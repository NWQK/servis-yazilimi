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
        $data = ['invoice_date' => '2026-09-23', 'client' => $this->owner->id, 'service' => 1,
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
        $this->assertEquals(150, $invoice->payments()->sum('amount')); // No automatic refund or duplicate revenue.
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
