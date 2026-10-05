<?php
namespace Tests\Feature;

use App\Models\{User,Subscription,SubscriptionChange,PackageTransaction,Vehicle};
use App\Services\AppointmentBooking;
use Illuminate\Support\Facades\{DB,Gate};
use Carbon\{Carbon,CarbonImmutable};
use Tests\TestCase;

class SubscriptionAdminTest extends TestCase
{
    private $admin;
    private $owner;
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']);
        DB::purge('sqlite');
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        Carbon::setTestNow(Carbon::parse('2026-10-05 08:00:00','Europe/Istanbul'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-05 08:00:00','Europe/Istanbul'));
        $this->admin=User::create(['name'=>'Super Admin','email'=>'admin-sub@example.test','password'=>bcrypt('test'),'type'=>'super admin','email_verified_at'=>now()]);
        $this->owner=User::create(['name'=>'Abonelik Servisi','email'=>'owner-sub@example.test','password'=>bcrypt('test'),'type'=>'owner','subscription'=>Subscription::where('vehicle_limit',500)->value('id'),'subscription_expire_date'=>'2026-10-31']);
        $this->owner->email_verified_at=now(); $this->owner->save();
        Gate::before(fn($user)=>in_array($user->type,['owner','super admin']) ? true : false);
        $this->actingAs($this->admin)->withoutMiddleware(\App\Http\Middleware\XSS::class);
    }
    protected function tearDown(): void { Carbon::setTestNow(); CarbonImmutable::setTestNow(); parent::tearDown(); }

    public function test_list_edit_and_filters_are_admin_only()
    {
        $this->get(route('subscription-admin.index'))->assertOk()->assertSee('Abonelik Servisi')->assertSee('Askıya al')->assertSee('Abonelik yönetimi');
        $this->get(route('subscription-admin.edit',$this->owner->id))->assertOk()->assertSee('Paket ve süreyi kaydet');
        $this->get(route('subscription-admin.index',['search'=>'bulunamayan']))->assertOk()->assertDontSee('owner-sub@example.test');
        $this->get(route('subscription-admin.index',['status'=>'expired']))->assertOk()->assertDontSee('owner-sub@example.test');
        $this->get(route('subscription-admin.edit',$this->admin->id))->assertNotFound();
        $this->actingAs($this->owner)->get(route('subscription-admin.index'))->assertForbidden();
        $this->put(route('subscription-admin.update',$this->owner->id),['subscription'=>1,'no_expiry'=>1])->assertForbidden();
        $this->post(route('subscription-admin.status',$this->owner->id),['action'=>'suspend','confirm'=>1])->assertForbidden();
    }

    public function test_edit_changes_package_expiry_and_records_history_without_recording_payment()
    {
        $plan=Subscription::where('vehicle_limit',1000)->first();
        $this->put(route('subscription-admin.update',$this->owner->id),['subscription'=>$plan->id])->assertSessionHasErrors('expiry_date');
        $this->put(route('subscription-admin.update',$this->owner->id),['subscription'=>999999,'no_expiry'=>1])->assertSessionHasErrors('subscription');
        $this->put(route('subscription-admin.update',$this->owner->id),['subscription'=>$plan->id,'expiry_date'=>'2026-12-31'])->assertSessionHasNoErrors();
        $owner=$this->owner->fresh(); $this->assertEquals($plan->id,$owner->subscription); $this->assertEquals('2026-12-31',$owner->subscription_expire_date);
        $this->assertSame(1,SubscriptionChange::count()); $this->assertSame(0,PackageTransaction::count());
        $this->assertEquals($this->admin->id,SubscriptionChange::first()->actor_id);
        $this->get(route('subscription-admin.edit',$owner->id))->assertOk()->assertSee('Son abonelik işlemleri')->assertSee('Super Admin');
        $this->put(route('subscription-admin.update',$owner->id),['subscription'=>$plan->id,'no_expiry'=>1])->assertSessionHasNoErrors();
        $this->assertNull($owner->fresh()->subscription_expire_date);
    }

    public function test_suspend_is_idempotent_blocks_existing_owner_and_staff_sessions_and_resume_preserves_expiry()
    {
        $staff=User::create(['name'=>'Staff','email'=>'staff-sub@example.test','password'=>bcrypt('test'),'type'=>'employee','parent_id'=>$this->owner->id]);
        Vehicle::create(['parent_id'=>$this->owner->id]);
        $this->post(route('subscription-admin.status',$this->owner->id),['action'=>'suspend'])->assertSessionHasErrors('confirm');
        $this->post(route('subscription-admin.status',$this->owner->id),['action'=>'suspend','confirm'=>1,'reason'=>'Tahsilat bekleniyor'])->assertSessionHasNoErrors();
        $this->post(route('subscription-admin.status',$this->owner->id),['action'=>'suspend','confirm'=>1])->assertSessionHasNoErrors();
        $this->assertSame(1,SubscriptionChange::count());
        $migration=require database_path('migrations/2026_10_05_000004_add_subscription_administration.php'); $migration->up();
        $this->assertNotNull($this->owner->fresh()->subscription_suspended_at);
        $this->assertEquals('2026-10-31',$this->owner->fresh()->subscription_expire_date);
        $this->assertSame(1,Vehicle::count());
        $this->get(route('subscription-admin.index',['status'=>'suspended']))->assertOk()->assertSee('owner-sub@example.test');
        $this->actingAs($this->owner)->get(route('vehicle-type.index'))->assertForbidden()->assertSee('Aboneliğiniz askıya alındı');
        $this->actingAs($staff)->getJson(route('vehicle-type.index'))->assertForbidden()->assertJson(['message'=>'İşletmenizin aboneliği askıya alındı.']);
        $this->post(route('logout'))->assertRedirect('/');
        $this->actingAs($this->admin)->post(route('subscription-admin.status',$this->owner->id),['action'=>'resume','confirm'=>1])->assertSessionHasNoErrors();
        $this->post(route('subscription-admin.status',$this->owner->id),['action'=>'resume','confirm'=>1])->assertSessionHasNoErrors();
        $this->assertSame(2,SubscriptionChange::count());
        $this->assertNull($this->owner->fresh()->subscription_suspended_at);
        $this->assertEquals('2026-10-31',$this->owner->fresh()->subscription_expire_date);
        $this->actingAs($this->owner)->get(route('vehicle-type.index'))->assertOk();
    }

    public function test_package_edit_does_not_resume_suspended_owner_and_staff_login_is_rejected()
    {
        $this->post(route('subscription-admin.status',$this->owner->id),['action'=>'suspend','confirm'=>1]);
        $demo=Subscription::where('vehicle_limit',50)->first();
        $this->put(route('subscription-admin.update',$this->owner->id),['subscription'=>$demo->id,'no_expiry'=>1])->assertSessionHasNoErrors();
        $this->assertNotNull($this->owner->fresh()->subscription_suspended_at);
        $this->get(route('subscription-admin.edit',$this->owner->id))->assertOk()->assertSee('Abonelik askıda');
        $this->owner->subscription_expire_date='2026-09-01'; $this->owner->save();
        $this->post(route('logout'));
        $this->post(route('login'),['email'=>$this->owner->email,'password'=>'test'])->assertRedirect(route('login'))->assertSessionHas('error');
        $this->assertGuest();
        $staff=User::create(['name'=>'Staff','email'=>'login-staff-sub@example.test','password'=>bcrypt('test'),'type'=>'employee','parent_id'=>$this->owner->id]);
        $this->post(route('login'),['email'=>$staff->email,'password'=>'test'])->assertRedirect(route('login'))->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_suspension_closes_booking_slots_without_changing_profile_hours()
    {
        $booking=app(AppointmentBooking::class); $profile=$booking->profileForOwner($this->owner->id);
        $hours=array_fill_keys(range(1,7),[10,11]); $profile->update(['is_active'=>true,'weekly_hours'=>$hours]);
        $this->assertSame([10,11],$booking->availableHours($profile,today()->toDateString()));
        $this->post(route('subscription-admin.status',$this->owner->id),['action'=>'suspend','confirm'=>1]);
        $this->assertSame([],$booking->availableHours($profile,today()->toDateString()));
        $this->assertTrue($profile->fresh()->is_active); $this->assertEquals($hours,$profile->fresh()->weekly_hours);
        $this->post(route('subscription-admin.status',$this->owner->id),['action'=>'resume','confirm'=>1]);
        $this->assertSame([10,11],$booking->availableHours($profile,today()->toDateString()));
    }
}
