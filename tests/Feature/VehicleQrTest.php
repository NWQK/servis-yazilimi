<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Service;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleQrCode;
use App\Services\VehicleQrPool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VehicleQrTest extends TestCase
{
    private $owner;
    private $pool;

    protected function setUp(): void
    {
        parent::setUp();
        // Never use the checked-in application's database credentials for tests.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'session.driver' => 'array', 'app.url' => 'https://service.example.test']);
        DB::purge('sqlite');
        $paths = [];
        foreach (glob(database_path('migrations/*.php')) as $path) {
            // The legacy 1.7 migration contains MySQL-only ALTER CHANGE statements.
            // QR tests use the actual base schemas and the new QR migration on SQLite.
            if (str_contains($path, 'version_1_7_filled')) {
                continue;
            }
            $paths[] = $path;
        }
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertExitCode(0);
        \Illuminate\Support\Facades\Schema::table('service_items', function ($table) {
            $table->string('tax')->nullable();
        });
        $this->owner = $this->owner('owner@example.test');
        DB::table('settings')->insert(['parent_id' => 1, 'name' => 'pricing_feature', 'value' => 'off']);
        $this->pool = app(VehicleQrPool::class);
        Gate::before(fn ($user) => $user->type === 'owner' ? true : null);
    }

    private function owner($email)
    {
        return User::create(['name' => 'Test Servis', 'email' => $email, 'password' => bcrypt('test-password'), 'type' => 'owner', 'lang' => 'tr']);
    }

    private function ready()
    {
        $this->pool->replenish($this->owner->id);
        $code = VehicleQrCode::where('parent_id', $this->owner->id)->available()->first();
        $this->pool->markPrinted($this->owner->id, [$code->id]);
        return $code->fresh();
    }

    private function vehicle($owner = null)
    {
        return Vehicle::create(['parent_id' => ($owner ?? $this->owner)->id, 'license_plate' => '34 QR 123', 'model' => 'Test Araç']);
    }

    private function assigned()
    {
        $code = $this->ready();
        $vehicle = $this->vehicle();
        $this->pool->assignExisting($this->owner->id, $vehicle->id, $code->id);
        return [$code->fresh(), $vehicle];
    }

    private function expectValidation(callable $action)
    {
        try {
            $action();
            $this->fail('Expected invalid QR selection to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }
    }

    public function test_pool_initialization_is_idempotent_and_unique()
    {
        $this->pool->replenish($this->owner->id);
        $this->pool->replenish($this->owner->id);
        $this->assertSame(10, VehicleQrCode::available()->count());
        $this->assertSame(10, VehicleQrCode::distinct()->count('token'));
        $this->assertSame(0, VehicleQrCode::whereNotNull('printed_at')->count());
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', VehicleQrCode::first()->token);
    }

    public function test_vehicle_creation_assigns_selected_label_and_replaces_stock_atomically()
    {
        $code = $this->ready();
        $vehicle = $this->pool->createVehicle($this->owner->id, $code->id, fn () => $this->vehicle());
        $this->assertSame($vehicle->id, $code->fresh()->vehicle_id);
        $this->assertSame(10, VehicleQrCode::available()->count());
        $this->assertSame(11, VehicleQrCode::count());
        $this->assertNull(VehicleQrCode::latest('id')->first()->printed_at);
        $this->assertSame($code->token, $vehicle->qrCode->token);
    }

    public function test_failed_vehicle_creation_rolls_back_everything()
    {
        $code = $this->ready();
        try {
            $this->pool->createVehicle($this->owner->id, $code->id, function () {
                $this->vehicle();
                throw new \RuntimeException('Simulated save failure');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated save failure', $e->getMessage());
        }
        $this->assertSame(0, Vehicle::count());
        $this->assertSame(10, VehicleQrCode::available()->count());
        $this->assertNull($code->fresh()->assigned_at);
    }

    public function test_unprinted_used_and_other_tenant_codes_cannot_be_assigned()
    {
        $this->pool->replenish($this->owner->id);
        $code = VehicleQrCode::first();
        $this->expectValidation(fn () => $this->pool->createVehicle($this->owner->id, $code->id, fn () => $this->vehicle()));
        $code = $this->ready();
        $other = $this->owner('other@example.test');
        $this->expectValidation(fn () => $this->pool->createVehicle($other->id, $code->id, fn () => $this->vehicle($other)));
        $this->pool->createVehicle($this->owner->id, $code->id, fn () => $this->vehicle());
        $this->expectValidation(fn () => $this->pool->createVehicle($this->owner->id, $code->id, fn () => $this->vehicle()));
        $this->assertSame(1, Vehicle::count());
        $this->assertSame(10, VehicleQrCode::available()->count());
    }

    public function test_existing_vehicle_cannot_receive_a_second_label()
    {
        [$code, $vehicle] = $this->assigned();
        $second = $this->ready();
        $this->expectValidation(fn () => $this->pool->assignExisting($this->owner->id, $vehicle->id, $second->id));
        $this->assertSame($code->id, $vehicle->qrCode->id);
    }

    public function test_deleted_vehicle_label_is_never_reused()
    {
        [$code, $vehicle] = $this->assigned();
        $vehicle->delete();
        $this->assertNull($code->fresh()->vehicle_id);
        $this->assertNotNull($code->fresh()->assigned_at);
        $this->assertSame(10, VehicleQrCode::available()->count());
        $this->get('/q/' . $code->token)->assertNotFound();
        $this->expectValidation(fn () => $this->pool->createVehicle($this->owner->id, $code->id, fn () => $this->vehicle()));
    }

    public function test_empty_and_invalid_labels_do_not_expose_vehicle_data()
    {
        $code = $this->ready();
        $this->get('/q/' . $code->token)->assertOk()->assertSee('henüz bir araca atanmamış')->assertDontSee('34 QR 123');
        $this->get('/q/' . str_repeat('f', 64))->assertNotFound();
        $this->get('/q/1')->assertNotFound();
    }

    public function test_portal_is_guest_accessible_and_scoped_to_vehicle_and_tenant()
    {
        [$code, $vehicle] = $this->assigned();
        $service = Service::create(['vehicle' => $vehicle->id, 'parent_id' => $this->owner->id, 'notes' => 'Görünen servis', 'service_id' => 42]);
        $invoice = Invoice::create(['parent_id' => $this->owner->id, 'service' => $service->id, 'invoice_id' => 85]);
        $otherVehicle = $this->vehicle();
        $hidden = Service::create(['vehicle' => $otherVehicle->id, 'parent_id' => $this->owner->id, 'notes' => 'OTHER_VEHICLE_SECRET']);
        $hiddenInvoice = Invoice::create(['parent_id' => $this->owner->id, 'service' => $hidden->id, 'invoice_id' => 999]);
        $other = $this->owner('other@example.test');
        Service::create(['vehicle' => $vehicle->id, 'parent_id' => $other->id, 'notes' => 'OTHER_TENANT_SECRET']);
        $foreignInvoice = Invoice::create(['parent_id' => $other->id, 'service' => $service->id, 'invoice_id' => 888]);
        $this->get('/q/' . $code->token)->assertOk()->assertSee('Görünen servis')->assertSee('#INV-85')
            ->assertDontSee('OTHER_VEHICLE_SECRET')->assertDontSee('OTHER_TENANT_SECRET')->assertDontSee('#INV-999')->assertDontSee('#INV-888')
            ->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
        $this->get('/q/' . $code->token . '/invoice/' . $invoice->id)->assertOk();
        $this->get('/q/' . $code->token . '/invoice/' . $hiddenInvoice->id)->assertNotFound();
        $this->get('/q/' . $code->token . '/invoice/' . $foreignInvoice->id)->assertNotFound();
    }

    public function test_invoice_details_include_tax_payments_and_escape_notes()
    {
        [$code, $vehicle] = $this->assigned();
        $service = Service::create(['vehicle' => $vehicle->id, 'parent_id' => $this->owner->id]);
        $invoice = Invoice::create(['parent_id' => $this->owner->id, 'service' => $service->id]);
        $taxId = DB::table('taxes')->insertGetId(['title' => 'KDV', 'rate' => 20, 'parent_id' => $this->owner->id]);
        DB::table('invoice_items')->insert(['invoice_id' => $invoice->id, 'parent_id' => $this->owner->id, 'quantity' => 2, 'amount' => 100, 'tax' => (string) $taxId, 'description' => '<script>alert(1)</script>']);
        DB::table('invoice_services')->insert(['invoice_id' => $invoice->id, 'parent_id' => $this->owner->id, 'rate' => 50, 'tax' => (string) $taxId]);
        DB::table('invoice_payments')->insert(['invoice_id' => $invoice->id, 'parent_id' => $this->owner->id, 'amount' => 100]);
        $this->get('/q/' . $code->token . '/invoice/' . $invoice->id)->assertOk()->assertSee('300,00')->assertSee('200,00')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_all_history_is_accessible_through_pagination()
    {
        [$code, $vehicle] = $this->assigned();
        for ($i = 1; $i <= 12; $i++) {
            $service = Service::create(['vehicle' => $vehicle->id, 'parent_id' => $this->owner->id, 'service_id' => $i]);
            Invoice::create(['parent_id' => $this->owner->id, 'service' => $service->id, 'invoice_id' => $i]);
        }
        $this->get('/q/' . $code->token)->assertOk()->assertSee('Servisler (12)')->assertSee('Faturalar (12)');
        $this->get('/q/' . $code->token . '?services_page=2&invoices_page=2')->assertOk()->assertSee('#SER-1')->assertSee('#INV-1');
    }

    public function test_guest_and_client_cannot_manage_qr_stock()
    {
        $this->get('/vehicle-qr')->assertRedirect('/login');
        $client = User::create(['name' => 'Client', 'email' => 'client@example.test', 'password' => bcrypt('test'), 'type' => 'client', 'parent_id' => $this->owner->id, 'lang' => 'tr']);
        $this->actingAs($client)->get('/vehicle-qr')->assertForbidden();
        $this->post('/vehicle-qr/printed', ['ids' => [1]])->assertForbidden();
        $this->get('/vehicle/create')->assertForbidden();
    }

    public function test_print_does_not_mark_labels_printed_and_uses_canonical_url()
    {
        $code = $this->ready();
        $code->update(['printed_at' => null]);
        $response = $this->actingAs($this->owner)->get('/vehicle-qr/print?ids[]=' . $code->id);
        $response->assertOk()->assertSee('data:image/svg+xml;base64,', false)->assertSee($code->label);
        $this->assertNull($code->fresh()->printed_at);
        $this->assertSame('https://service.example.test/q/' . $code->token, $code->publicUrl());
        $this->post('/vehicle-qr/printed', ['ids' => [$code->id]])->assertRedirect();
        $this->assertNotNull($code->fresh()->printed_at);
    }

    public function test_foreign_labels_cannot_be_printed_or_marked_printed()
    {
        $code = $this->ready();
        $other = $this->owner('other@example.test');
        $this->actingAs($other)->get('/vehicle-qr/print?ids[]=' . $code->id)->assertNotFound();
        $this->post('/vehicle-qr/printed', ['ids' => [$code->id]])->assertSessionHasErrors('ids');
    }

    public function test_vehicle_store_uses_qr_workflow()
    {
        $code = $this->ready();
        $client = User::create(['name' => 'Client', 'email' => 'client@example.test', 'password' => bcrypt('test'), 'type' => 'client', 'parent_id' => $this->owner->id]);
        $type = DB::table('vehicle_types')->insertGetId(['type' => 'Otomobil', 'parent_id' => $this->owner->id]);
        $brand = DB::table('vehicle_brands')->insertGetId(['name' => 'Test', 'type' => $type, 'parent_id' => $this->owner->id]);
        $data = ['client' => $client->id, 'type' => $type, 'brand' => $brand, 'qr_code_id' => $code->id,
            'model' => 'Test Araç', 'color' => 'Beyaz', 'license_plate' => '34 QR 123', 'engine_type' => 'Test',
            'engine_no' => 'E123', 'chassis_no' => 'C123', 'fuel_type' => 'Benzin', 'mileage' => 10000];
        $this->actingAs($this->owner)->post('/vehicle', $data)->assertRedirect(route('vehicle.index'));
        $this->assertSame(1, Vehicle::count());
        $this->assertSame(Vehicle::first()->id, $code->fresh()->vehicle_id);
        $this->assertSame(10, VehicleQrCode::available()->count());
        $this->post('/vehicle', $data)->assertSessionHasErrors('qr_code_id');
        $this->assertSame(1, Vehicle::count());
    }

    public function test_other_tenant_cannot_edit_delete_or_assign_vehicle()
    {
        [$code, $vehicle] = $this->assigned();
        $other = $this->owner('other@example.test');
        $this->actingAs($other)->get('/vehicle/' . $vehicle->id)->assertForbidden();
        $this->get('/vehicle/' . $vehicle->id . '/edit')->assertForbidden();
        $this->put('/vehicle/' . $vehicle->id, [])->assertForbidden();
        $this->delete('/vehicle/' . $vehicle->id)->assertForbidden();
        $this->post('/vehicle-qr/assign/' . $vehicle->id, ['qr_code_id' => $code->id])->assertForbidden();
    }

    public function test_staff_pages_render_and_only_offer_printed_available_codes()
    {
        $code = $this->ready();
        $unprinted = VehicleQrCode::available()->whereNull('printed_at')->first();
        $this->actingAs($this->owner)->get('/vehicle-qr')->assertOk()->assertSee('10 boş QR');
        $this->get('/vehicle/create')->assertOk()->assertSee($code->label)->assertDontSee($unprinted->label);
        $this->get('/client/create')->assertOk()->assertSee($code->label)->assertDontSee($unprinted->label);
    }

    public function test_combined_client_wizard_creates_and_links_every_record_once()
    {
        $code = $this->ready();
        \Spatie\Permission\Models\Role::create(['name' => 'client', 'parent_id' => $this->owner->id, 'guard_name' => 'web']);
        $type = DB::table('vehicle_types')->insertGetId(['type' => 'Otomobil', 'parent_id' => $this->owner->id]);
        $brand = DB::table('vehicle_brands')->insertGetId(['name' => 'Test', 'type' => $type, 'parent_id' => $this->owner->id]);
        $serviceType = DB::table('service_types')->insertGetId(['type' => 'Bakım', 'parent_id' => $this->owner->id]);
        $data = ['name' => 'Müşteri', 'email' => 'new@example.test', 'password' => 'test-password', 'phone_number' => '5551234567',
            'gender' => 'male', 'address' => 'Test', 'country' => 'Türkiye', 'state' => 'İstanbul', 'city' => 'İstanbul', 'zip_code' => '34000',
            'type' => $type, 'brand' => $brand, 'qr_code_id' => $code->id, 'model' => 'Test Araç', 'color' => 'Beyaz',
            'license_plate' => '34 QR 123', 'engine_type' => 'Test', 'engine_no' => 'E123', 'chassis_no' => 'C123',
            'fuel_type' => 'Benzin', 'mileage' => 10000, 'assign' => $this->owner->id, 'service_date' => '2026-09-20',
            'service_time' => '09:00', 'due_date' => '2026-09-20', 'due_time' => '17:00', 'status' => 'scheduled',
            'types' => [['service_type' => $serviceType, 'rate' => 1500]]];
        $this->actingAs($this->owner)->post('/client', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, Vehicle::count());
        $this->assertSame(1, Service::count());
        $this->assertSame(1, Invoice::count());
        $this->assertSame(Vehicle::first()->id, $code->fresh()->vehicle_id);
        $this->assertSame(10, VehicleQrCode::available()->count());
        // A stale tab must not leave an orphan customer behind when the QR was taken.
        $data['email'] = 'retry@example.test';
        $this->post('/client', $data)->assertSessionHasErrors('qr_code_id');
        $this->assertFalse(User::where('email', 'retry@example.test')->exists());
        $this->assertSame(1, Invoice::count());
    }
}
