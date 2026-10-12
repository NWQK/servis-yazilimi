<?php
namespace Tests\Feature;

use App\Mail\Common;
use App\Models\{BusinessReview, Client, User, Vehicle, AppointmentSmsSetting, AppointmentProfile, BookingService};
use App\Services\{BusinessReviews, VehicleQrPool};
use Illuminate\Support\Facades\{DB, Gate, Http, Mail};
use Tests\TestCase;

class BusinessReviewTest extends TestCase
{
    private $owner;
    private $admin;
    protected function setUp(): void {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']);
        DB::purge('sqlite');
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        $this->admin=User::create(['name'=>'Admin','email'=>'review-admin@example.test','password'=>'x','type'=>'super admin']);
        $this->owner=User::create(['name'=>'Review Shop','email'=>'review-owner@example.test','password'=>'x','type'=>'owner']);
        foreach (['SERVER_HOST'=>'smtp.example.test','SERVER_PORT'=>'465','SERVER_ENCRYPTION'=>'ssl','FROM_EMAIL'=>'bilgi@example.test','SERVER_USERNAME'=>'bilgi@example.test','SERVER_PASSWORD'=>'test'] as $name=>$value) {
            DB::table('settings')->insert(['parent_id'=>$this->admin->id,'type'=>'smtp','name'=>$name,'value'=>$value]);
        }
        Mail::fake(); Http::fake(['*'=>Http::response(['response'=>['status'=>['code'=>200],'order'=>['id'=>'98765']]],200)]);
        Gate::before(fn($u)=>in_array($u->type,['owner','super admin']) ? true : null);
        $this->withoutMiddleware(\App\Http\Middleware\XSS::class);
    }
    private function customer(string $phone='5551234567', ?string $email='review-client@example.test', ?User $owner=null): User {
        $owner=$owner ?? $this->owner;
        $user=User::create(['name'=>'Test Customer','email'=>$email,'phone_number'=>$phone,'password'=>'x','type'=>'client','parent_id'=>$owner->id]);
        Client::create(['user_id'=>$user->id,'parent_id'=>$owner->id,'client_id'=>$user->id]);
        return $user;
    }
    private function smsOn(): void {
        AppointmentSmsSetting::central()->update(['enabled'=>true,'review_sms_enabled'=>true,'api_key'=>'key','api_hash'=>'hash','sender'=>'SANAYI']);
    }
    public function test_registration_sends_one_central_sms_and_email_with_the_same_private_link() {
        $this->smsOn();
        $user=$this->customer(); $review=BusinessReview::firstOrFail();
        $this->assertSame('accepted',$review->sms_status);
        $this->assertSame('accepted',$review->email_status);
        Http::assertSent(fn($r)=>$r['request']['order']['sender']==='SANAYI' && $r['request']['order']['message']['receipents']['number']===['905551234567'] && str_contains($r['request']['order']['message']['text'],$review->publicUrl()));
        Mail::assertSent(Common::class,fn($mail)=>$mail->hasTo($user->email) && $mail->data['module']===BusinessReviews::MODULE && str_contains($mail->data['message'],$review->publicUrl()) && $mail->data['settings']['FROM_EMAIL']==='bilgi@example.test');
        app(BusinessReviews::class)->registered($user->id);
        Http::assertSentCount(1); Mail::assertSent(Common::class,1);
        $response=$this->get($review->publicUrl())->assertOk()->assertSee('Review Shop')->assertSee('name="rating"',false)->assertDontSee($user->email)->assertDontSee($user->phone_number);
        $this->assertStringContainsString('no-store',$response->headers->get('Cache-Control'));
        $this->assertSame('no-referrer',$response->headers->get('Referrer-Policy'));
    }
    public function test_rating_is_required_bounded_and_cannot_be_overwritten_even_by_another_customer_record() {
        $user=$this->customer();$review=BusinessReview::firstOrFail();
        foreach ([null,0,6,'bad',1.5,['5']] as $rating) $this->post($review->publicUrl(),['rating'=>$rating])->assertSessionHasErrors('rating');
        $this->assertNull($review->fresh()->rating);
        $this->post($review->publicUrl(),['rating'=>4,'owner_id'=>999,'customer_id'=>999])->assertRedirect($review->publicUrl());
        $this->post($review->publicUrl(),['rating'=>1])->assertRedirect($review->publicUrl());
        $this->assertSame(4,$review->fresh()->rating);
        $rated=$review->fresh()->rated_at;
        $this->customer('+90 555 123 45 67',null);
        $this->assertSame(1,BusinessReview::count());
        Mail::assertSent(Common::class,1);
        $this->assertEquals($rated,$review->fresh()->rated_at);
        $this->get($review->publicUrl())->assertOk()->assertSee('4 / 5')->assertDontSee('name="rating"',false);
        $this->get('/degerlendir/'.str_repeat('0',64))->assertNotFound();
        $this->post('/degerlendir/'.str_repeat('0',64),['rating'=>5])->assertNotFound();
    }
    public function test_customer_can_rate_a_different_business_and_deletion_does_not_reset_existing_identity() {
        $user=$this->customer();$review=BusinessReview::firstOrFail();
        $this->post($review->publicUrl(),['rating'=>5]);
        Client::where('user_id',$user->id)->delete();$user->delete();
        $this->customer('05551234567',null);
        $this->assertSame(1,BusinessReview::count());
        $other=User::create(['name'=>'Other Shop','email'=>'review-other@example.test','password'=>'x','type'=>'owner']);
        $this->customer('5551234567',null,$other);
        $this->assertSame(2,BusinessReview::count());
        $this->assertSame(5,$review->fresh()->rating);
    }
    public function test_disabled_channels_and_rollback_never_send_messages() {
        $this->smsOn(); AppointmentSmsSetting::central()->update(['enabled'=>false]);
        DB::table('settings')->insert(['parent_id'=>$this->admin->id,'type'=>'smtp','name'=>'EMAIL_ENABLED','value'=>'0']);
        $this->customer();
        Mail::assertNothingSent(); Http::assertNothingSent();
        $review=BusinessReview::firstOrFail();
        $this->assertSame('pending',$review->sms_status); $this->assertSame('pending',$review->email_status);
        DB::beginTransaction();$this->customer('5551234568',null);DB::rollBack();
        $this->assertSame(1,BusinessReview::count());
        $this->assertSame(1,Client::count());
        Mail::assertNothingSent(); Http::assertNothingSent();
        $this->post($review->publicUrl(),['rating'=>3])->assertRedirect();
        $this->assertSame(3,$review->fresh()->rating);
    }
    public function test_customer_only_form_triggers_invitation_and_provider_failure_does_not_break_registration() {
        $this->smsOn(); Http::swap(new \Illuminate\Http\Client\Factory()); Http::fake(['*'=>Http::response(['response'=>['status'=>['code'=>402]]],400)]);
        $this->actingAs($this->owner)->post(route('client.simple.store'),['name'=>'Simple Client','phone_number'=>'5551234567'])->assertRedirect(route('client.index'))->assertSessionHasNoErrors();
        $this->assertSame(1,Client::count());$review=BusinessReview::firstOrFail();
        $this->assertSame('failed',$review->sms_status);$this->assertSame('402',$review->sms_error);
        $this->assertSame('pending',$review->email_status);Mail::assertNothingSent();
        app(BusinessReviews::class)->registered($review->customer_id);Http::assertSentCount(1);
    }
    public function test_vehicle_portal_uses_the_same_review_for_multiple_vehicles_and_does_not_send_on_view() {
        $user=$this->customer();$review=BusinessReview::firstOrFail();
        $role=\Spatie\Permission\Models\Role::create(['name'=>'client','guard_name'=>'web','parent_id'=>$this->owner->id]);
        $user->assignRole($role);
        $role->syncPermissions([]);
        $this->assertFalse(\Illuminate\Support\Facades\Auth::check());
        $this->get($review->publicUrl())->assertOk();
        for ($i=1;$i<=2;$i++) {
            $vehicle=app(VehicleQrPool::class)->createVehicle($this->owner->id,null,fn()=>Vehicle::create(['vehicle_id'=>$i,'parent_id'=>$this->owner->id,'client'=>$user->id,'type'=>1,'brand'=>1,'license_plate'=>'34 TEST '.$i,'status'=>'active']));
            $this->get($vehicle->qrCode->publicUrl())->assertOk()->assertSee('İşletmeyi değerlendir')->assertSee($review->publicUrl(),false);
        }
        $this->assertSame(1,BusinessReview::count());Http::assertNothingSent();Mail::assertSent(Common::class,1);
        $this->post($review->publicUrl(),['rating'=>5]);
        $this->get($vehicle->qrCode->publicUrl())->assertOk()->assertSee('5 / 5 puan verdiniz');
    }
    public function test_owner_scope_admin_settings_and_public_scores() {
        $user=$this->customer();$review=BusinessReview::firstOrFail();$this->post($review->publicUrl(),['rating'=>4]);
        $other=User::create(['name'=>'Foreign Shop','email'=>'foreign-review@example.test','password'=>'x','type'=>'owner']);
        $this->customer('5551234568',null,$other);
        $this->actingAs($this->owner)->get(route('reviews.index',['owner_id'=>$other->id]))->assertOk()->assertSee('1 değerlendirme')->assertDontSee('Foreign Shop');
        $this->actingAs($user)->get(route('reviews.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('reviews.index'))->assertOk()->assertSee('Foreign Shop')->assertSee('Review Shop');
        $this->get(route('notification.index'))->assertOk()->assertSee('Müşteriye işletme değerlendirme bağlantısı');
        $this->get(route('appointments.sms-settings'))->assertOk()->assertSee('name="review_template"',false);
        $template=BusinessReviews::template($this->admin);
        $this->put(route('notification.update',$template->id),['subject'=>'Test','message'=>'Missing link','enabled_email'=>1])->assertSessionHas('error');
        $profile=new AppointmentProfile();$profile->forceFill(['owner_id'=>$this->owner->id,'public_id'=>(string)\Illuminate\Support\Str::uuid(),'display_name'=>'Review Shop','weekly_hours'=>[],'is_active'=>true,'directory_visible'=>true,'directory_region'=>'54'])->save();
        $service=BookingService::first();
        if (!$service) $service=BookingService::create(['name'=>'Test service','vehicle_types'=>['otomobil'],'is_active'=>true]);
        $vehicle=$service->vehicle_types[0];
        DB::table('booking_offerings')->insert(['appointment_profile_id'=>$profile->id,'vehicle_type'=>$vehicle,'booking_service_id'=>$service->id]);
        auth()->logout();
        $this->get(route('directory.index',['region'=>'54','vehicle'=>$vehicle,'service'=>$service->id]))->assertOk()->assertSee('4,0')->assertSee('1 değerlendirme')->assertDontSee($review->token);
        $this->get(route('booking.show',$profile->public_id))->assertOk()->assertSee('4,0 / 5');
    }
    public function test_channel_specific_switches_and_template_validation_are_central() {
        $this->smsOn(); AppointmentSmsSetting::central()->update(['review_sms_enabled'=>false]);
        BusinessReviews::template($this->admin)->update(['enabled_email'=>false]);
        $this->customer(); Http::assertNothingSent();Mail::assertNothingSent();
        $this->actingAs($this->owner)->get(route('appointments.sms-settings'))->assertForbidden();
        $data=['enabled'=>0,'brand'=>'sanayirandevu.com','daily_limit'=>100,
            'verification_template'=>'{kod} {isletme} {marka}', 'approval_template'=>'{isletme} {tarih} {saat} {marka}',
            'review_template'=>'Bağlantı yok'];
        $this->actingAs($this->admin)->post(route('appointments.sms-settings.save'),$data)->assertSessionHasErrors('review_template');
        $data['review_template']='{isletme} değerlendir: {link} {marka}';$data['review_sms_enabled']=1;
        $this->post(route('appointments.sms-settings.save'),$data)->assertSessionHasNoErrors();
        $this->assertSame($data['review_template'],AppointmentSmsSetting::central()->review_template);
        $this->assertTrue(AppointmentSmsSetting::central()->review_sms_enabled);
        $template=BusinessReviews::template($this->admin);
        $this->put(route('notification.update',$template->id),['subject'=>'Değerlendirme','message'=>'<p>{review_link}</p>','enabled_email'=>1])->assertSessionHas('success');
        $this->assertSame('<p>{review_link}</p>',$template->fresh()->message);
    }
    public function test_landing_page_keeps_existing_mockups_and_includes_new_features() {
        $this->get(route('marketing.index'))->assertOk()->assertSee('desktop-device',false)->assertSee('phone-device',false)
            ->assertSee('Müşteri değerlendirmeleri')->assertSee('Destek &amp; bildirimler',false)
            ->assertSee('Abonelik ve kullanım.')->assertSee('İşlem geçmişi ve erişim.')
            ->assertSee('SMTP veya SMS hesabı kurması gerekmez');
    }

}
