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
        $this->get('/q/' . $code->token)->assertOk()->assertDontSee('Görünen servis')->assertDontSee('id="services"', false)->assertDontSee('Son servis:')->assertSee('#INV-85')
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

    public function test_only_invoice_history_is_accessible_through_pagination()
    {
        [$code, $vehicle] = $this->assigned();
        for ($i = 1; $i <= 12; $i++) {
            $service = Service::create(['vehicle' => $vehicle->id, 'parent_id' => $this->owner->id, 'service_id' => $i]);
            Invoice::create(['parent_id' => $this->owner->id, 'service' => $service->id, 'invoice_id' => $i]);
        }
        $this->get('/q/' . $code->token)->assertOk()->assertDontSee('Servisler')->assertSee('Faturalar (12)')->assertViewMissing('services');
        $this->get('/q/' . $code->token . '?services_page=2&invoices_page=2')->assertOk()->assertDontSee('#SER-1')->assertSee('#INV-1')->assertDontSee('services_page=');
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

    public function test_ready_labels_can_be_reprinted_from_list_without_changing_stock_or_assignment()
    {
        [$assigned] = $this->assigned();
        $ready = VehicleQrCode::available()->orderBy('id')->take(2)->get();
        $this->pool->markPrinted($this->owner->id, $ready->pluck('id')->all());
        $before = VehicleQrCode::orderBy('id')->get()->toArray();
        $response = $this->actingAs($this->owner)->get('/vehicle-qr')->assertOk();
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $response->getContent());
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//a[contains(text(), "Araca atanmaya hazır QR")]');
        $this->assertSame(1, $links->length);
        $url = $links->item(0)->getAttribute('href');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame($ready->pluck('id')->map(fn ($id) => (string) $id)->all(), $query['ids']);
        $print = $this->get($url)->assertOk()->assertSee('yeniden basılmış olarak işaretlemeniz gerekmez')
            ->assertDontSee($assigned->label);
        foreach ($ready as $code) {
            $print->assertSee($code->label)->assertSee('data:image/svg+xml;base64,', false);
            $singleUrl = route('vehicle-qr.print', ['ids' => [$code->id]]);
            $response->assertSee(e($singleUrl), false);
            $this->get($singleUrl)->assertOk()->assertSee($code->label);
        }
        $this->assertSame($before, VehicleQrCode::orderBy('id')->get()->toArray());
        $this->assertSame(10, VehicleQrCode::available()->count());
        $this->pool->assignExisting($this->owner->id, $this->vehicle()->id, $ready->first()->id);
        $this->assertNotNull($ready->first()->fresh()->assigned_at);
    }

    public function test_foreign_labels_cannot_be_printed_or_marked_printed()
    {
        $code = $this->ready();
        $other = $this->owner('other@example.test');
        $this->actingAs($other)->get('/vehicle-qr/print?ids[]=' . $code->id)->assertNotFound();
        $this->post('/vehicle-qr/printed', ['ids' => [$code->id]])->assertSessionHasErrors('ids');
    }

    public static function optionalVehicleCases()
    {
        return ['details provided' => ['provided'], 'details omitted' => ['omitted'], 'details blank' => ['blank']];
    }

    private function optionalVehicleFields()
    {
        return ['color', 'engine_type', 'engine_no', 'fuel_type', 'chassis_no', 'mileage',
            'last_service_date', 'next_service_due_date', 'insurance_details'];
    }

    /** @dataProvider optionalVehicleCases */
    public function test_vehicle_store_uses_qr_workflow($detailsCase)
    {
        $code = $this->ready();
        $client = User::create(['name' => 'Client', 'email' => 'client@example.test', 'password' => bcrypt('test'), 'type' => 'client', 'parent_id' => $this->owner->id]);
        $type = DB::table('vehicle_types')->insertGetId(['type' => 'Otomobil', 'parent_id' => $this->owner->id]);
        $brand = DB::table('vehicle_brands')->insertGetId(['name' => 'Test', 'type' => $type, 'parent_id' => $this->owner->id]);
        $data = ['client' => $client->id, 'type' => $type, 'brand' => $brand, 'qr_code_id' => $code->id,
            'color' => 'Beyaz', 'license_plate' => '34 QR 123', 'engine_type' => 'Test',
            'engine_no' => 'E123', 'chassis_no' => 'C123', 'fuel_type' => 'Benzin', 'mileage' => 10000];
        if ($detailsCase !== 'provided') {
            foreach (array_merge($this->optionalVehicleFields(), ['notes']) as $field) {
                unset($data[$field]);
                if ($detailsCase === 'blank') $data[$field] = '';
            }
        }
        $this->actingAs($this->owner)->post('/vehicle', $data)->assertRedirect(route('vehicle.index'));
        $this->assertSame(1, Vehicle::count());
        if ($detailsCase !== 'provided') {
            foreach (array_merge($this->optionalVehicleFields(), ['notes']) as $field) {
                $this->assertNull(Vehicle::first()->$field);
            }
        }
        $this->assertSame(Vehicle::first()->id, $code->fresh()->vehicle_id);
        $this->assertNull(Vehicle::first()->getRawOriginal('model'));
        $this->assertSame('Otomobil Test', Vehicle::first()->display_name);
        $this->get('/vehicle/' . Vehicle::first()->id . '/edit')->assertOk()->assertDontSee('name="model"', false);
        $secondModel = DB::table('vehicle_brands')->insertGetId(['name' => 'Updated Model', 'type' => $type, 'parent_id' => $this->owner->id]);
        $this->put('/vehicle/' . Vehicle::first()->id, array_merge($data, ['brand' => $secondModel, 'model' => 'Ignored free text']))->assertRedirect(route('vehicle.index'));
        $this->assertSame('Updated Model', Vehicle::first()->model);
        $this->assertNull(Vehicle::first()->getRawOriginal('model'));
        $this->assertSame(10, VehicleQrCode::available()->count());
        $emptyDetails = array_fill_keys(array_merge($this->optionalVehicleFields(), ['notes']), '');
        $this->put('/vehicle/' . Vehicle::first()->id, array_merge($data, $emptyDetails))->assertRedirect(route('vehicle.index'));
        foreach (array_keys($emptyDetails) as $field) {
            $this->assertNull(Vehicle::first()->$field);
        }
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

    public function test_dashboard_quick_access_links_return_forms_in_the_correct_layout()
    {
        $response = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk();
        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//*[@id="quick-access-menu"]/a');
        $this->assertSame(16, $links->length);
        $fullPages = ['client.create', 'service.create', 'quotation.create', 'invoice.create'];
        $pageUrls = array_map(fn ($name) => route($name), $fullPages);
        foreach ($links as $link) {
            $modal = str_contains($link->getAttribute('class'), 'customModal');
            $url = $modal ? $link->getAttribute('data-url') : $link->getAttribute('href');
            $this->assertSame(!in_array($url, $pageUrls), $modal, $url);
            $form = $this->get($url)->assertOk();
            $this->assertStringContainsString('<form', $form->getContent(), $url);
            $this->assertSame(!$modal, str_contains($form->getContent(), '<html'), $url);
        }
    }

    public function test_staff_pages_render_and_only_offer_printed_available_codes()
    {
        $code = $this->ready();
        $unprinted = VehicleQrCode::available()->whereNull('printed_at')->first();
        $this->actingAs($this->owner)->get('/vehicle-qr')->assertOk()->assertSee('10 boş QR');
        $this->get('/vehicle/create')->assertOk()->assertSee($code->label)->assertDontSee($unprinted->label)->assertDontSee('name="model"', false);
        $this->get('/client/create')->assertOk()->assertSee($code->label)->assertDontSee($unprinted->label)
            ->assertDontSee('name="password"', false)->assertDontSee('name="gender"', false)->assertDontSee('name="country"', false)->assertDontSee('name="model"', false);
    }

    public static function customerEmailCases()
    {
        return ['email provided' => ['provided'], 'email omitted' => ['omitted'], 'email blank' => ['blank']];
    }

    public function test_client_wizard_service_selector_uses_service_types_not_vehicle_brands()
    {
        DB::table('vehicle_types')->insert(['type' => 'Toyota catalogue brand', 'parent_id' => $this->owner->id]);
        $serviceId = DB::table('service_types')->insertGetId(['type' => 'Oil change service', 'parent_id' => $this->owner->id]);
        $otherOwner = $this->owner('other-service-owner@example.test');
        DB::table('service_types')->insert(['type' => 'Foreign service', 'parent_id' => $otherOwner->id]);
        $response = $this->actingAs($this->owner)->get('/client/create')->assertOk();
        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $select = $xpath->query('//select[@name="service_type"]')->item(0);
        $this->assertNotNull($select);
        $options = [];
        foreach ($xpath->query('option', $select) as $option) {
            $options[$option->getAttribute('value')] = $option->textContent;
        }
        $this->assertSame('Oil change service', $options[(string) $serviceId]);
        $this->assertCount(2, $options);
        $this->assertNotContains('Toyota catalogue brand', $options);
        $this->assertNotContains('Foreign service', $options);
        $this->assertSame('Servis türü seçin', $options['']);
        $this->assertSame('Servis türü', trim($xpath->query('//table[@data-repeater-list="types"]/thead/tr/th')->item(0)->textContent));
    }

    public function test_brand_catalogue_lists_only_models_for_the_selected_brand()
    {
        $this->actingAs($this->owner)->post('/vehicle-type', ['type' => 'Toyota'])->assertRedirect(route('vehicle-type.index'));
        $make = DB::table('vehicle_types')->where('type', 'Toyota')->first();
        $this->post('/vehicle-brand', ['name' => 'Corolla', 'type' => $make->id])->assertRedirect(route('vehicle-brand.index'));
        $model = DB::table('vehicle_brands')->where('name', 'Corolla')->first();
        $otherMake = DB::table('vehicle_types')->insertGetId(['type' => 'Ford', 'parent_id' => $this->owner->id]);
        DB::table('vehicle_brands')->insert(['name' => 'Focus', 'type' => $otherMake, 'parent_id' => $this->owner->id]);
        $this->get(route('vehicle.brand', $make->id))->assertExactJson([(string) $model->id => 'Corolla']);
        $foreignOwner = $this->owner('foreign-catalogue@example.test');
        $foreignMake = DB::table('vehicle_types')->insertGetId(['type' => 'Foreign', 'parent_id' => $foreignOwner->id]);
        $this->post('/vehicle-brand', ['name' => 'Not allowed', 'type' => $foreignMake])->assertSessionHas('error');
        $this->assertFalse(DB::table('vehicle_brands')->where('name', 'Not allowed')->exists());
        $this->get('/vehicle-type')->assertOk()->assertSee('Araç markaları');
        $this->get('/vehicle-brand')->assertOk()->assertSee('Araç modelleri');
        $vehicle = Vehicle::create(['parent_id' => $this->owner->id, 'type' => $make->id, 'brand' => $model->id, 'license_plate' => '34 NEW 01']);
        $this->assertSame('Toyota Corolla', $vehicle->fresh()->display_name);
        $qr = $this->ready();
        $this->pool->assignExisting($this->owner->id, $vehicle->id, $qr->id);
        $this->get('/q/' . $qr->token)->assertOk()->assertSee('Toyota Corolla');
    }

    /** @dataProvider customerEmailCases */
    public function test_combined_client_wizard_creates_and_links_every_record_once($emailCase)
    {
        $code = $this->ready();
        \Spatie\Permission\Models\Role::create(['name' => 'client', 'parent_id' => $this->owner->id, 'guard_name' => 'web']);
        $type = DB::table('vehicle_types')->insertGetId(['type' => 'Otomobil', 'parent_id' => $this->owner->id]);
        $brand = DB::table('vehicle_brands')->insertGetId(['name' => 'Test', 'type' => $type, 'parent_id' => $this->owner->id]);
        $serviceType = DB::table('service_types')->insertGetId(['type' => 'Bakım', 'parent_id' => $this->owner->id]);
        $data = ['name' => 'Müşteri', 'email' => 'new@example.test', 'password' => 'test-password', 'phone_number' => '5551234567',
            'gender' => 'male', 'address' => 'Test', 'country' => 'Türkiye', 'state' => 'İstanbul', 'city' => 'İstanbul', 'zip_code' => '34000',
            'type' => $type, 'brand' => $brand, 'qr_code_id' => $code->id, 'color' => 'Beyaz',
            'license_plate' => '34 QR 123', 'engine_type' => 'Test', 'engine_no' => 'E123', 'chassis_no' => 'C123',
            'fuel_type' => 'Benzin', 'mileage' => 10000, 'assign' => $this->owner->id, 'service_date' => '2026-09-20',
            'service_time' => '09:00', 'due_date' => '2026-09-20', 'due_time' => '17:00', 'status' => 'scheduled',
            'types' => [['service_type' => $serviceType, 'rate' => 1500]]];
        unset($data['password'], $data['gender'], $data['country'], $data['address'], $data['state'], $data['city'], $data['zip_code']);
        if ($emailCase === 'omitted') unset($data['email']);
        if ($emailCase === 'blank') $data['email'] = '';
        if ($emailCase !== 'provided') {
            foreach (array_merge($this->optionalVehicleFields(), ['vehicle_notes']) as $field) {
                unset($data[$field]);
                if ($emailCase === 'blank') $data[$field] = '';
            }
        }
        if ($emailCase === 'provided') {
            $data['external_labor_amount'] = '250.75';
            $data['notes'] = 'Customer note';
            $data['vehicle_notes'] = 'Vehicle note';
            $data['service_notes'] = 'Service note';
        }
        $this->actingAs($this->owner)->post('/client', array_merge($data, ['email' => 'not-an-email']))->assertSessionHasErrors('email');
        $this->assertSame(0, Vehicle::count());
        $this->post('/client', $data)->assertRedirect()->assertSessionHasNoErrors();
        $customer = User::where('type', 'client')->firstOrFail();
        $this->assertSame($emailCase === 'provided' ? 'new@example.test' : null, $customer->email);
        $this->assertFalse(\Hash::check('', $customer->password));
        foreach (['gender', 'country', 'address', 'state', 'city', 'zip_code'] as $field) {
            $this->assertNull($customer->clients->$field);
        }
        $this->assertSame($data['notes'] ?? null, $customer->clients->notes);
        $this->assertSame(($data['vehicle_notes'] ?? null) ?: null, Vehicle::first()->notes);
        $this->assertSame($data['service_notes'] ?? null, Service::first()->notes);
        if ($emailCase !== 'provided') {
            foreach (array_merge($this->optionalVehicleFields(), ['notes']) as $field) {
                $this->assertNull(Vehicle::first()->$field);
            }
        }
        $this->assertSame(1, Vehicle::count());
        $this->assertSame(1, Service::count());
        $this->assertSame(1, Invoice::count());
        $this->assertEquals($data['external_labor_amount'] ?? 0, Service::first()->external_labor_amount);
        $this->assertEquals($data['external_labor_amount'] ?? 0, Invoice::first()->external_labor_amount);
        $this->assertSame(Vehicle::first()->id, $code->fresh()->vehicle_id);
        $this->assertSame(10, VehicleQrCode::available()->count());
        // A stale tab must not leave an orphan customer behind when the QR was taken.
        $data['email'] = 'retry@example.test';
        $this->post('/client', $data)->assertSessionHasErrors('qr_code_id');
        $this->assertFalse(User::where('email', 'retry@example.test')->exists());
        $this->assertSame(1, Invoice::count());
        if ($emailCase !== 'provided') {
            unset($data['email']);
            $data['qr_code_id'] = $this->ready()->id;
            $this->post('/client', $data)->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame(2, User::where('type', 'client')->whereNull('email')->count());
        }
    }
}
