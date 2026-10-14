<?php
namespace Tests\Feature;

use App\Models\{User, Subscription, VehicleCapacityRequest};
use App\Services\{DemoLifecycle, DemoCleanup, VehicleCapacity};
use Illuminate\Support\Facades\{DB, Gate};
use Tests\TestCase;

class MonthlyPackagesAndDemoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']);
        DB::purge('sqlite');
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        User::create(['name'=>'Admin','email'=>'admin@demo.test','password'=>'x','type'=>'super admin']);
        Gate::before(fn()=>true);
        $this->withoutMiddleware(\App\Http\Middleware\XSS::class);
    }
    private function owner(string $email='shop@demo.test'): User
    {
        return User::create(['name'=>'Demo','email'=>$email,'password'=>'x','type'=>'owner',
            'subscription'=>Subscription::where('vehicle_limit',50)->value('id'),'parent_id'=>1]);
    }
    private function age(User $owner): void
    {
        DB::table('users')->where('id',$owner->id)->update(['demo_started_at'=>now()->subDays(40),
            'demo_expires_at'=>now()->subDays(26),'last_business_login_at'=>now()->subDays(30)]);
    }
    public function test_prices_and_demo_expiry_cannot_be_restarted(): void
    {
        foreach([50=>0,500=>800,1000=>1400,2000=>2200,3000=>3000] as $limit=>$amount) {
            $plan=Subscription::where('vehicle_limit',$limit)->first();
            $this->assertEquals($amount,$plan->package_amount);
            $this->assertSame('Monthly',$plan->interval);
        }
        $this->assertEquals(500,Subscription::where('vehicle_limit',3000)->value('vehicle_block_amount'));
        $owner=$this->owner();
        $this->assertFalse(app(DemoLifecycle::class)->expired($owner));
        $expiry=$owner->fresh()->demo_expires_at;
        $this->travel(15)->days();
        $this->actingAs($owner); assignSubscription($owner->subscription);
        $this->assertSame($expiry,$owner->fresh()->demo_expires_at);
        $this->assertTrue(app(DemoLifecycle::class)->expired($owner->fresh()));
    }
    public function test_expired_demo_panel_blocked_but_packages_and_public_site_open(): void
    {
        $owner=$this->owner();$this->age($owner);
        $this->actingAs($owner->fresh())->get(route('dashboard'))->assertRedirect(route('subscriptions.index'));
        $this->get(route('subscriptions.index'))->assertOk()->assertSee('14 gün');
        $this->get('/tanitim')->assertOk();
        $this->post(route('subscription.manual_assign_package',[encrypt(Subscription::where('vehicle_limit',500)->value('id')),$owner->id]))->assertForbidden();
        $staff=User::create(['name'=>'Personel','email'=>'staff@demo.test','password'=>'x','type'=>'employee','parent_id'=>$owner->id]);
        $this->actingAs($staff)->get(route('dashboard'))->assertRedirect(route('subscriptions.index'));
    }
    public function test_cleanup_deletes_only_expired_inactive_never_paid_tenant_and_children(): void
    {
        $owner=$this->owner();$this->age($owner);
        $other=$this->owner('other@demo.test');
        $client=User::create(['name'=>'Müşteri','email'=>'customer@demo.test','type'=>'client','password'=>'x','parent_id'=>$owner->id]);
        foreach(['clients','vehicles','services','invoices','invoice_items','items','taxes','units'] as $table) {
            DB::table($table)->insert(['parent_id'=>$owner->id]);
            DB::table($table)->insert(['parent_id'=>$other->id]);
        }
        DB::table('settings')->insert(['name'=>'demo_only','value'=>'yes','parent_id'=>$owner->id]);
        $this->artisan('subscriptions:purge-demos')->assertExitCode(0);
        $this->assertNotNull($owner->fresh());
        $this->assertTrue(app(DemoCleanup::class)->delete($owner->id));
        $this->assertNull($owner->fresh());$this->assertNull($client->fresh());$this->assertNotNull($other->fresh());
        foreach(['clients','vehicles','services','invoices','invoice_items','items','taxes','units'] as $table) {
            $this->assertSame(0,DB::table($table)->where('parent_id',$owner->id)->count());
            $this->assertSame(1,DB::table($table)->where('parent_id',$other->id)->count());
        }
        $this->assertSame(5,Subscription::whereNotNull('vehicle_limit')->count());
        $this->assertNotNull(User::where('type','super admin')->first());
        $this->assertFalse(app(DemoCleanup::class)->delete($owner->id));
    }
    public function test_login_after_candidate_selection_and_paid_upgrade_both_prevent_deletion(): void
    {
        $owner=$this->owner();$this->age($owner);
        $this->assertSame(1,app(DemoCleanup::class)->candidates()->count());
        app(DemoLifecycle::class)->recordLogin($owner);
        $this->assertFalse(app(DemoCleanup::class)->delete($owner->id));
        $this->age($owner);
        $owner->update(['subscription'=>Subscription::where('vehicle_limit',500)->value('id')]);
        $owner->update(['subscription'=>Subscription::where('vehicle_limit',50)->value('id')]);
        $this->assertNotNull($owner->fresh()->paid_subscription_at);
        $this->assertFalse(app(DemoCleanup::class)->delete($owner->id));
    }
    public function test_pending_payment_and_recent_login_protect_demo(): void
    {
        $owner=$this->owner();$this->age($owner);
        DB::table('package_transactions')->insert(['user_id'=>$owner->id,'amount'=>800,'payment_status'=>'Pending','subscription_transactions_id'=>'pending-demo']);
        $this->assertFalse(app(DemoCleanup::class)->delete($owner->id));
        DB::table('package_transactions')->where('user_id',$owner->id)->delete();
        DB::table('users')->where('id',$owner->id)->update(['last_business_login_at'=>now()->subDays(29)]);
        $this->assertFalse(app(DemoCleanup::class)->delete($owner->id));
    }
    public function test_monthly_extra_capacity_expires_without_deleting_vehicles(): void
    {
        $owner=$this->owner();$plan=Subscription::where('vehicle_limit',3000)->first();
        $owner->update(['subscription'=>$plan->id]);
        $record=VehicleCapacityRequest::create(['owner_id'=>$owner->id,'subscription_id'=>$plan->id,'vehicles'=>500,'amount'=>500,'status'=>'pending']);
        $this->actingAs(User::where('type','super admin')->first())->post(route('capacity.review',$record->id),['decision'=>'approved'])->assertSessionHasNoErrors();
        $this->assertNotNull($record->fresh()->expires_at);
        $this->assertSame(3500,app(VehicleCapacity::class)->summary($owner)['limit']);
        $this->assertEquals(3500,app(VehicleCapacity::class)->summary($owner)['monthly_amount']);
        DB::table('vehicles')->insert(['parent_id'=>$owner->id]);
        $this->travel(32)->days();
        $this->assertSame(3000,app(VehicleCapacity::class)->summary($owner)['limit']);
        $this->assertSame(1,DB::table('vehicles')->where('parent_id',$owner->id)->count());
    }
    public function test_extra_blocks_can_be_bought_repeatedly_without_upper_capacity_limit(): void
    {
        $owner=$this->owner();$plan=Subscription::where('vehicle_limit',3000)->first();
        $owner->update(['subscription'=>$plan->id]);
        $admin=User::where('type','super admin')->first();
        for ($blocks=1; $blocks<=4; $blocks++) {
            $this->actingAs($owner)->post(route('capacity.store'),['confirm'=>1,'quoted_amount'=>500])->assertSessionHasNoErrors();
            $record=VehicleCapacityRequest::where('owner_id',$owner->id)->latest('id')->first();
            $this->actingAs($admin)->post(route('capacity.review',$record->id),['decision'=>'approved'])->assertSessionHasNoErrors();
            $this->assertSame(3000 + $blocks*500, app(VehicleCapacity::class)->summary($owner)['limit']);
            $this->assertEquals(3000 + $blocks*500, app(VehicleCapacity::class)->summary($owner)['monthly_amount']);
        }
        $this->assertSame(5000,app(VehicleCapacity::class)->summary($owner)['limit']);
        $this->assertSame(4,VehicleCapacityRequest::where('status','approved')->count());
    }

    public function test_existing_demo_accounts_get_safe_grace_period_during_migration(): void
    {
        $owner=$this->owner();$this->age($owner);
        $paid=$this->owner('paid@demo.test');
        DB::table('package_transactions')->insert(['user_id'=>$paid->id,'amount'=>800,
            'payment_status'=>'Success','subscription_transactions_id'=>'previous-paid']);
        $migration=require database_path('migrations/2026_10_14_000001_revise_monthly_packages_and_demo.php');
        $migration->down();$migration->up();
        $this->assertEquals(now()->addDays(14)->toDateTimeString(),$owner->fresh()->demo_expires_at);
        $this->assertEquals(now()->toDateTimeString(),$owner->fresh()->last_business_login_at);
        $this->assertNotNull($paid->fresh()->paid_subscription_at);
        $this->assertSame(0,app(DemoCleanup::class)->candidates()->count());
    }
    public function test_expired_demo_cannot_offer_new_public_appointments(): void
    {
        $owner=$this->owner();
        $profile=app(\App\Services\AppointmentBooking::class)->profileForOwner($owner->id);
        $profile->update(['is_active'=>true,'weekly_hours'=>array_fill(1,7,[9,10,11,12,13,14,15,16,17])]);
        $date=now()->addDay()->toDateString();
        $this->assertNotEmpty(app(\App\Services\AppointmentBooking::class)->availableHours($profile->fresh(),$date));
        $this->age($owner);
        $this->assertSame([],app(\App\Services\AppointmentBooking::class)->availableHours($profile->fresh(),$date));
        $this->actingAs($owner->fresh())->get('/')->assertRedirect(route('subscriptions.index'));
    }

}
