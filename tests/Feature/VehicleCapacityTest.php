<?php
namespace Tests\Feature;

use App\Models\{PackageTransaction, Subscription, User, Vehicle, VehicleCapacityRequest, VehicleQrCode};
use App\Services\{VehicleCapacity, VehicleQrPool};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VehicleCapacityTest extends TestCase
{
    private $owner;
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']);
        DB::purge('sqlite');
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        \Illuminate\Support\Facades\Schema::table('subscriptions', function ($table) {
            $table->integer('enabled_openai')->default(0);
            $table->integer('enabled_n8n')->default(0);
        });
        $this->owner = User::create(['name'=>'Capacity Shop','email'=>'capacity@example.test','password'=>bcrypt('test'),'type'=>'owner']);
        Gate::before(fn($user)=>in_array($user->type,['owner','super admin']) ? true : null);
        $this->actingAs($this->owner);
        // SQLite intentionally omits the legacy MySQL version migration.
        $this->withoutMiddleware(\App\Http\Middleware\XSS::class);
    }

    public function test_standard_tiers_and_repeatable_migration_preserve_existing_assignment()
    {
        $this->assertEquals([50,500,1000,2000,3000],Subscription::whereNotNull('vehicle_limit')->orderBy('vehicle_limit')->pluck('vehicle_limit')->all());
        $plan = Subscription::where('vehicle_limit',3000)->first(); $plan->update(['package_amount'=>850,'vehicle_block_amount'=>120]);
        $migration = require database_path('migrations/2026_10_05_000001_add_vehicle_capacity_packages.php'); $migration->up();
        $this->assertEquals(850,$plan->fresh()->package_amount);
        $this->assertEquals(120,$plan->fresh()->vehicle_block_amount);
        $this->assertSame(5,Subscription::whereNotNull('vehicle_limit')->count());
    }

    public function test_limit_blocks_next_vehicle_without_creating_qr_and_deletion_frees_capacity()
    {
        $plan = Subscription::where('vehicle_limit',500)->first(); $this->owner->update(['subscription'=>$plan->id]);
        DB::table('vehicles')->insert(array_fill(0,500,['parent_id'=>$this->owner->id]));
        $pool = app(VehicleQrPool::class);
        try { $pool->createVehicle($this->owner->id,null,fn()=>Vehicle::create(['parent_id'=>$this->owner->id])); $this->fail('Limit must block'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('vehicle_limit',$e->errors()); }
        $this->assertSame(500,Vehicle::count()); $this->assertSame(0,VehicleQrCode::count());
        Vehicle::first()->delete();
        $pool->createVehicle($this->owner->id,null,fn()=>Vehicle::create(['parent_id'=>$this->owner->id]));
        $this->assertSame(500,Vehicle::count());
        $other = User::create(['name'=>'Other','email'=>'other-capacity@example.test','password'=>'x','type'=>'owner']);
        $this->assertSame(0,app(VehicleCapacity::class)->summary($other)['used']);
    }

    public function test_request_requires_consent_and_admin_approval_is_idempotent_and_tenant_scoped()
    {
        $plan = Subscription::where('vehicle_limit',3000)->first(); $plan->update(['vehicle_block_amount'=>100]);
        $this->owner->update(['subscription'=>$plan->id]);
        $this->post(route('capacity.store'),[])->assertSessionHasErrors('confirm');
        $this->post(route('capacity.store'),['confirm'=>1,'quoted_amount'=>99])->assertSessionHasErrors('capacity');
        $this->post(route('capacity.store'),['confirm'=>1,'quoted_amount'=>100])->assertSessionHasNoErrors();
        $record = VehicleCapacityRequest::first(); $this->assertEquals(100,$record->amount);
        $this->assertEquals(3000,app(VehicleCapacity::class)->summary($this->owner)['limit']);
        $this->post(route('capacity.store'),['confirm'=>1,'quoted_amount'=>100])->assertSessionHasErrors('capacity');
        $this->post(route('capacity.review',$record->id),['decision'=>'approved'])->assertForbidden();
        $plan->update(['vehicle_block_amount'=>200]);
        $admin = User::create(['name'=>'Admin','email'=>'capacity-admin@example.test','password'=>'x','type'=>'super admin']);
        $this->actingAs($admin);
        $this->post(route('capacity.review',$record->id),['decision'=>'approved'])->assertSessionHasNoErrors();
        $this->post(route('capacity.review',$record->id),['decision'=>'approved'])->assertSessionHasNoErrors();
        $this->assertEquals(3500,app(VehicleCapacity::class)->summary($this->owner)['limit']);
        $this->assertSame(1,PackageTransaction::count()); $this->assertEquals(100,PackageTransaction::first()->amount);
        $other = User::create(['name'=>'Other','email'=>'other-request@example.test','password'=>'x','type'=>'owner']);
        $this->actingAs($other)->get(route('capacity.index'))->assertOk()->assertDontSee($this->owner->name);
        $this->post(route('capacity.store'),['confirm'=>1,'quoted_amount'=>100])->assertSessionHasErrors('capacity');
        $this->actingAs($this->owner)->get(route('subscriptions.index'))->assertOk()->assertSee('3.500 araç')->assertSee('500 araçlık ek kapasite talep et');
    }

    public function test_rejection_and_package_change_do_not_grant_capacity_and_new_plan_keeps_accounts_active()
    {
        $plan = Subscription::where('vehicle_limit',3000)->first(); $plan->update(['vehicle_block_amount'=>100]);
        $this->owner->update(['subscription'=>$plan->id]);
        $this->post(route('capacity.store'),['confirm'=>1,'quoted_amount'=>100])->assertSessionHasNoErrors();
        $record = VehicleCapacityRequest::first();
        $admin = User::create(['name'=>'Admin','email'=>'reject-admin@example.test','password'=>'x','type'=>'super admin']);
        $this->actingAs($admin);
        $this->owner->update(['subscription'=>Subscription::where('vehicle_limit',500)->value('id')]);
        $this->post(route('capacity.review',$record->id),['decision'=>'approved'])->assertSessionHasErrors('capacity');
        $this->post(route('capacity.review',$record->id),['decision'=>'rejected'])->assertSessionHasNoErrors();
        $this->assertSame(0,PackageTransaction::count());
        $staff = User::create(['name'=>'Staff','email'=>'capacity-staff@example.test','password'=>'x','parent_id'=>$this->owner->id,'type'=>'employee','is_active'=>0]);
        SetLimit($plan,$this->owner->id); $this->assertEquals(1,$staff->fresh()->is_active);
        $this->actingAs($this->owner)->put(route('subscriptions.update',$plan->id),['package_amount'=>1])->assertForbidden();
    }

    public function test_demo_is_limited_to_fifty_and_admin_can_edit_package_prices()
    {
        $demo = Subscription::where('vehicle_limit',50)->first();
        $this->owner->update(['subscription'=>$demo->id]);
        DB::table('vehicles')->insert(array_fill(0,50,['parent_id'=>$this->owner->id]));
        $this->get(route('subscriptions.index'))->assertOk()->assertSee('Ücretsiz demo')->assertSee('50 araç');
        try { app(VehicleCapacity::class)->assertAvailable($this->owner->id); $this->fail('Demo must stop at 50'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('vehicle_limit',$e->errors()); }
        $admin = User::create(['name'=>'Admin','email'=>'edit-admin@example.test','password'=>'x','type'=>'super admin']);
        $this->actingAs($admin)->get(route('subscriptions.index'))->assertOk()->assertSee('Paketi düzenle');
        $plan = Subscription::where('vehicle_limit',3000)->first();
        $this->get(route('subscriptions.edit',$plan->id))->assertOk()->assertSee('name="vehicle_block_amount"', false);
        $data = ['title'=>$plan->title,'interval'=>'Monthly','package_amount'=>750,'vehicle_limit'=>3000,'vehicle_block_amount'=>125];
        $this->put(route('subscriptions.update',$plan->id),$data)->assertSessionHasNoErrors();
        $this->assertEquals(125,$plan->fresh()->vehicle_block_amount);
        $this->put(route('subscriptions.update',$plan->id),array_replace($data,['vehicle_limit'=>333]))->assertSessionHas('error');
    }
}
