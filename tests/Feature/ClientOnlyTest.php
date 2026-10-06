<?php
namespace Tests\Feature;

use App\Models\{Client, Invoice, Service, User, Vehicle, VehicleQrCode};
use Illuminate\Support\Facades\{DB, Gate, Hash};
use Tests\TestCase;

class ClientOnlyTest extends TestCase
{
    private $owner;
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']);
        DB::purge('sqlite');
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        $this->owner=User::create(['name'=>'Shop','email'=>'client-only-owner@example.test','password'=>'x','type'=>'owner']);
        Gate::before(fn($user)=>$user->type==='owner' ? true : null);
        $this->actingAs($this->owner);
        DB::table('settings')->insert(['parent_id'=>$this->owner->id,'name'=>'pricing_feature','value'=>'off']);
    }

    public function test_simple_customer_creation_has_no_vehicle_service_invoice_or_qr_side_effects()
    {
        $this->get('/client')->assertOk()->assertSee('Servisle müşteri ekle')->assertSee(route('client.simple.create'),false);
        $this->get(route('client.simple.create'))->assertOk()->assertSee('+90')->assertSee('maxlength="10"',false)
            ->assertDontSee('name="license_plate"',false)->assertDontSee('name="password"',false);
        $this->post(route('client.simple.store'),['name'=>'Yeni müşteri','phone_number'=>'5551234567',
            'email'=>'','type'=>'owner','parent_id'=>999,'license_plate'=>'34 FAKE','external_labor_amount'=>500])->assertRedirect(route('client.index'))->assertSessionHasNoErrors();
        $customer=User::where('type','client')->firstOrFail();
        $this->assertNull($customer->email);
        $this->assertSame($this->owner->id,$customer->parent_id);
        $this->assertSame('5551234567',$customer->phone_number);
        $this->assertFalse(Hash::check('',$customer->password));
        $this->assertTrue($customer->hasRole('client'));
        $this->assertSame(1,Client::count());
        foreach ([Vehicle::class,Service::class,Invoice::class,VehicleQrCode::class] as $model) $this->assertSame(0,$model::count());
        $this->post(route('client.simple.store'),['name'=>'İkinci müşteri','phone_number'=>'5551234568'])->assertSessionHasNoErrors();
        $this->assertSame(2,Client::count());
        $this->assertEquals(2,Client::max('client_id'));
        $this->get(route('client.show',encrypt($customer->id)))->assertOk()->assertSee('Yeni müşteri');
    }

    public function test_simple_customer_validation_and_editing_work_without_vehicle_fields()
    {
        $url=route('client.simple.store');
        $this->post($url,['phone_number'=>'05551234567'])->assertSessionHasErrors('phone_number');
        $this->post($url,['name'=>'Customer','phone_number'=>'5551234567','email'=>'invalid'])->assertSessionHasErrors('email');
        $this->post($url,['phone_number'=>'5551234567'])->assertSessionHasErrors('name');
        $this->assertSame(0,Client::count());
        $this->post($url,['name'=>'Customer','phone_number'=>'5551234567','email'=>'simple@example.test','city'=>'İstanbul','state'=>'Kadıköy','notes'=>'Test'])->assertSessionHasNoErrors();
        $customer=User::where('type','client')->firstOrFail();
        $this->get(route('client.edit',encrypt($customer->id)))->assertOk()->assertSee('name="city"',false)->assertDontSee('name="license_plate"',false);
        $this->put(route('client.simple.update',$customer->id),['name'=>'Güncel müşteri','phone_number'=>'5551234568','email'=>'simple@example.test','city'=>'Ankara','state'=>'Çankaya','notes'=>'Güncel not'])->assertRedirect(route('client.index'))->assertSessionHasNoErrors();
        $this->assertSame('Ankara',$customer->fresh()->clients->city);
        $this->assertSame('Güncel müşteri',$customer->fresh()->name);
        $this->post($url,['name'=>'Duplicate','phone_number'=>'5551234569','email'=>'simple@example.test'])->assertSessionHasErrors('email');
        $other=User::create(['name'=>'Other','email'=>'simple-other@example.test','password'=>'x','type'=>'owner']);
        $this->actingAs($other)->put(route('client.simple.update',$customer->id),['name'=>'Hack','phone_number'=>'5551234567'])->assertNotFound();
        $this->get(route('client.edit',encrypt($customer->id)))->assertNotFound();
        $this->assertSame('Güncel müşteri',$customer->fresh()->name);
    }

    public function test_customer_only_permission_does_not_require_vehicle_permission_and_clients_cannot_create()
    {
        $employee=User::create(['name'=>'Staff','email'=>'simple-staff@example.test','password'=>'x','type'=>'employee','parent_id'=>$this->owner->id]);
        Gate::define('create client',fn($user)=>$user->id===$employee->id);
        Gate::define('manage client',fn($user)=>$user->id===$employee->id);
        $this->actingAs($employee)->get('/client')->assertOk()->assertSee(route('client.simple.create'),false)->assertDontSee('Servisle müşteri ekle');
        $this->get(route('client.simple.create'))->assertOk();
        $this->post(route('client.simple.store'),['name'=>'Staff customer','phone_number'=>'5551234567'])->assertSessionHasNoErrors();
        $customer=User::where('type','client')->firstOrFail();
        $this->actingAs($customer)->get(route('client.simple.create'))->assertForbidden();
        $this->post(route('client.simple.store'),['name'=>'Blocked','phone_number'=>'5551234567'])->assertForbidden();
        $this->assertSame(1,Client::count());
    }
}
