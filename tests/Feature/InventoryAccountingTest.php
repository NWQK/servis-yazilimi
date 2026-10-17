<?php

namespace Tests\Feature;

use App\Models\{Expense, Invoice, InvoiceItem, Item, Service, User, Vehicle};
use App\Services\InventoryAccounting;
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InventoryAccountingTest extends TestCase
{
    use \Tests\FinancialForms;
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
        // Product forms require a real unit belonging to this business.
        DB::table('units')->insert(['id'=>1,'unit'=>'Adet','parent_id'=>$this->owner->id]);
        DB::table('settings')->insert(['parent_id' => $this->owner->id, 'name' => 'pricing_feature', 'value' => 'off']);
        $this->stock = app(InventoryAccounting::class);
    }

    public function test_counter_sale_is_paid_once_and_stock_is_deducted_without_invoice()
    {
        $item = $this->purchase(5);
        $data = ['quantity'=>2,'unit_price'=>300,'request_key'=>(string)\Illuminate\Support\Str::uuid()];
        $this->get('/item/'.$item->id.'/counter-sale')->assertOk()->assertSee('Satışı ve tahsilatı kaydet');
        for ($i=0;$i<2;$i++) $this->post('/item/'.$item->id.'/counter-sale',$data)->assertSessionHasNoErrors();
        $this->assertSame(3,(int)$item->fresh()->quantity);
        $this->assertSame(1,DB::table('counter_sales')->count());
        $this->assertEquals(600,DB::table('counter_sales')->sum('amount'));
        $this->assertSame(0,Invoice::count());
        $this->assertSame(0,DB::table('invoice_payments')->count());
        $this->assertSame(1,Expense::count());
        $report = app(\App\Http\Controllers\ReportController::class)->incomeByMonth(now()->year);
        $this->assertEquals(600,$report['income'][now()->month-1]);
        $this->get('/report/income')->assertOk()->assertViewHas('counterTotal',600)->assertSee('Elden satışlar');
        DB::connection()->getPdo()->sqliteCreateFunction('MONTH',fn ($date)=>(int)substr($date,5,2));
        $this->get('/report/profit-loss?year='.now()->year)->assertOk()->assertViewHas('report',fn ($rows)=>collect($rows)->sum('income') == 600);
        $item->delete();
        $this->assertEquals(600,DB::table('counter_sales')->sum('amount'));
    }

    public function test_counter_sale_rejects_short_stock_changed_price_and_foreign_item()
    {
        $item = $this->purchase(1);
        $data = ['quantity'=>2,'unit_price'=>300,'request_key'=>(string)\Illuminate\Support\Str::uuid()];
        $url = '/item/'.$item->id.'/counter-sale';
        $this->post($url,$data)->assertSessionHasErrors('item');
        $this->post($url,array_replace($data,['quantity'=>1,'unit_price'=>200]))->assertSessionHasErrors('item');
        $this->post($url,array_replace($data,['quantity'=>-1]))->assertSessionHasErrors('quantity');
        $item->parent_id = 999; $item->save();
        $this->post($url,array_replace($data,['quantity'=>1]))->assertNotFound();
        $this->assertSame(0,DB::table('counter_sales')->count());
        $this->assertSame(1,(int)$item->fresh()->quantity);
    }

    public function test_invoice_deletion_requires_choice_and_can_leave_stock_unchanged()
    {
        $item = $this->purchase(5); $invoice = $this->invoice();
        $this->stock->add($invoice,['item'=>$item->id,'quantity'=>2]);
        $this->assertSame(3,(int)$item->fresh()->quantity);
        $this->assertSame(0,$invoice->payments()->count());
        $this->get('/invoice/'.$invoice->id.'/delete-confirmation')->assertOk()->assertSee('stoklara tekrar eklensin mi');
        $this->deleteInvoice('/invoice/'.$invoice->id)->assertSessionHasErrors('return_stock');
        $this->assertNotNull($invoice->fresh());
        $this->deleteInvoice('/invoice/'.$invoice->id,['return_stock'=>0])->assertRedirect(route('invoice.index'));
        $this->assertSame(3,(int)$item->fresh()->quantity);
        $this->assertNull($invoice->fresh());
        $this->assertSame(0,InvoiceItem::count());
    }

    public function test_new_booking_services_migration_is_repeatable()
    {
        $migration = require database_path('migrations/2026_09_28_000001_create_counter_sales.php');
        $migration->up();
        foreach (['Kaporta ve boya','Diğer hizmetler'] as $name) $this->assertSame(1,DB::table('booking_services')->where('name',$name)->count());
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
            ->assertSee('Ürün adı')->assertSee('Alış fiyatı')->assertSee('Ürün kategorileri')
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
            ->assertSee('Sıfırlama bağlantısı gönder')->assertSee('sanayirandevu-logo-light.svg')->assertSee('lang="tr"', false);
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

    public function test_billing_details_are_optional_tenant_scoped_and_do_not_change_money_or_stock()
    {
        $this->post('/settings/invoice-business', ['billing' => ['name' => 'Deneme Servis', 'tax_number' => '1234567890', 'phone' => '2121234567', 'unknown' => 'discard']])->assertRedirect()->assertSessionHasNoErrors();
        $item = $this->purchase(8);
        $invoice = $this->invoice();
        $invoice->external_labor_amount = 200;
        $invoice->discount_amount = 50.25;
        $invoice->save();
        $this->stock->add($invoice, ['item' => $item->id, 'quantity' => 4]);
        $taxId = DB::table('taxes')->insertGetId(['title' => 'KDV', 'rate' => 20, 'parent_id' => $this->owner->id]);
        $invoice->items()->update(['tax' => (string) $taxId]);
        DB::table('invoice_payments')->insert(['invoice_id' => $invoice->id, 'parent_id' => $this->owner->id, 'amount' => 500, 'payment_date' => now()->toDateString()]);
        $this->assertEquals(1589.75, $invoice->fresh()->getInvoiceAllTotalAmount());
        $this->get('/invoice')->assertOk()->assertSee('1.589,75 ₺');
        $this->get('/invoice/'.$invoice->id.'/billing')->assertOk()->assertSee('Müşteri bilgileri ekle')->assertDontSee('Müşteri türü')->assertDontSee('billing[type]', false);
        $data = ['billing' => ['type' => 'company', 'name' => 'Örnek <script>firma</script>', 'tax_number' => '0123456789', 'email' => '', 'phone' => '5321234567', 'extra' => 'discard'], 'discount_amount' => 999, 'parent_id' => 999];
        $this->putInvoice('/invoice/'.$invoice->id.'/billing', $data)->assertRedirect()->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertArrayNotHasKey('extra', $invoice->customer_details);
        $this->assertArrayNotHasKey('type', $invoice->customer_details);
        $this->assertArrayNotHasKey('unknown', $invoice->business_details);
        $this->assertSame('0123456789', $invoice->customer_details['tax_number']);
        $this->assertEquals(1589.75, $invoice->getInvoiceAllTotalAmount());
        $this->assertEquals(500, $invoice->payments()->sum('amount'));
        $this->assertSame(4, (int) $item->fresh()->quantity);
        $this->assertSame(1, Expense::count());
        $this->get('/invoice/'.encrypt($invoice->id))->assertOk()->assertSee('1.589,75')->assertSee('Deneme Servis')->assertSee('Örnek <script>firma</script>')->assertDontSee('<script>firma</script>', false);
        $this->post('/settings/invoice-business', ['billing' => ['name' => 'Yeni Unvan']])->assertRedirect();
        $this->assertSame('Deneme Servis', $invoice->fresh()->business_details['name']);
        $this->assertSame('Yeni Unvan', $this->invoice()->business_details['name']);
        $this->putInvoice('/invoice/'.$invoice->id.'/billing', ['billing' => ['name' => '', 'phone' => '']])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($invoice->fresh()->customer_details['name']);
        $this->putInvoice('/invoice/'.$invoice->id.'/billing', ['billing' => ['phone' => '05321234567', 'tax_number' => '12']])->assertSessionHasErrors(['billing.phone', 'billing.tax_number']);
        $invoice->parent_id = 999; $invoice->save();
        $this->get('/invoice/'.$invoice->id.'/billing')->assertNotFound();
        $this->putInvoice('/invoice/'.$invoice->id.'/billing', $data)->assertNotFound();
        $staff = User::create(['name'=>'Staff','email'=>'billing-staff@example.test','password'=>bcrypt('test'),'type'=>'staff','parent_id'=>$this->owner->id]);
        $this->actingAs($staff);
        $this->putInvoice('/invoice/'.$invoice->id.'/billing', $data)->assertForbidden();
        $this->post('/settings/invoice-business', ['billing'=>['name'=>'Unauthorized']])->assertForbidden();
        $this->assertSame('Yeni Unvan', \App\Services\InvoiceBilling::business($this->owner->id)['name']);
    }

    public function test_invoice_create_and_edit_store_optional_billing_and_portal_displays_it()
    {
        $vehicle = Vehicle::create(['parent_id'=>$this->owner->id,'license_plate'=>'34 BILL 01']);
        $service = Service::create(['parent_id'=>$this->owner->id,'client'=>$this->owner->id,'vehicle'=>$vehicle->id]);
        $data = ['invoice_date'=>now()->toDateString(),'client'=>$this->owner->id,'service'=>$service->id,'types'=>[], 'billing'=>['name'=>'Müşteri Unvanı','type'=>'individual']];
        $this->postInvoice('/invoice', $data)->assertRedirect()->assertSessionHasNoErrors();
        $invoice = Invoice::firstOrFail();
        $this->assertSame('Müşteri Unvanı', $invoice->customer_details['name']);
        $data['billing'] = ['name'=>'Güncel Unvan','address'=>'Örnek adres'];
        $this->putInvoice('/invoice/'.encrypt($invoice->id), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Güncel Unvan', $invoice->fresh()->customer_details['name']);
        $pool = app(\App\Services\VehicleQrPool::class);
        $pool->replenish($this->owner->id);
        $code = \App\Models\VehicleQrCode::available()->first();
        $pool->markPrinted($this->owner->id, [$code->id]);
        $pool->assignExisting($this->owner->id, $vehicle->id, $code->id);
        auth()->logout();
        $this->get('/q/'.$code->token.'/invoice/'.$invoice->id)->assertOk()->assertSee('Güncel Unvan')->assertSee('Örnek adres');
    }

    private function supplierFixture(?int $tenant = null): \App\Models\Supplier
    {
        $supplier = new \App\Models\Supplier(['company_name'=>'Yağ tedarikçisi', 'contact_name'=>'Ali Usta', 'phone'=>'5321234567', 'email'=>'tedarik@example.test']);
        $supplier->parent_id = $tenant ?? $this->owner->id;
        $supplier->save();
        return $supplier;
    }

    public function test_supplier_directory_contacts_and_tenant_isolation()
    {
        $data = ['company_name'=>'Parça AŞ', 'contact_name'=>'Ali Yılmaz', 'phone'=>'5321234567', 'email'=>'parca@example.test'];
        $this->post('/supplier', $data)->assertRedirect(route('supplier.index'));
        $supplier = \App\Models\Supplier::firstOrFail();
        $this->get('/supplier')->assertOk()->assertSee('Parça AŞ')->assertSee('https://wa.me/905321234567', false);
        $this->get('/supplier/'.$supplier->id)->assertOk()->assertSee('Ali Yılmaz')->assertSee('mailto:parca@example.test', false);
        $this->get('/supplier/'.$supplier->id.'/edit')->assertOk()->assertSee('5321234567');
        $this->put('/supplier/'.$supplier->id, array_replace($data, ['company_name'=>'Yeni ad']))->assertRedirect();
        $foreign = $this->supplierFixture(999);
        $this->get('/supplier')->assertDontSee('Yağ tedarikçisi');
        $this->get('/supplier/'.$foreign->id)->assertNotFound();
        $this->get('/supplier/'.$foreign->id.'/edit')->assertNotFound();
        $this->put('/supplier/'.$foreign->id, $data)->assertNotFound();
        $this->delete('/supplier/'.$foreign->id)->assertNotFound();
        $this->post('/supplier', array_replace($data, ['phone'=>'05321234567']))->assertSessionHasErrors('phone');
        $this->post('/supplier', array_replace($data, ['email'=>'bad']))->assertSessionHasErrors('email');
        $this->assertSame(0, Expense::count());
    }

    public function test_optional_supplier_and_stock_threshold_can_be_cleared()
    {
        $supplier = $this->supplierFixture();
        $this->get('/item/create')->assertOk()->assertSee('Tedarikçi olmadan ekle')->assertSee('Stok uyarı miktarı');
        $data = $this->attributes(2) + ['supplier_id'=>$supplier->id, 'stock_alert_quantity'=>2];
        $this->post('/item', $data)->assertSessionMissing('error');
        $item = Item::firstOrFail();
        $this->assertEquals($supplier->id, $item->supplier_id);
        $this->get('/item?low_stock=1')->assertOk()->assertSee('Düşük stok');
        $this->get('/item/'.$item->id.'/edit')->assertOk()->assertSee('Stok uyarı miktarı');
        $this->get('/item/'.$item->id)->assertOk()->assertSee('Yağ tedarikçisi');
        $this->put('/item/'.$item->id, array_replace($data, ['supplier_id'=>'', 'stock_alert_quantity'=>'']))->assertSessionMissing('error');
        $this->assertNull($item->fresh()->supplier_id);
        $this->assertNull($item->fresh()->stock_alert_quantity);
        $this->assertSame(0, Item::lowStock()->count());
        $this->put('/item/'.$item->id, array_replace($data, ['stock_alert_quantity'=>-1]))->assertSessionHas('error');
        $foreign = $this->supplierFixture(999);
        $this->put('/item/'.$item->id, array_replace($data, ['supplier_id'=>$foreign->id]))->assertSessionHas('error');
    }

    public function test_stock_notifications_follow_stock_and_offer_supplier_contact()
    {
        $supplier = $this->supplierFixture(); $item = $this->purchase(3);
        $item->supplier_id = $supplier->id; $item->stock_alert_quantity = 1; $item->save();
        $stockItems = fn ($response) => collect($response->json('items'))->where('type', 'stock');
        $this->assertCount(0, $stockItems($this->get(route('appointments.notifications'))->assertOk()));
        $invoice = $this->invoice(); $line = $this->stock->add($invoice, ['item'=>$item->id, 'quantity'=>2]);
        $stock = $stockItems($this->get(route('appointments.notifications'))->assertOk());
        $this->assertCount(1, $stock);
        $this->assertEquals(route('supplier.show', $supplier->id), $stock->first()['actions'][1]['url']);
        $this->stock->remove($this->owner->id, $invoice->id, $line->id);
        $this->assertCount(0, $stockItems($this->get(route('appointments.notifications'))->assertOk()));
        $item->refresh(); $item->quantity = 0; $item->stock_alert_quantity = null; $item->save();
        $this->assertCount(0, $stockItems($this->get(route('appointments.notifications'))->assertOk()));
        $item->stock_alert_quantity = 0; $item->save();
        $this->assertCount(1, $stockItems($this->get(route('appointments.notifications'))->assertOk()));
        $item->parent_id = 999; $item->save();
        $this->assertCount(0, $stockItems($this->get(route('appointments.notifications'))->assertOk()));
    }

    public function test_purchase_is_idempotent_and_history_survives_deletions()
    {
        $supplier = $this->supplierFixture(); $item = $this->purchase(2);
        $data = ['quantity'=>3, 'unit_price'=>'210.50', 'purchase_date'=>now()->toDateString(),
            'supplier_id'=>$supplier->id, 'notes'=>'Yeni yağ alımı', 'request_key'=>(string)\Illuminate\Support\Str::uuid()];
        $this->get('/item/'.$item->id.'/purchase')->assertOk()->assertSee('Satın alımı kaydet');
        for ($i=0; $i<2; $i++) $this->post('/item/'.$item->id.'/purchase', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertEquals(5, $item->fresh()->quantity);
        $this->assertSame(2, Expense::count());
        $this->assertEquals(1031.50, Expense::sum('amount'));
        $history = \App\Models\ItemPurchase::where('request_key', $data['request_key'])->firstOrFail();
        $this->assertEquals(631.50, $history->amount);
        $this->assertSame('Yağ tedarikçisi', $history->supplier_name);
        $this->post('/item/'.$item->id.'/purchase', array_replace($data, ['quantity'=>4]))->assertSessionHasErrors('item');
        $this->assertEquals(5, $item->fresh()->quantity);
        $invoice = $this->invoice();
        $line = $this->stock->add($invoice, ['item'=>$item->id, 'quantity'=>1]);
        $supplier->company_name = 'Değişti'; $supplier->save();
        $this->delete('/supplier/'.$supplier->id)->assertRedirect();
        $this->assertNull($item->fresh()->supplier_id);
        $this->delete('/item/'.$item->id)->assertRedirect();
        $this->get('/item-purchases')->assertOk()->assertSee('Yağ tedarikçisi')->assertSee('Castrol motor yağı')->assertSee('Yeni yağ alımı');
        $this->assertNotNull($history->fresh());
        $this->stock->remove($this->owner->id, $invoice->id, $line->id);
        $restored = Item::where('inventory_key', $item->inventory_key)->firstOrFail();
        $this->assertNull($restored->supplier_id);
        $this->assertEquals(1, $restored->quantity);
        $this->assertSame(2, \App\Models\ItemPurchase::count());
    }

    public function test_purchase_history_excludes_returns_and_prunes_without_changing_finance()
    {
        $item = $this->purchase(5);
        $this->assertSame(1, \App\Models\ItemPurchase::count());
        $this->stock->savePurchase($this->owner->id, $this->attributes(3), $item->id);
        $invoice = $this->invoice(); $line = $this->stock->add($invoice, ['item'=>$item->id, 'quantity'=>1]);
        $this->stock->remove($this->owner->id, $invoice->id, $line->id);
        $this->assertSame(1, \App\Models\ItemPurchase::count());
        $this->stock->savePurchase($this->owner->id, $this->attributes(4), $item->id);
        $old = \App\Models\ItemPurchase::firstOrFail();
        $old->created_at = now()->subYears(2)->subDay(); $old->save();
        $this->get('/item-purchases')->assertOk()->assertViewHas('purchases', fn ($p) => $p->total() === 1);
        $before = Expense::sum('amount'); $movements = DB::table('stock_movements')->count();
        $this->artisan('inventory:purge-purchases')->assertSuccessful();
        $this->assertSame(1, \App\Models\ItemPurchase::count());
        $this->assertEquals($before, Expense::sum('amount'));
        $this->assertSame($movements, DB::table('stock_movements')->count());
        $this->assertEquals(4, $item->fresh()->quantity);
    }

    public function test_supplier_purchase_permissions_and_tenant_scoping()
    {
        $foreign = $this->supplierFixture(999); $item = $this->purchase(3);
        $data = ['quantity'=>1, 'unit_price'=>10, 'purchase_date'=>now()->toDateString(), 'request_key'=>(string)\Illuminate\Support\Str::uuid()];
        $this->post('/item/'.$item->id.'/purchase', $data + ['supplier_id'=>$foreign->id])->assertSessionHasErrors('supplier_id');
        $history = \App\Models\ItemPurchase::where('item_id', $item->id)->firstOrFail();
        $history->parent_id = 999; $history->item_title = 'Yabancı alım'; $history->save();
        $this->get('/item-purchases')->assertOk()->assertDontSee('Yabancı alım');
        $item->parent_id = 999; $item->save();
        $this->get('/item/'.$item->id.'/purchase')->assertNotFound();
        $this->post('/item/'.$item->id.'/purchase', $data)->assertNotFound();
        $employee = User::create(['name'=>'Personel', 'email'=>'supplier-access@example.test', 'password'=>bcrypt('test'), 'type'=>'employee', 'parent_id'=>$this->owner->id]);
        $this->actingAs($employee);
        $this->get('/supplier')->assertForbidden();
        $this->get('/item-purchases')->assertForbidden();
        $this->get('/item/'.$item->id.'/purchase')->assertForbidden();
        $this->post('/item/'.$item->id.'/purchase', $data)->assertForbidden();
    }

    private function invoice(): Invoice
    {
        return Invoice::create(['parent_id' => $this->owner->id, 'client' => $this->owner->id, 'invoice_id' => 1, 'invoice_date' => '2026-09-23', 'status' => 0]);
    }

    public function test_invoice_copies_selected_customer_and_keeps_optional_overrides_on_the_invoice()
    {
        $client = User::create(['name'=>'Kayıtlı Müşteri','email'=>'customer@example.test','phone_number'=>'+905321234567','password'=>bcrypt('test'),'type'=>'client','parent_id'=>$this->owner->id]);
        \App\Models\Client::create(['user_id'=>$client->id,'parent_id'=>$this->owner->id,'address'=>'Sanayi sitesi','city'=>'İstanbul','state'=>'Kartal','zip_code'=>'34860']);
        $this->get('/invoice-customer/'.$client->id.'/billing')->assertOk()->assertJsonFragment(['name'=>'Kayıtlı Müşteri','phone'=>'5321234567','district'=>'Kartal','postcode'=>'34860']);
        $service = Service::create(['parent_id'=>$this->owner->id,'client'=>$client->id]);
        $data = ['invoice_date'=>now()->toDateString(),'client'=>$client->id,'service'=>$service->id,'types'=>[], 'billing'=>['name'=>'','tax_office'=>'Örnek vergi dairesi']];
        $this->postInvoice('/invoice',$data)->assertRedirect()->assertSessionHasNoErrors();
        $invoice = Invoice::firstOrFail();
        $this->assertSame('Kayıtlı Müşteri',$invoice->customer_details['name']);
        $this->assertSame('customer@example.test',$invoice->customer_details['email']);
        $this->assertSame('Sanayi sitesi',$invoice->customer_details['address']);
        $this->assertSame('Örnek vergi dairesi',$invoice->customer_details['tax_office']);
        $this->putInvoice('/invoice/'.$invoice->id.'/billing',['billing'=>['name'=>'Faturaya özel ad','address'=>'Faturaya özel adres']])->assertRedirect();
        $this->assertSame('Kayıtlı Müşteri',$client->fresh()->name);
        $this->get('/invoice/'.$invoice->id.'/billing')->assertOk()->assertSee('Faturaya özel ad');
        $other = User::create(['name'=>'İkinci Müşteri','email'=>'second@example.test','password'=>bcrypt('test'),'type'=>'client','parent_id'=>$this->owner->id]);
        $secondService = Service::create(['parent_id'=>$this->owner->id,'client'=>$other->id]);
        unset($data['billing']);
        $data['client']=$other->id; $data['service']=$secondService->id;
        $this->putInvoice('/invoice/'.encrypt($invoice->id),$data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('İkinci Müşteri',$invoice->fresh()->customer_details['name']);
        $this->assertSame('',$invoice->fresh()->customer_details['address']);
        $client->parent_id=999; $client->save();
        $this->get('/invoice-customer/'.$client->id.'/billing')->assertNotFound();
        $this->assertSame([],\App\Services\InvoiceBilling::customer($this->owner->id,$client->id));
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
        $this->postInvoice('/invoice/' . $invoice->id . '/item/store', ['item' => $foreign->id, 'quantity' => 1])->assertSessionHasErrors('item');
        $this->assertSame(5, (int) $foreign->fresh()->quantity);
        $foreignInvoice = Invoice::create(['parent_id' => $other->id, 'client' => $other->id, 'invoice_id' => 1]);
        $foreignLine = $this->stock->add($foreignInvoice, ['item' => $foreign->id, 'quantity' => 1]);
        $this->postInvoice('/invoice/' . $foreignInvoice->id . '/item/' . $foreignLine->id . '/store')->assertNotFound();
        $this->postInvoice('/invoice/item/destroy', ['id' => $foreignLine->id])->assertNotFound();
        $this->assertSame(4, (int) $foreign->fresh()->quantity);
        $this->assertNotNull($foreignLine->fresh());
    }

    public function test_failed_invoice_creation_leaves_no_invoice_or_sale()
    {
        $item = $this->purchase(1);
        $service = Service::create(['parent_id' => $this->owner->id, 'client' => $this->owner->id]);
        $data = ['invoice_date' => '2026-09-23', 'client' => $this->owner->id, 'service' => $service->id,
            'item' => [$item->id], 'quantity' => [2], 'types' => []];
        $this->postInvoice('/invoice', $data)->assertSessionHasErrors('item');
        $this->assertSame(0, Invoice::count());
        $this->assertSame(1, (int) $item->fresh()->quantity);
        $this->assertSame(0, InvoiceItem::count());
    }

    public function test_deleting_invoice_restores_all_product_quantities()
    {
        $item = $this->purchase(5);
        $invoice = $this->invoice();
        $this->stock->add($invoice, ['item' => $item->id, 'quantity' => 3]);
        $this->deleteInvoice('/invoice/' . $invoice->id, ['return_stock'=>1])->assertRedirect(route('invoice.index'));
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

    public function test_external_labor_tax_is_snapshotted_in_invoice_and_portal_and_reversed_on_paid_reduction()
    {
        \Illuminate\Support\Facades\Schema::table('service_items', fn ($table) => $table->string('tax')->nullable());
        $tax = \App\Models\Tax::forceCreate(['parent_id' => $this->owner->id, 'title' => 'KDV %20', 'rate' => 20]);
        $foreign = \App\Models\Tax::forceCreate(['parent_id' => 999, 'title' => 'Foreign', 'rate' => 10]);
        $vehicle = Vehicle::create(['parent_id' => $this->owner->id, 'client' => $this->owner->id, 'license_plate' => '34 TAX 01']);
        $data = ['client' => $this->owner->id, 'vehicle' => $vehicle->id, 'assign' => $this->owner->id,
            'status' => 'scheduled', 'types' => [], 'external_labor_amount' => '1000', 'external_labor_tax_id' => $tax->id];
        $this->post('/service', array_replace($data, ['external_labor_tax_id' => $foreign->id]))->assertSessionHas('error');
        $this->assertSame(0, Service::count());
        $this->post('/service', $data)->assertRedirect()->assertSessionMissing('error');
        $service = Service::firstOrFail(); $invoice = Invoice::firstOrFail();
        $this->assertEquals(200, $service->getServiceTotalTaxAmount());
        $this->assertEquals(1200, $invoice->getInvoiceAllTotalAmount());
        $this->assertEquals(1200, $invoice->getInvoiceTotalDueAmount());
        $this->assertSame(0, $invoice->payments()->count());
        $this->get('/service/' . encrypt($service->id) . '/edit')->assertOk()->assertSee('external_labor_tax_id');
        $this->get('/invoice/' . encrypt($invoice->id))->assertOk()->assertSee('Harici işçilik vergisi')->assertSee('KDV %20');
        $pool = app(\App\Services\VehicleQrPool::class); $pool->replenish($this->owner->id);
        $code = \App\Models\VehicleQrCode::available()->first();
        $pool->markPrinted($this->owner->id, [$code->id]); $pool->assignExisting($this->owner->id, $vehicle->id, $code->id);
        $tax->rate = 10; $tax->save();
        $this->get('/q/' . $code->token . '/invoice/' . $invoice->id)->assertOk()->assertViewHas('total', 1200);
        $this->postInvoice('/invoice', ['invoice_date' => now()->toDateString(), 'client' => $this->owner->id, 'service' => $service->id, 'types' => []])->assertRedirect()->assertSessionMissing('error');
        $this->assertEquals(1200, Invoice::latest('id')->first()->getInvoiceAllTotalAmount());
        DB::table('invoice_payments')->insert(['invoice_id' => $invoice->id, 'parent_id' => $this->owner->id, 'amount' => 1200, 'payment_date' => now()->toDateString()]);
        $this->put('/service/' . $service->id, array_replace($data, ['external_labor_tax_id' => $foreign->id]))->assertSessionHas('error');
        $this->assertEquals(1200, $invoice->fresh()->getInvoiceAllTotalAmount());
        $tax->delete();
        $this->get('/q/' . $code->token . '/invoice/' . $invoice->id)->assertOk()->assertViewHas('total', 1200);
        $this->put('/service/' . $service->id, array_replace($data, ['external_labor_tax_id' => '']))->assertRedirect()->assertSessionMissing('error');
        $this->assertEquals(1000, $invoice->fresh()->getInvoiceAllTotalAmount());
        $this->assertEquals(1000, $invoice->payments()->sum('amount'));
        $this->assertEquals(0, $invoice->fresh()->getInvoiceTotalDueAmount());
        $report = app(\App\Http\Controllers\ReportController::class)->incomeByMonth(now()->year);
        $this->assertEquals(1000, $report['income'][now()->month - 1]);
        $this->put('/service/' . $service->id, array_replace($data, ['external_labor_tax_id' => '']))->assertRedirect();
        $this->assertEquals(1000, $invoice->payments()->sum('amount'));
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
        $this->postInvoice('/invoice', $data)->assertRedirect()->assertSessionMissing('error');
        $invoice = Invoice::firstOrFail();
        $this->assertEquals(250.50, $invoice->getInvoiceAllTotalAmount());
        $this->putInvoice('/invoice/' . encrypt($invoice->id), $data)->assertRedirect()->assertSessionMissing('error');
        $this->assertEquals(250.50, $invoice->fresh()->getInvoiceAllTotalAmount());
        $this->assertSame(0, $invoice->payments()->count());
        $this->get('/invoice/' . encrypt($invoice->id) . '/edit')->assertOk()->assertSee('250.50');
        $this->get(route('client.service', $this->owner->id))->assertJsonFragment(['external_labor_amount' => '250.50']);
        $other = Service::create(['parent_id' => $this->owner->id + 100, 'client' => $this->owner->id, 'external_labor_amount' => 777]);
        $this->postInvoice('/invoice', array_merge($data, ['service' => $other->id]))->assertSessionHas('error');
        $this->assertSame(1, Invoice::count());
        // Reducing labor while adding a more expensive product must not reverse income mid-update.
        $item = $this->purchase();
        $replacement = Service::create(['parent_id' => $this->owner->id, 'client' => $this->owner->id, 'vehicle' => $vehicle->id]);
        DB::table('invoice_payments')->insert(['invoice_id' => $invoice->id, 'parent_id' => $this->owner->id,
            'amount' => 250.50, 'payment_date' => now()->toDateString()]);
        $this->putInvoice('/invoice/' . encrypt($invoice->id), array_merge($data, ['service' => $replacement->id,
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
        $this->postInvoice('/invoice', $data)->assertRedirect()->assertSessionHasNoErrors();
        $invoice = Invoice::firstOrFail();
        $line = $invoice->items()->firstOrFail();
        $this->assertSame(3, (int) $item->fresh()->quantity);
        $this->assertEquals(300, $line->amount);
        $this->assertEquals(0, $invoice->payments()->sum('amount'));
        $this->get('/invoice/' . encrypt($invoice->id) . '/edit')->assertOk()->assertSee('item_id[0]', false);
        DB::table('invoice_payments')->insert(['invoice_id' => $invoice->id, 'parent_id' => $this->owner->id, 'amount' => 100, 'payment_date' => '2026-09-23']);
        $data['item_id'] = [$line->id];
        $data['quantity'] = [1];
        $this->putInvoice('/invoice/' . encrypt($invoice->id), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(4, (int) $item->fresh()->quantity);
        $this->assertEquals(100, $invoice->payments()->sum('amount'));
        $this->assertSame(1, (int) $invoice->fresh()->status);
        $this->postInvoice('/invoice/' . $invoice->id . '/item/store', ['item' => $item->id, 'quantity' => 1])->assertRedirect();
        $added = $invoice->items()->latest('id')->first();
        $this->postInvoice('/invoice/' . $invoice->id . '/item/' . $added->id . '/store')->assertRedirect();
        $this->assertSame(4, (int) $item->fresh()->quantity);
        $this->postInvoice('/invoice/item/destroy', ['id' => $line->id])->assertRedirect();
        $this->assertSame(5, (int) $item->fresh()->quantity);
    }

    public function test_invoice_discount_updates_totals_portal_and_paid_income_without_stock_changes()
    {
        $item = $this->purchase(5);
        $vehicle = Vehicle::create(['parent_id' => $this->owner->id, 'client' => $this->owner->id, 'license_plate' => '34 IND 01']);
        $service = Service::create(['parent_id' => $this->owner->id, 'vehicle' => $vehicle->id, 'client' => $this->owner->id, 'external_labor_amount' => 100]);
        $data = ['invoice_date' => now()->toDateString(), 'client' => $this->owner->id, 'service' => $service->id,
            'item' => [$item->id], 'quantity' => [2], 'types' => [], 'discount_amount' => '50.25'];
        $this->postInvoice('/invoice', $data)->assertRedirect()->assertSessionMissing('error');
        $invoice = Invoice::firstOrFail();
        $this->assertEquals(700, $invoice->getInvoiceGrossAmount());
        $this->assertEquals(649.75, $invoice->getInvoiceAllTotalAmount());
        $this->assertEquals(649.75, $invoice->getInvoiceTotalDueAmount());
        $this->assertSame(0, $invoice->payments()->count());
        $this->get('/invoice/'.encrypt($invoice->id).'/edit')->assertOk()->assertSee('name="discount_amount"', false)->assertSee('50.25');
        $this->get('/invoice/'.encrypt($invoice->id))->assertOk()->assertSee('İndirim')->assertSee('649,75');
        $pool = app(\App\Services\VehicleQrPool::class);
        $pool->replenish($this->owner->id);
        $code = \App\Models\VehicleQrCode::available()->first();
        $pool->markPrinted($this->owner->id, [$code->id]);
        $pool->assignExisting($this->owner->id, $vehicle->id, $code->id);
        $this->get('/q/'.$code->token.'/invoice/'.$invoice->id)->assertOk()->assertSee('İndirim')->assertViewHas('total', 649.75)->assertViewHas('discount', 50.25);
        DB::table('invoice_payments')->insert(['invoice_id' => $invoice->id, 'parent_id' => $this->owner->id, 'amount' => 649.75, 'payment_date' => now()->toDateString()]);
        $data['item_id'] = [$invoice->items()->first()->id];
        $data['discount_amount'] = '100.50';
        for ($i = 0; $i < 2; $i++) $this->putInvoice('/invoice/'.encrypt($invoice->id), $data)->assertRedirect()->assertSessionMissing('error');
        $this->assertEquals(599.50, $invoice->fresh()->getInvoiceAllTotalAmount());
        $this->assertEquals(599.50, $invoice->payments()->sum('amount'));
        $this->assertSame(2, $invoice->payments()->count());
        $this->assertSame(3, (int) $item->fresh()->quantity);
        foreach (['-1', 'abc', '1.999'] as $invalid) {
            $this->putInvoice('/invoice/'.encrypt($invoice->id), array_replace($data, ['discount_amount' => $invalid]))->assertSessionHas('error');
            $this->assertEquals(100.50, $invoice->fresh()->discount_amount);
        }
        $this->putInvoice('/invoice/'.encrypt($invoice->id), array_replace($data, ['discount_amount' => '']))->assertRedirect()->assertSessionMissing('error');
        $this->assertEquals(0, $invoice->fresh()->discount_amount);
        $this->assertEquals(100.50, $invoice->fresh()->getInvoiceTotalDueAmount());
        $this->assertEquals(599.50, $invoice->payments()->sum('amount'));
        $this->putInvoice('/invoice/'.encrypt($invoice->id), array_replace($data, ['discount_amount' => '1000']))->assertRedirect()->assertSessionMissing('error');
        $this->assertEquals(700, $invoice->fresh()->getInvoiceDiscountAmount());
        $this->assertEquals(0, $invoice->fresh()->getInvoiceAllTotalAmount());
        $this->assertEquals(0, $invoice->payments()->sum('amount'));
    }
    public function test_debt_tracking_uses_actual_balance_and_excludes_other_shops_and_settled_invoices()
    {
        $unpaid = $this->invoice();
        $unpaid->update(['external_labor_amount'=>100, 'external_labor_tax_rate'=>20, 'discount_amount'=>10, 'status'=>2]);
        $partial = $this->invoice();
        $partial->update(['external_labor_amount'=>200]);
        DB::table('invoice_payments')->insert(['invoice_id'=>$partial->id, 'parent_id'=>$this->owner->id, 'amount'=>50]);
        $paid = $this->invoice();
        $paid->update(['external_labor_amount'=>300]);
        DB::table('invoice_payments')->insert(['invoice_id'=>$paid->id, 'parent_id'=>$this->owner->id, 'amount'=>300]);
        $foreign = $this->invoice();
        $foreign->update(['external_labor_amount'=>9999, 'parent_id'=>999]);
        $this->invoice(); // Zero total is not a debt.
        $response = $this->get(route('invoice.debts'))->assertOk()->assertViewHas('totalDue', 260);
        $response->assertViewHas('debts', function ($rows) use ($unpaid, $partial) {
            return $rows->count() === 2 && $rows->pluck('invoice.id')->all() === [$unpaid->id, $partial->id]
                && $rows[0]['due'] == 110 && $rows[1]['paid'] == 50 && $rows[1]['due'] == 150;
        });
        $response->assertSee('Borç takibi')->assertSee('Ödeme ekle')->assertSee(route('invoice.payment', $partial->id), false);
        DB::table('invoice_payments')->insert(['invoice_id'=>$partial->id, 'parent_id'=>$this->owner->id, 'amount'=>150]);
        $this->get(route('invoice.debts'))->assertOk()->assertViewHas('totalDue', 110)
            ->assertViewHas('debts', fn ($rows) => $rows->count() === 1);
    }

    public function test_debt_tracking_shows_empty_state_and_preserved_customer_details()
    {
        $this->get(route('invoice.debts'))->assertOk()->assertSee('Ödemesi beklenen fatura bulunmuyor.');
        $invoice = $this->invoice();
        $invoice->external_labor_amount = 100;
        $invoice->client = 999999;
        $invoice->customer_details = ['name'=>'Arşivlenmiş müşteri'];
        $invoice->save();
        $this->get(route('invoice.debts'))->assertOk()->assertSee('Arşivlenmiş müşteri');
    }

    public function test_debt_tracking_requires_invoice_permission_and_rejects_customer_accounts()
    {
        $employee = User::create(['name'=>'Personel', 'email'=>'debt-access@example.test', 'password'=>bcrypt('test'), 'type'=>'employee', 'parent_id'=>$this->owner->id]);
        $this->actingAs($employee);
        $this->get(route('invoice.debts'))->assertForbidden();
        $employee->type = 'client';
        $employee->save();
        Gate::before(fn () => true);
        $this->actingAs($employee);
        $this->get(route('invoice.debts'))->assertForbidden();
    }

    public function test_service_mileage_defaults_are_returned_for_only_the_current_shop()
    {
        $vehicle = Vehicle::create(['parent_id'=>$this->owner->id, 'client'=>$this->owner->id, 'mileage'=>125000, 'license_plate'=>'34 KM 01']);
        Vehicle::create(['parent_id'=>999, 'client'=>$this->owner->id, 'mileage'=>999, 'license_plate'=>'34 KM 99']);
        $this->get(route('client.vehicle', $this->owner->id))->assertOk()->assertJsonCount(1)
            ->assertJsonPath('0.id', $vehicle->id)->assertJsonPath('0.mileage', 125000);
        $this->get('/service/create')->assertOk()->assertSee('Güncel araç kilometresi')->assertSee('data-mileage', false);
    }

    public function test_service_creation_and_edit_update_vehicle_mileage_and_blank_preserves_it()
    {
        $vehicle = Vehicle::create(['parent_id'=>$this->owner->id, 'client'=>$this->owner->id, 'mileage'=>125000, 'license_plate'=>'34 KM 02']);
        $data = ['client'=>$this->owner->id, 'vehicle'=>$vehicle->id, 'assign'=>$this->owner->id,
            'service_date'=>now()->toDateString(), 'status'=>'scheduled', 'types'=>[], 'mileage'=>'126000'];
        $this->post('/service', $data)->assertRedirect()->assertSessionMissing('error');
        $service = Service::firstOrFail();
        $this->assertEquals(126000, $vehicle->fresh()->mileage);
        $this->get('/service/'.encrypt($service->id).'/edit')->assertOk()->assertSee('Güncel araç kilometresi');
        $data['mileage'] = '127000';
        $this->put('/service/'.$service->id, $data)->assertRedirect()->assertSessionMissing('error');
        $this->assertEquals(127000, $vehicle->fresh()->mileage);
        $data['mileage'] = '';
        $this->put('/service/'.$service->id, $data)->assertRedirect()->assertSessionMissing('error');
        $this->assertEquals(127000, $vehicle->fresh()->mileage);
        $data['mileage'] = '0';
        $this->put('/service/'.$service->id, $data)->assertRedirect()->assertSessionMissing('error');
        $this->assertEquals(0, $vehicle->fresh()->mileage);
    }

    public function test_service_mileage_rejects_invalid_values_and_foreign_or_mismatched_vehicles()
    {
        $vehicle = Vehicle::create(['parent_id'=>$this->owner->id, 'client'=>$this->owner->id, 'mileage'=>100, 'license_plate'=>'34 KM 03']);
        $data = ['client'=>$this->owner->id, 'vehicle'=>$vehicle->id, 'assign'=>$this->owner->id,
            'service_date'=>now()->toDateString(), 'status'=>'scheduled', 'types'=>[], 'mileage'=>'200'];
        foreach (['-1', '1.5', 'abc', '2147483648'] as $invalid) {
            $this->post('/service', array_replace($data, ['mileage'=>$invalid]))->assertSessionHas('error');
        }
        $this->post('/service', array_replace($data, ['client'=>999]))->assertSessionHas('error');
        $vehicle->parent_id = 999; $vehicle->save();
        $this->post('/service', $data)->assertSessionHas('error');
        $this->assertEquals(100, $vehicle->fresh()->mileage);
        $this->assertSame(0, Service::count());
    }

}
