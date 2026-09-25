<?php

namespace Tests\Feature;

use App\Models\{Expense, Invoice, InvoiceItem, Item, Service, User, Vehicle};
use App\Services\InventoryAccounting;
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryAccountingTest extends TestCase
{
    private $owner;
    private $stock;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'session.driver' => 'array']);
        DB::purge('sqlite');
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($p) => !str_contains($p, 'version_1_7_filled')));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertExitCode(0);
        // Payment columns from the MySQL-only version_1_7 migration skipped above.
        \Illuminate\Support\Facades\Schema::table('invoice_payments', function ($table) {
            foreach (['transaction_id', 'receipt', 'payment_type', 'payment_status'] as $column) {
                $table->string($column)->nullable();
            }
        });
        $this->owner = User::create(['name' => 'Shop', 'email' => 'stock@example.test', 'password' => bcrypt('test'), 'type' => 'owner', 'lang' => 'english']);
        Gate::before(fn ($user) => $user->type === 'owner' ? true : null);
        $this->actingAs($this->owner);
        DB::table('settings')->insert(['parent_id' => $this->owner->id, 'name' => 'pricing_feature', 'value' => 'off']);
        $this->stock = app(InventoryAccounting::class);
    }

    private function attributes(int $quantity = 5): array
    {
        return ['title' => 'Castrol motor yağı', 'item_code' => 'OIL-1', 'quantity' => $quantity,
            'units' => 1, 'purchase_price' => '200.00', 'sales_price' => '300.00', 'purchase_date' => '2026-09-23'];
    }

    public function test_interface_stays_turkish_for_legacy_accounts_and_language_route_is_removed()
    {
        $this->owner->update(['lang' => 'english']);
        $this->get('/item', ['Accept-Language' => 'en-US'])->assertOk()
            ->assertSee('Ürün adı')->assertSee('Alış fiyatı')->assertSee('Ürün Kategorileri')
            ->assertSee('lang="tr"', false)->assertDontSee('ti-language', false)->assertDontSee('/language/', false);
        $this->assertSame('tr', app()->getLocale());
        $this->get('/language/english')->assertNotFound();
        $this->assertSame('english', $this->owner->fresh()->lang);
    }

    public function test_guest_pages_and_validation_are_turkish_without_default_user()
    {
        auth()->logout();
        $this->owner->delete();
        $this->get('/forgot-password', ['Accept-Language' => 'en-US'])->assertOk()
            ->assertSee('Şifrenizi mi unuttunuz?')->assertSee('lang="tr"', false);
        $validator = validator(['email' => 'invalid', 'quantity' => 'abc'], ['email' => 'email', 'quantity' => 'integer', 'purchase_price' => 'required']);
        $this->assertSame('E-posta adresi geçerli bir e-posta adresi olmalıdır.', $validator->errors()->first('email'));
        $this->assertSame('Adet tam sayı olmalıdır.', $validator->errors()->first('quantity'));
        $this->assertSame('Alış fiyatı alanı zorunludur.', $validator->errors()->first('purchase_price'));
        $this->assertStringNotContainsString(':seconds', __('auth.throttle', ['seconds' => 60]));
        $this->assertSame('Kurulum tamamlandı', __('installer_messages.final.title'));
    }

    public function test_turkish_dates_and_money_only_change_display()
    {
        $this->assertSame('Europe/Istanbul', config('app.timezone'));
        $this->assertSame('Europe/Istanbul', date_default_timezone_get());
        DB::table('settings')->insert([
            ['parent_id' => $this->owner->id, 'name' => 'timezone', 'value' => 'America/New_York'],
            ['parent_id' => $this->owner->id, 'name' => 'company_date_format', 'value' => 'Y-m-d'],
            ['parent_id' => $this->owner->id, 'name' => 'company_time_format', 'value' => 'g:i A'],
        ]);
        $this->get('/item')->assertOk();
        $this->assertSame('Europe/Istanbul', config('app.timezone'));
        $this->assertSame('Europe/Istanbul', settings()['timezone']);
        $this->assertSame('24 Eyl 2026', dateFormat('2026-09-24'));
        $this->assertSame('14:30', timeFormat('14:30:00'));
        $this->assertSame('00:00', timeFormat('00:00:00'));
        $this->assertSame('25 Eyl 2026', dateFormat('2026-09-24T22:30:00Z'));
        $this->assertSame('01:30', settingTimeFormat(['company_time_format' => 'g:i A'], '2026-09-24T22:30:00Z'));
        $this->assertSame('24 Eyl 2026', settingDateFormat(['company_date_format' => 'd F Y'], '2026-09-24'));
        $this->assertSame('—', dateFormat(null));
        $this->assertSame('1.234,50 ₺', settingPriceFormat(['CURRENCY_SYMBOL' => '₺'], 1234.5));
        $item = $this->stock->savePurchase($this->owner->id, array_merge($this->attributes(2), ['purchase_price' => 200.25]));
        $this->assertEquals(400.5, Expense::sum('amount'));
        $this->assertEquals(200.25, $item->purchase_price);
    }

    public function test_company_settings_use_fixed_turkish_time_without_format_inputs()
    {
        $this->get(route('setting.index'))->assertOk()
            ->assertDontSee('name="timezone"', false)
            ->assertDontSee('name="company_date_format"', false)
            ->assertDontSee('name="company_time_format"', false);
        $company = ['company_name' => 'Test servis', 'company_email' => 'servis@example.test',
            'company_phone' => '5551234567', 'company_address' => 'İstanbul'];
        $this->post(route('setting.company'), $company)->assertRedirect()->assertSessionMissing('error');
        $this->post(route('setting.company'), $company + ['timezone' => 'UTC',
            'company_date_format' => 'Y-m-d', 'company_time_format' => 'g:i A'])->assertRedirect()->assertSessionMissing('error');
        foreach (['timezone' => 'Europe/Istanbul', 'company_date_format' => 'd M Y', 'company_time_format' => 'H:i'] as $name => $value) {
            $this->assertSame($value, DB::table('settings')->where('parent_id', $this->owner->id)->where('name', $name)->value('value'));
            $this->assertSame($value, settingsById($this->owner->id)[$name]);
        }
    }

    public function test_categories_can_be_managed_and_filter_products_without_creating_purchase_expenses()
    {
        $this->post('/item-category', ['name' => ' Motor Yağları '])->assertRedirect(route('item-category.index'));
        $category = \App\Models\ItemCategory::firstOrFail();
        $this->assertSame('Motor Yağları', $category->name);
        $this->post('/item-category', ['name' => 'Motor Yağları'])->assertSessionHasErrors('name');
        $item = $this->purchase();
        $this->put('/item/' . $item->id, $this->attributes() + ['category_id' => $category->id])->assertRedirect();
        $this->assertSame(1, Expense::count());
        $this->assertEquals($category->id, $item->fresh()->category_id);
        $other = $this->purchase();
        $this->get('/item?category=' . $category->id)->assertOk()->assertViewHas('items', fn ($items) => $items->pluck('id')->all() === [$item->id]);
        $this->get('/item?category=uncategorized')->assertOk()->assertViewHas('items', fn ($items) => $items->pluck('id')->all() === [$other->id]);
        $this->get('/item-category')->assertOk()->assertSee('Motor Yağları');
        $this->get('/item-category/' . $category->id . '/edit')->assertOk();
        $this->put('/item-category/' . $category->id, ['name' => 'Yağlar'])->assertRedirect();
        $this->delete('/item-category/' . $category->id)->assertSessionHas('error');
        $this->assertNotNull($category->fresh());
        $this->put('/item/' . $item->id, $this->attributes() + ['category_id' => ''])->assertRedirect();
        $this->delete('/item-category/' . $category->id)->assertRedirect();
        $this->assertNull($category->fresh());
    }

    public function test_categories_are_tenant_scoped_in_crud_product_assignment_and_invoice_catalog()
    {
        $foreign = new \App\Models\ItemCategory(['name' => 'Private category']);
        $foreign->parent_id = $this->owner->id + 100;
        $foreign->save();
        $this->get('/item-category/' . $foreign->id . '/edit')->assertNotFound();
        $this->put('/item-category/' . $foreign->id, ['name' => 'Changed'])->assertNotFound();
        $this->delete('/item-category/' . $foreign->id)->assertNotFound();
        $this->post('/item', $this->attributes() + ['category_id' => $foreign->id])->assertSessionHas('error');
        $this->assertSame(0, Item::count());
        $this->get('/invoice/create')->assertOk()->assertViewHas('itemCategories', fn ($categories) => !$categories->has($foreign->id));
        $this->owner->type = 'employee';
        $this->owner->save();
        $this->get('/item-category')->assertForbidden();
        $this->post('/item-category', ['name' => 'Unauthorized'])->assertForbidden();
    }

    public function test_deleted_product_and_category_can_be_returned_as_uncategorized()
    {
        $this->post('/item-category', ['name' => 'Oil']);
        $category = \App\Models\ItemCategory::firstOrFail();
        $item = $this->stock->savePurchase($this->owner->id, $this->attributes(1) + ['category_id' => $category->id]);
        $invoice = $this->invoice();
        $line = $this->stock->add($invoice, ['item' => $item->id, 'quantity' => 1]);
        $item->delete();
        $this->delete('/item-category/' . $category->id)->assertRedirect();
        $this->get('/invoice/' . encrypt($invoice->id) . '/edit')->assertOk()
            ->assertViewHas('itemCatalog', fn ($items) => $items->first()['category'] === 'uncategorized' && !$items->first()['available']);
        $this->stock->remove($this->owner->id, $invoice->id, $line->id);
        $this->assertNull(Item::firstOrFail()->category_id);
        $this->assertSame(1, (int) Item::first()->quantity);
        $this->assertSame(1, Expense::count());
    }

    private function purchase(int $quantity = 5): Item
    {
        return $this->stock->savePurchase($this->owner->id, $this->attributes($quantity));
    }

    private function invoice(): Invoice
    {
        return Invoice::create(['parent_id' => $this->owner->id, 'client' => $this->owner->id, 'invoice_id' => 1, 'invoice_date' => '2026-09-23', 'status' => 0]);
    }

    public function test_purchase_creates_one_expense_and_only_new_stock_adds_more_expense()
    {
        $this->post('/item', $this->attributes())->assertRedirect(route('item.index'));
        $item = Item::first();
        $this->assertEquals(1000, Expense::sum('amount'));
        $this->assertSame('Castrol motor yağı', Expense::first()->title);
        $this->put('/item/' . $item->id, $this->attributes())->assertRedirect();
        $this->assertSame(1, Expense::count());
        $this->stock->savePurchase($this->owner->id, array_merge($this->attributes(7), ['purchase_price' => '200.25']), $item->id);
        $this->assertEquals(1400.50, Expense::sum('amount'));
        $this->assertSame(7, (int) $item->fresh()->quantity);
    }

    public function test_selling_and_removing_deleted_product_restores_snapshot_without_new_expense()
    {
        $item = $this->purchase(2);
        $invoice = $this->invoice();
        $line = $this->stock->add($invoice, ['item' => $item->id, 'quantity' => 2, 'amount' => 1]);
        $this->assertSame(0, (int) $item->fresh()->quantity);
        $this->assertEquals(600, $invoice->fresh()->getInvoiceSubTotalAmount());
        $this->assertSame(0, DB::table('invoice_payments')->count());
        $item->delete();
        $this->assertSame('Castrol motor yağı', $line->fresh()->item_title);
        $this->stock->remove($this->owner->id, $invoice->id, $line->id);
        $restored = Item::firstOrFail();
        $this->assertSame('Castrol motor yağı', $restored->title);
        $this->assertEquals(200, $restored->purchase_price);
        $this->assertEquals(300, $restored->sales_price);
        $this->assertSame(2, (int) $restored->quantity);
        $this->assertEquals(400, Expense::sum('amount'));
        $this->stock->remove($this->owner->id, $invoice->id, $line->id);
        $this->assertSame(2, (int) $restored->fresh()->quantity);
    }

    public function test_quantity_edits_are_delta_based_and_preserve_sale_price()
    {
        $item = $this->purchase();
        $invoice = $this->invoice();
        $line = $this->stock->add($invoice, ['item' => $item->id, 'quantity' => 2]);
        $item->sales_price = 999;
        $item->save();
        foreach ([3, 3, 1] as $quantity) {
            $this->stock->sync($invoice, [['id' => $line->id, 'item' => $item->id, 'quantity' => $quantity]]);
            $this->assertSame(5 - $quantity, (int) $item->fresh()->quantity);
            $this->assertEquals(300, $line->fresh()->amount);
        }
        $this->stock->sync($invoice, []);
        $this->assertSame(5, (int) $item->fresh()->quantity);
        $this->assertSame(1, Expense::count());
    }

    public function test_failed_multi_line_sale_rolls_back_all_stock_changes()
    {
        $item = $this->purchase(1);
        $invoice = $this->invoice();
        try {
            $this->stock->sync($invoice, [['item' => $item->id, 'quantity' => 1], ['item' => $item->id, 'quantity' => 1]]);
            $this->fail('Overselling should fail');
        } catch (ValidationException $e) {
            $this->assertSame(1, (int) $item->fresh()->quantity);
            $this->assertSame(0, InvoiceItem::count());
            $this->assertSame(0, DB::table('stock_movements')->where('kind', 'sale')->count());
        }
    }

    public function test_multiple_returns_recreate_only_one_product()
    {
        $item = $this->purchase(3);
        $invoice = $this->invoice();
        $first = $this->stock->add($invoice, ['item' => $item->id, 'quantity' => 1]);
        $second = $this->stock->add($invoice, ['item' => $item->id, 'quantity' => 2]);
        $item->delete();
        $this->stock->remove($this->owner->id, $invoice->id, $first->id);
        $this->stock->remove($this->owner->id, $invoice->id, $second->id);
        $this->assertSame(1, Item::count());
        $this->assertSame(3, (int) Item::first()->quantity);
        $this->assertSame(1, Expense::count());
    }

    public function test_old_invoice_removal_does_not_invent_stock()
    {
        $item = $this->purchase();
        $invoice = $this->invoice();
        $line = InvoiceItem::create(['invoice_id' => $invoice->id, 'item' => $item->id, 'quantity' => 4, 'amount' => 250, 'parent_id' => $this->owner->id]);
        $this->stock->remove($this->owner->id, $invoice->id, $line->id);
        $this->assertSame(5, (int) $item->fresh()->quantity);
    }

    public function test_foreign_stock_and_invoice_mutations_are_rejected()
    {
        $other = User::create(['name' => 'Other', 'email' => 'other-stock@example.test', 'password' => bcrypt('test'), 'type' => 'owner']);
        $foreign = $this->stock->savePurchase($other->id, $this->attributes());
        $invoice = $this->invoice();
        $this->post('/invoice/' . $invoice->id . '/item/store', ['item' => $foreign->id, 'quantity' => 1])->assertSessionHasErrors('item');
        $this->assertSame(5, (int) $foreign->fresh()->quantity);
        $foreignInvoice = Invoice::create(['parent_id' => $other->id, 'client' => $other->id, 'invoice_id' => 1]);
        $foreignLine = $this->stock->add($foreignInvoice, ['item' => $foreign->id, 'quantity' => 1]);
        $this->post('/invoice/' . $foreignInvoice->id . '/item/' . $foreignLine->id . '/store')->assertNotFound();
        $this->post('/invoice/item/destroy', ['id' => $foreignLine->id])->assertRedirect();
        $this->assertSame(4, (int) $foreign->fresh()->quantity);
        $this->assertNotNull($foreignLine->fresh());
    }

    public function test_failed_invoice_creation_leaves_no_invoice_or_sale()
    {
        $item = $this->purchase(1);
        $service = Service::create(['parent_id' => $this->owner->id, 'client' => $this->owner->id]);
        $data = ['invoice_date' => '2026-09-23', 'client' => $this->owner->id, 'service' => $service->id,
            'item' => [$item->id], 'quantity' => [2], 'types' => []];
        $this->post('/invoice', $data)->assertSessionHasErrors('item');
        $this->assertSame(0, Invoice::count());
        $this->assertSame(1, (int) $item->fresh()->quantity);
        $this->assertSame(0, InvoiceItem::count());
    }

    public function test_deleting_invoice_restores_all_product_quantities()
    {
        $item = $this->purchase(5);
        $invoice = $this->invoice();
        $this->stock->add($invoice, ['item' => $item->id, 'quantity' => 3]);
        $this->delete('/invoice/' . $invoice->id)->assertRedirect(route('invoice.index'));
        $this->assertSame(5, (int) $item->fresh()->quantity);
        $this->assertSame(0, InvoiceItem::count());
        $this->assertSame(1, Expense::count());
    }

    public function test_replacement_returns_old_product_and_deducts_new_product_atomically()
    {
        $item = $this->purchase(2);
        $replacement = $this->stock->savePurchase($this->owner->id, array_merge($this->attributes(1), ['title' => 'Filter', 'sales_price' => 400]));
        $invoice = $this->invoice();
        $line = $this->stock->add($invoice, ['item' => $item->id, 'quantity' => 2]);
        $this->stock->sync($invoice, [['id' => $line->id, 'item' => $replacement->id, 'quantity' => 1]]);
        $this->assertSame(2, (int) $item->fresh()->quantity);
        $this->assertSame(0, (int) $replacement->fresh()->quantity);
        $this->assertEquals(400, $line->fresh()->amount);
        $this->assertSame('Filter', $line->fresh()->item_title);
    }

    public function test_income_report_uses_actual_payments_and_no_automatic_payment_is_created()
    {
        $item = $this->purchase();
        $invoice = $this->invoice();
        $this->stock->add($invoice, ['item' => $item->id, 'quantity' => 1]);
        $response = $this->get(route('report.income'))->assertOk();
        $this->assertEquals(0, $response->viewData('invoices')->first()->payments_sum_amount ?? 0);
        DB::table('invoice_payments')->insert(['invoice_id' => $invoice->id, 'parent_id' => $this->owner->id, 'amount' => 150, 'payment_date' => '2026-09-23']);
        $response = $this->get(route('report.income'))->assertOk();
        $this->assertEquals(150, $response->viewData('invoices')->first()->payments_sum_amount);
        $this->stock->sync($invoice, []);
        $this->assertEquals(0, $invoice->payments()->sum('amount'));
        $this->assertEquals(150, $invoice->payments()->where('amount', '>', 0)->sum('amount'));
        $response = $this->get(route('report.income'))->assertOk();
        $this->assertEquals(0, $response->viewData('invoices')->first()->payments_sum_amount);
    }

    public function test_paid_quantity_reduction_reverses_income_and_preserves_service_payment()
    {
        $item = $this->purchase();
        $invoice = $this->invoice();
        $invoice->types()->create(['parent_id' => $this->owner->id, 'rate' => 100]);
        $line = $this->stock->add($invoice, ['item' => $item->id, 'quantity' => 2]);
        DB::table('invoice_payments')->insert(['invoice_id' => $invoice->id, 'parent_id' => $this->owner->id, 'amount' => 700, 'payment_date' => now()->toDateString()]);
        foreach ([1 => 400, 0 => 100] as $quantity => $income) {
            $this->stock->sync($invoice, [['id' => $line->id, 'item' => $item->id, 'quantity' => $quantity]]);
            $this->stock->refreshStatus($invoice);
            $this->assertEquals($income, $invoice->payments()->sum('amount'));
            $this->assertSame(5 - $quantity, (int) $item->fresh()->quantity);
            $this->assertSame(2, (int) $invoice->fresh()->status);
            $report = app(\App\Http\Controllers\ReportController::class)->incomeByMonth(now()->year);
            $this->assertEquals($income, $report['income'][now()->month - 1]);
        }
        $this->stock->sync($invoice, []);
        $this->assertSame(3, $invoice->payments()->count());
        $this->assertEquals(700, $invoice->payments()->where('amount', '>', 0)->sum('amount'));
        $this->assertEquals(-600, $invoice->payments()->where('amount', '<', 0)->sum('amount'));
    }

    public function test_paid_removal_is_idempotent_and_includes_tax_and_cents()
    {
        $item = $this->stock->savePurchase($this->owner->id, array_merge($this->attributes(), ['sales_price' => '300.25']));
        $taxId = DB::table('taxes')->insertGetId(['title' => 'KDV', 'rate' => 20, 'parent_id' => $this->owner->id]);
        $invoice = $this->invoice();
        $line = $this->stock->add($invoice, ['item' => $item->id, 'quantity' => 2, 'tax' => (string) $taxId]);
        DB::table('invoice_payments')->insert(['invoice_id' => $invoice->id, 'parent_id' => $this->owner->id, 'amount' => 720.60, 'payment_date' => now()->toDateString()]);
        $this->stock->sync($invoice, [['id' => $line->id, 'item' => $item->id, 'quantity' => 1]]);
        $this->assertEqualsWithDelta(360.30, $invoice->payments()->sum('amount'), 0.001);
        $this->stock->remove($this->owner->id, $invoice->id, $line->id);
        $this->stock->remove($this->owner->id, $invoice->id, $line->id);
        $this->assertEqualsWithDelta(0, $invoice->payments()->sum('amount'), 0.001);
        $this->assertSame(3, $invoice->payments()->count());
        $this->assertSame(5, (int) $item->fresh()->quantity);
    }

    public function test_external_labor_flows_from_service_to_invoice_portal_and_paid_reductions()
    {
        \Illuminate\Support\Facades\Schema::table('service_items', fn ($table) => $table->string('tax')->nullable());
        $vehicle = Vehicle::create(['parent_id' => $this->owner->id, 'client' => $this->owner->id, 'license_plate' => '34 LAB 01']);
        $data = ['client' => $this->owner->id, 'vehicle' => $vehicle->id, 'assign' => $this->owner->id,
            'service_date' => now()->toDateString(), 'due_date' => now()->toDateString(),
            'service_time' => '09:00', 'due_time' => '17:00', 'status' => 'scheduled', 'types' => [],
            'external_labor_amount' => '1250.75'];
        $this->post('/service', $data)->assertRedirect()->assertSessionMissing('error');
        $service = Service::firstOrFail();
        $invoice = Invoice::firstOrFail();
        $this->assertEquals(1250.75, $invoice->getInvoiceAllTotalAmount());
        $this->assertEquals(0, $invoice->payments()->sum('amount'));
        $this->assertSame(0, Expense::count());
        $this->get('/service/' . encrypt($service->id) . '/edit')->assertOk()->assertSee('1250.75');
        $this->get('/service/' . encrypt($service->id))->assertOk()->assertSee('1.250,75');
        $this->get('/invoice/' . encrypt($invoice->id))->assertOk()->assertSee('Harici işçilik')->assertSee('1.250,75');
        $pool = app(\App\Services\VehicleQrPool::class);
        $pool->replenish($this->owner->id);
        $code = \App\Models\VehicleQrCode::available()->first();
        $pool->markPrinted($this->owner->id, [$code->id]);
        $pool->assignExisting($this->owner->id, $vehicle->id, $code->id);
        $this->get('/q/' . $code->token)->assertOk()->assertSee('Faturalar')->assertDontSee('Harici işçilik');
        $this->get('/q/' . $code->token . '/invoice/' . $invoice->id)->assertOk()->assertSee('Harici işçilik')->assertViewHas('total', 1250.75);
        DB::table('invoice_payments')->insert(['invoice_id' => $invoice->id, 'parent_id' => $this->owner->id,
            'amount' => 1250.75, 'payment_date' => now()->toDateString()]);
        foreach (['500.25', '500.25', '0'] as $amount) {
            $this->put('/service/' . $service->id, array_merge($data, ['external_labor_amount' => $amount]))->assertRedirect()->assertSessionMissing('error');
            $this->assertEquals((float) $amount, $invoice->fresh()->getInvoiceAllTotalAmount());
            $this->assertEquals((float) $amount, $invoice->payments()->sum('amount'));
        }
        $this->assertSame(3, $invoice->payments()->count());
        $this->assertEquals(1250.75, $invoice->payments()->where('amount', '>', 0)->sum('amount'));
        $this->put('/service/' . $service->id, array_merge($data, ['external_labor_amount' => '100']))->assertRedirect();
        $this->assertEquals(100, $invoice->fresh()->getInvoiceTotalDueAmount());
        $this->assertEquals(0, $invoice->payments()->sum('amount'));
        foreach (['-1', 'abc', '1.999', '10000000000'] as $invalid) {
            $this->put('/service/' . $service->id, array_merge($data, ['external_labor_amount' => $invalid]))->assertSessionHas('error');
            $this->assertEquals(100, $invoice->fresh()->getInvoiceAllTotalAmount());
        }
        DB::table('invoice_payments')->insert(['invoice_id' => $invoice->id, 'parent_id' => $this->owner->id,
            'amount' => 25, 'payment_date' => now()->toDateString()]);
        $this->put('/service/' . $service->id, array_merge($data, ['external_labor_amount' => '50']))->assertRedirect();
        $this->assertEquals(25, $invoice->payments()->sum('amount'));
        $this->assertEquals(25, $invoice->fresh()->getInvoiceTotalDueAmount());
        $this->assertSame(1, (int) $invoice->fresh()->status);
        $this->put('/service/' . $service->id, array_merge($data, ['external_labor_amount' => '']))->assertRedirect();
        $this->assertEquals(0, $invoice->fresh()->external_labor_amount);
    }

    public function test_invoice_service_selection_copies_labor_once_and_never_creates_income()
    {
        $vehicle = Vehicle::create(['parent_id' => $this->owner->id, 'license_plate' => '34 LAB 02']);
        $service = Service::create(['parent_id' => $this->owner->id, 'client' => $this->owner->id,
            'vehicle' => $vehicle->id, 'external_labor_amount' => '250.50']);
        $data = ['invoice_date' => now()->toDateString(), 'client' => $this->owner->id, 'service' => $service->id, 'types' => [], 'external_labor_amount' => '999'];
        $this->post('/invoice', $data)->assertRedirect()->assertSessionMissing('error');
        $invoice = Invoice::firstOrFail();
        $this->assertEquals(250.50, $invoice->getInvoiceAllTotalAmount());
        $this->put('/invoice/' . encrypt($invoice->id), $data)->assertRedirect()->assertSessionMissing('error');
        $this->assertEquals(250.50, $invoice->fresh()->getInvoiceAllTotalAmount());
        $this->assertSame(0, $invoice->payments()->count());
        $this->get('/invoice/' . encrypt($invoice->id) . '/edit')->assertOk()->assertSee('250.50');
        $this->get(route('client.service', $this->owner->id))->assertJsonFragment(['external_labor_amount' => '250.50']);
        $other = Service::create(['parent_id' => $this->owner->id + 100, 'client' => $this->owner->id, 'external_labor_amount' => 777]);
        $this->post('/invoice', array_merge($data, ['service' => $other->id]))->assertSessionHas('error');
        $this->assertSame(1, Invoice::count());
        // Reducing labor while adding a more expensive product must not reverse income mid-update.
        $item = $this->purchase();
        $replacement = Service::create(['parent_id' => $this->owner->id, 'client' => $this->owner->id, 'vehicle' => $vehicle->id]);
        DB::table('invoice_payments')->insert(['invoice_id' => $invoice->id, 'parent_id' => $this->owner->id,
            'amount' => 250.50, 'payment_date' => now()->toDateString()]);
        $this->put('/invoice/' . encrypt($invoice->id), array_merge($data, ['service' => $replacement->id,
            'item' => [$item->id], 'quantity' => [1]]))->assertRedirect();
        $this->assertEquals(300, $invoice->fresh()->getInvoiceAllTotalAmount());
        $this->assertEquals(250.50, $invoice->payments()->sum('amount'));
        $this->assertEquals(49.50, $invoice->fresh()->getInvoiceTotalDueAmount());
    }

    public function test_invoice_routes_deduct_restore_and_only_payments_count_as_income()
    {
        $item = $this->purchase();
        $vehicle = Vehicle::create(['parent_id' => $this->owner->id, 'license_plate' => '34 STK 01']);
        $service = Service::create(['parent_id' => $this->owner->id, 'vehicle' => $vehicle->id, 'client' => $this->owner->id]);
        $data = ['invoice_date' => '2026-09-23', 'client' => $this->owner->id, 'service' => $service->id,
            'item' => [$item->id], 'quantity' => [2], 'amount' => [1], 'types' => []];
        $this->post('/invoice', $data)->assertRedirect()->assertSessionHasNoErrors();
        $invoice = Invoice::firstOrFail();
        $line = $invoice->items()->firstOrFail();
        $this->assertSame(3, (int) $item->fresh()->quantity);
        $this->assertEquals(300, $line->amount);
        $this->assertEquals(0, $invoice->payments()->sum('amount'));
        $this->get('/invoice/' . encrypt($invoice->id) . '/edit')->assertOk()->assertSee('item_id[0]', false);
        DB::table('invoice_payments')->insert(['invoice_id' => $invoice->id, 'parent_id' => $this->owner->id, 'amount' => 100, 'payment_date' => '2026-09-23']);
        $data['item_id'] = [$line->id];
        $data['quantity'] = [1];
        $this->put('/invoice/' . encrypt($invoice->id), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(4, (int) $item->fresh()->quantity);
        $this->assertEquals(100, $invoice->payments()->sum('amount'));
        $this->assertSame(1, (int) $invoice->fresh()->status);
        $this->post('/invoice/' . $invoice->id . '/item/store', ['item' => $item->id, 'quantity' => 1])->assertRedirect();
        $added = $invoice->items()->latest('id')->first();
        $this->post('/invoice/' . $invoice->id . '/item/' . $added->id . '/store')->assertRedirect();
        $this->assertSame(4, (int) $item->fresh()->quantity);
        $this->post('/invoice/item/destroy', ['id' => $line->id])->assertRedirect();
        $this->assertSame(5, (int) $item->fresh()->quantity);
    }
}
