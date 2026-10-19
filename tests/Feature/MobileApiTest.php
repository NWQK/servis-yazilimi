<?php
namespace Tests\Feature;
use App\Models\{User,Client,Service,Vehicle,Invoice};
use Illuminate\Support\Facades\{DB,Hash,Mail,Http};
use Spatie\Permission\Models\Permission;
use Tests\TestCase;
class MobileApiTest extends TestCase {
    private User $owner;
    protected function setUp(): void {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array','sanctum.guard'=>[]]);
        DB::purge('sqlite');Mail::fake();Http::preventStrayRequests();
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        $this->owner=$this->account('owner');
        foreach(['manage client','show client','create client','manage vehicle','show vehicle','create vehicle','manage service','show service'] as $permission){
            Permission::firstOrCreate(['name'=>$permission,'guard_name'=>'web']);$this->owner->givePermissionTo($permission);
        }
    }
    private function account(string $type,?int $parent=null): User {
        $u=User::create(['name'=>'Sentetik işletme','email'=>uniqid().'@example.test','password'=>Hash::make('Test-password-123'),'type'=>$type,'parent_id'=>$parent??0,'is_active'=>1]);
        $u->email_verified_at=now();$u->save();return $u;
    }
    private function login(?User $user=null): string {
        auth()->forgetGuards();
        return $this->postJson('/api/v1/auth/login',['email'=>($user??$this->owner)->email,'password'=>'Test-password-123','device_name'=>'Test Android'])->assertOk()->json('token');
    }
    private function callApi(string $method,string $url,string $token,array $data=[],array $headers=[]) {
        auth()->forgetGuards();
        return $this->json($method,'/api/v1/'.$url,$data,['Authorization'=>'Bearer '.$token]+$headers);
    }
    private function appointment(User $owner, string $status = 'pending') {
        $profile = app(\App\Services\AppointmentBooking::class)->profileForOwner($owner->id);
        return \App\Models\Appointment::create([
            'appointment_profile_id'=>$profile->id, 'public_token'=>\Illuminate\Support\Str::random(64),
            'request_key'=>(string) \Illuminate\Support\Str::uuid(), 'customer_name'=>'Sentetik Randevu',
            'phone'=>'5550000000', 'phone_verified_at'=>now(), 'starts_at'=>now()->addDay(),
            'ends_at'=>now()->addDay()->addHour(), 'status'=>$status,
            'pending_expires_at'=>now()->addHours(2), 'cancelled_by_customer'=>$status === 'cancelled',
            'cancelled_at'=>$status === 'cancelled' ? now() : null,
        ]);
    }
    public function test_notification_isolation_unread_count_and_idempotent_read(): void {
        $mine=$this->appointment($this->owner);
        $cancelled=$this->appointment($this->owner,'cancelled');
        $foreign=$this->appointment($this->account('owner'));
        $expired=$this->appointment($this->owner);$expired->update(['pending_expires_at'=>now()->subMinute()]);
        $this->appointment($this->owner,'approved');
        $token=$this->login();
        $this->callApi('GET','notifications',$token)->assertOk()->assertJsonCount(2,'data')->assertJsonPath('meta.unread_count',2);
        $this->callApi('GET','notifications?per_page=1',$token)->assertJsonCount(1,'data')->assertJsonPath('meta.has_more',true);
        $this->callApi('GET','appointments/'.$foreign->id,$token)->assertNotFound();
        $this->callApi('POST','notifications/'.$foreign->id.'/read',$token,['event'=>'pending'])->assertNotFound();
        $this->callApi('POST','notifications/'.$mine->id.'/read',$token,['event'=>'cancelled'])->assertConflict();
        for($i=0;$i<2;$i++) $this->callApi('POST','notifications/'.$mine->id.'/read',$token,['event'=>'pending'])->assertNoContent();
        $this->callApi('GET','notifications',$token)->assertJsonPath('meta.unread_count',1);
        $this->assertSame(1,DB::table('mobile_notification_reads')->count());
        $this->assertSame('pending',$mine->fresh()->status);
        $this->assertNull($cancelled->fresh()->cancellation_read_at);
        $this->callApi('GET','appointments/'.$mine->id,$token)->assertOk()->assertJsonMissingPath('data.public_token');
        $this->owner->recovery_version++;$this->owner->save();
        $token=$this->login();
        $this->callApi('GET','notifications',$token)->assertJsonPath('meta.unread_count',2);
        Mail::assertNothingSent();
    }
    public function test_staff_cannot_read_appointment_notifications(): void {
        $mine=$this->appointment($this->owner);
        $employee=$this->account('employee',$this->owner->id);$token=$this->login($employee);
        $this->callApi('GET','notifications',$token)->assertOk()->assertJsonPath('meta.available',false)->assertJsonCount(0,'data');
        $this->callApi('GET','appointments/'.$mine->id,$token)->assertForbidden();
        $this->callApi('POST','notifications/'.$mine->id.'/read',$token,['event'=>'pending'])->assertForbidden();
    }
    public function test_device_registration_outbox_disabled_delivery_and_logout_revocation(): void {
        $token=$this->login();$registration=['fid'=>str_repeat('synthetic-device-',8),'platform'=>'android'];
        for($i=0;$i<2;$i++) $this->callApi('PUT','push-device',$token,$registration)->assertNoContent();
        $this->assertSame(1,DB::table('mobile_push_devices')->count());
        $this->assertNotSame($registration['fid'],DB::table('mobile_push_devices')->value('encrypted_token'));
        $appointment=$this->appointment($this->owner);
        $id=DB::table('mobile_push_outbox')->value('id');$this->assertNotNull($id);
        config(['mobile_push.enabled'=>false]);
        app(\App\Services\MobilePushDelivery::class)->deliver($id);
        $this->assertSame('pending',DB::table('mobile_push_outbox')->value('status'));Http::assertNothingSent();
        config(['mobile_push.enabled'=>true]);
        $mock=\Mockery::mock(\App\Services\FirebasePush::class);
        $mock->shouldReceive('send')->once()->with($registration['fid'],$appointment->id,'pending')->andReturn('sent');
        $this->app->instance(\App\Services\FirebasePush::class,$mock);
        app(\App\Services\MobilePushDelivery::class)->deliver($id);
        app(\App\Services\MobilePushDelivery::class)->deliver($id);
        $this->assertSame('sent',DB::table('mobile_push_outbox')->value('status'));
        $this->callApi('POST','auth/logout',$token)->assertNoContent();
        $this->assertSame(0,DB::table('mobile_push_devices')->count());
        $this->assertSame(0,DB::table('mobile_push_outbox')->count());
    }
    public function test_revoked_access_blocks_queued_push_and_other_session_replaces_installation(): void {
        $one=$this->login();$two=$this->login();$data=['fid'=>str_repeat('synthetic-installation',8),'platform'=>'android'];
        $this->callApi('PUT','push-device',$one,$data)->assertNoContent();
        $this->callApi('PUT','push-device',$two,$data)->assertNoContent();
        $this->assertSame(1,DB::table('mobile_push_devices')->count());
        $this->appointment($this->owner);$id=DB::table('mobile_push_outbox')->value('id');
        $this->owner->session_epoch++;$this->owner->save();
        config(['mobile_push.enabled'=>true]);
        $mock=\Mockery::mock(\App\Services\FirebasePush::class);$mock->shouldNotReceive('send');
        $this->app->instance(\App\Services\FirebasePush::class,$mock);
        app(\App\Services\MobilePushDelivery::class)->deliver($id);
        $this->assertSame('cancelled',DB::table('mobile_push_outbox')->value('status'));
        $this->callApi('GET','notifications',$two)->assertUnauthorized();
    }
    public function test_fcm_http_payload_uses_fid_and_contains_no_customer_information(): void {
        $credentials=['project_id'=>'synthetic-fcm-project','client_email'=>'synthetic@example.test','private_key'=>'synthetic-not-a-private-key'];
        $path=tempnam(sys_get_temp_dir(),'mobile-fcm-test-');file_put_contents($path,json_encode($credentials));
        try {
            config(['mobile_push.enabled'=>true,'mobile_push.project_id'=>$credentials['project_id'],'mobile_push.credentials_file'=>$path]);
            // Isolate transport testing from OAuth; no real key or Google request is used.
            \Illuminate\Support\Facades\Cache::put('mobile-fcm-oauth:'.hash('sha256',json_encode($credentials)),\Illuminate\Support\Facades\Crypt::encryptString('synthetic-oauth-token'),60);
            Http::fake(['https://fcm.googleapis.com/*'=>Http::response(['name'=>'projects/synthetic/messages/1'],200)]);
            $this->assertSame('sent',app(\App\Services\FirebasePush::class)->send('synthetic-fid-identifier',42,'pending'));
            Http::assertSent(fn($r)=>$r['message']['fid']==='synthetic-fid-identifier'
                && $r['message']['data']===['appointment_id'=>'42','event'=>'pending']
                && $r['message']['android']['priority']==='HIGH'
                && $r['message']['android']['notification']['channel_id']==='appointments_v1'
                && $r['message']['android']['notification']['icon']==='ic_stat_sanayirandevu'
                && $r['message']['android']['notification']['default_sound']===true
                && !isset($r['message']['token']) && count($r['message']['data'])===2
                && $r['message']['android']['notification']['tag']==='appointment-42-pending');
            Http::assertSentCount(1);
        } finally { unlink($path); }
    }
    public function test_push_status_reports_disabled_setup_without_network_or_sensitive_output(): void {
        config(['mobile_push.enabled'=>false,'mobile_push.credentials_file'=>null]);
        $this->artisan('mobile:push-status')
            ->expectsOutput('Gönderim: kapalı')
            ->expectsOutput('Sunucu kimliği: eksik veya geçersiz')
            ->expectsOutput('Kayıtlı cihaz: 0')
            ->expectsOutput('pending: 0')
            ->assertExitCode(1);
        $this->assertSame(0,DB::table('mobile_push_outbox')->count());
        Http::assertNothingSent();
    }
    public function test_disabled_push_worker_records_heartbeat_without_sending(): void {
        config(['mobile_push.enabled'=>false]);
        $this->artisan('mobile:push-work')->assertExitCode(0);
        $this->assertSame('disabled',DB::table('system_health')->where('component','mobile_push_worker')->value('code'));
        Http::assertNothingSent();
    }
    public function test_pending_push_waits_for_maintenance_and_recovery_without_network_calls(): void {
        $token=$this->login();$this->callApi('PUT','push-device',$token,['fid'=>str_repeat('synthetic-waiting',4),'platform'=>'android'])->assertNoContent();
        $this->appointment($this->owner);$id=DB::table('mobile_push_outbox')->value('id');
        config(['mobile_push.enabled'=>true]);
        $mock=\Mockery::mock(\App\Services\FirebasePush::class);$mock->shouldNotReceive('send');
        $this->app->instance(\App\Services\FirebasePush::class,$mock);
        \App\Services\PlatformMaintenance::set(true);
        app(\App\Services\MobilePushDelivery::class)->deliver($id);
        $this->assertSame('pending',DB::table('mobile_push_outbox')->value('status'));
        \App\Services\PlatformMaintenance::set(false);
        DB::table('business_recovery_locks')->insert(['owner_id'=>$this->owner->id,'recovery_id'=>(string)\Illuminate\Support\Str::uuid(),'created_at'=>now()]);
        app(\App\Services\MobilePushDelivery::class)->deliver($id);
        $this->assertSame('pending',DB::table('mobile_push_outbox')->value('status'));
        Http::assertNothingSent();
    }
    public function test_appointment_list_filters_virtual_expiry_and_tenant_isolation(): void {
        $mine=$this->appointment($this->owner);$mine->update(['license_plate'=>'34 API 18']);
        $expired=$this->appointment($this->owner);$expired->update(['starts_at'=>now()->subMinute()]);
        $this->appointment($this->owner,'approved');$foreign=$this->appointment($this->account('owner'));
        $token=$this->login();
        $this->callApi('GET','appointments?per_page=1',$token)->assertOk()->assertJsonCount(1,'data')->assertJsonPath('meta.has_more',true);
        $this->callApi('GET','appointments?status=pending',$token)->assertJsonCount(1,'data')->assertJsonPath('data.0.id',$mine->id);
        $this->callApi('GET','appointments?status=expired',$token)->assertJsonCount(1,'data')->assertJsonPath('data.0.id',$expired->id)->assertJsonPath('data.0.status','expired');
        $this->assertSame('pending',$expired->fresh()->status);
        $this->callApi('GET','notifications',$token)->assertJsonPath('meta.unread_count',1);
        $this->callApi('GET','appointments?q=API',$token)->assertJsonCount(1,'data')->assertJsonPath('data.0.id',$mine->id);
        $this->callApi('GET','appointments?date='.now()->addDays(10)->toDateString(),$token)->assertJsonCount(0,'data');
        $this->callApi('GET','appointments?status=forged',$token)->assertUnprocessable();
        $this->callApi('GET','appointments?date=invalid',$token)->assertUnprocessable();
        $this->callApi('GET','appointments?per_page=500',$token)->assertUnprocessable();
        $this->callApi('GET','appointments/'.$foreign->id,$token)->assertNotFound();
        $staff=$this->login($this->account('employee',$this->owner->id));
        $this->callApi('GET','appointments',$staff)->assertForbidden();
    }
    public function test_appointment_status_idempotency_stale_version_and_time_rules(): void {
        $mine=$this->appointment($this->owner);$token=$this->login();
        $original=$this->callApi('GET','appointments/'.$mine->id,$token)->assertOk()->json('data');
        $this->assertContains('approved',$original['actions']);
        $key=['Idempotency-Key'=>(string)\Illuminate\Support\Str::uuid()];
        $payload=['status'=>'approved','expected_version'=>$original['version']];
        $approved=$this->callApi('POST','appointments/'.$mine->id.'/status',$token,$payload,$key)->assertOk()->assertJsonPath('meta.replayed',false)->json('data');
        $this->assertSame(['cancelled'],$approved['actions']);
        $this->callApi('POST','appointments/'.$mine->id.'/status',$token,$payload,$key)->assertOk()->assertJsonPath('meta.replayed',true);
        $this->callApi('POST','appointments/'.$mine->id.'/status',$token,['status'=>'rejected','expected_version'=>$original['version']],$key)->assertConflict();
        $this->callApi('POST','appointments/'.$mine->id.'/status',$token,['status'=>'cancelled','expected_version'=>$original['version']],['Idempotency-Key'=>(string)\Illuminate\Support\Str::uuid()])->assertConflict();
        $this->callApi('POST','appointments/'.$mine->id.'/status',$token,['status'=>'completed','expected_version'=>$approved['version']],['Idempotency-Key'=>(string)\Illuminate\Support\Str::uuid()])->assertUnprocessable();
        $this->callApi('POST','appointments/'.$mine->id.'/status',$token,['status'=>'cancelled','expected_version'=>$approved['version']],['Idempotency-Key'=>(string)\Illuminate\Support\Str::uuid()])->assertOk()->assertJsonPath('data.status','cancelled');
        // An old approval retry must never resurrect a subsequently cancelled booking.
        $this->callApi('POST','appointments/'.$mine->id.'/status',$token,$payload,$key)->assertOk()->assertJsonPath('data.status','cancelled')->assertJsonPath('meta.replayed',true);
        $this->assertSame(2,DB::table('mobile_appointment_operations')->count());
        Mail::assertNothingSent();Http::assertNothingSent();
    }
    public function test_appointment_completion_and_status_permission_guards(): void {
        $past=$this->appointment($this->owner,'approved');$past->update(['starts_at'=>now()->subHour(),'ends_at'=>now()]);
        $token=$this->login();$path='appointments/'.$past->id;
        $view=$this->callApi('GET',$path,$token)->assertOk()->json('data');
        $this->assertContains('completed',$view['actions']);$this->assertContains('no_show',$view['actions']);
        $payload=['status'=>'completed','expected_version'=>$view['version']];
        $this->callApi('POST',$path.'/status',$token,$payload)->assertUnprocessable();
        $staff=$this->login($this->account('employee',$this->owner->id));
        $this->callApi('POST',$path.'/status',$staff,$payload,['Idempotency-Key'=>(string)\Illuminate\Support\Str::uuid()])->assertForbidden();
        $foreign=$this->appointment($this->account('owner'));
        $this->callApi('POST','appointments/'.$foreign->id.'/status',$token,$payload,['Idempotency-Key'=>(string)\Illuminate\Support\Str::uuid()])->assertNotFound();
        $this->callApi('POST',$path.'/status',$token,$payload,['Idempotency-Key'=>(string)\Illuminate\Support\Str::uuid()])->assertOk()->assertJsonPath('data.status','completed')->assertJsonCount(0,'data.actions');
    }
    private function vehiclePayload(User $owner): array {
        $customer=$this->account('client',$owner->id);
        $type=\App\Models\VehicleType::create(['parent_id'=>$owner->id,'type'=>'Sentetik Otomobil']);
        $brand=\App\Models\VehicleBrand::create(['parent_id'=>$owner->id,'type'=>$type->id,'name'=>'Örnek Marka']);
        return ['client'=>$customer->id,'type'=>$type->id,'brand'=>$brand->id,'license_plate'=>'34 API 2026','chassis_no'=>'TESTVN12345678901','mileage'=>'15000'];
    }
    public function test_same_second_customer_edit_invalidates_appointment_approval(): void {
        $this->freezeTime();
        $mine=$this->appointment($this->owner);$token=$this->login();
        $version=$this->callApi('GET','appointments/'.$mine->id,$token)->assertOk()->json('data.version');
        // Web edits can have the same second-resolution updated_at timestamp.
        $mine->update(['customer_name'=>'Changed customer','notes'=>'Different requested work']);
        $this->callApi('POST','appointments/'.$mine->id.'/status',$token,
            ['status'=>'approved','expected_version'=>$version],['Idempotency-Key'=>(string)\Illuminate\Support\Str::uuid()])->assertConflict();
        $this->assertSame('pending',$mine->fresh()->status);
    }
    public function test_vehicle_search_matches_displayed_tenant_catalog_label(): void {
        $data=$this->vehiclePayload($this->owner);$token=$this->login();
        $this->callApi('POST','vehicles',$token,$data,['Idempotency-Key'=>(string)\Illuminate\Support\Str::uuid()])->assertCreated();
        $this->callApi('GET','vehicles?q='.urlencode('Örnek Marka'),$token)->assertOk()->assertJsonCount(1,'data');
    }
    public function test_customer_replay_after_archiving_does_not_report_a_live_record(): void {
        $token=$this->login();$key=['Idempotency-Key'=>(string)\Illuminate\Support\Str::uuid()];
        $data=['name'=>'Archived customer','phone_number'=>'5551234567'];
        $id=$this->callApi('POST','customers',$token,$data,$key)->assertCreated()->json('data.id');
        DB::table('users')->where('id',$id)->update(['client_archived_at'=>now()]);
        $this->callApi('POST','customers',$token,$data,$key)->assertConflict();
        $this->assertSame(1,Client::count());
    }
    public function test_successful_mobile_login_updates_demo_activity_but_pending_2fa_does_not(): void {
        $old=now()->subDays(29)->toDateTimeString();
        DB::table('users')->where('id',$this->owner->id)->update(['last_business_login_at'=>$old]);
        $this->login();
        $this->assertSame(now()->toDateTimeString(),DB::table('users')->where('id',$this->owner->id)->value('last_business_login_at'));
        DB::table('users')->where('id',$this->owner->id)->update(['last_business_login_at'=>$old,'twofa_secret'=>'JBSWY3DPEHPK3PXP']);
        $pending=$this->login();
        $this->assertSame($old,DB::table('users')->where('id',$this->owner->id)->value('last_business_login_at'));
        $otp=(new \PragmaRX\Google2FA\Google2FA)->getCurrentOtp('JBSWY3DPEHPK3PXP');
        $this->callApi('POST','auth/2fa',$pending,['code'=>$otp])->assertOk();
        $this->assertSame(now()->toDateTimeString(),DB::table('users')->where('id',$this->owner->id)->value('last_business_login_at'));
    }
    public function test_vehicle_creation_uses_shared_qr_rules_and_replays_before_duplicate_vin_validation(): void {
        $data=$this->vehiclePayload($this->owner);$token=$this->login();$key=['Idempotency-Key'=>(string)\Illuminate\Support\Str::uuid()];
        $id=$this->callApi('POST','vehicles',$token,$data+['parent_id'=>9999,'status'=>'forged'],$key)->assertCreated()->json('data.id');
        $this->assertSame($this->owner->id,Vehicle::findOrFail($id)->parent_id);
        $this->assertNotSame('forged',Vehicle::findOrFail($id)->status);
        $qrCount=\App\Models\VehicleQrCode::count();
        $this->callApi('POST','vehicles',$token,$data,$key)->assertOk()->assertJsonPath('data.id',$id)->assertJsonPath('meta.replayed',true);
        $this->assertSame(1,Vehicle::count());$this->assertSame($qrCount,\App\Models\VehicleQrCode::count());
        $this->assertSame(1,\App\Models\VehicleQrCode::where('vehicle_id',$id)->count());
        $this->assertSame(0,Service::count());$this->assertSame(0,Invoice::count());
        $changed=$data;$changed['license_plate']='34 CHANGED';
        $this->callApi('POST','vehicles',$token,$changed,$key)->assertConflict();
        $this->callApi('POST','vehicles',$token,$data,['Idempotency-Key'=>(string)\Illuminate\Support\Str::uuid()])->assertUnprocessable();
        Vehicle::find($id)->delete();
        $this->callApi('POST','vehicles',$token,$data,$key)->assertConflict();
        $this->assertSame(1,Vehicle::withTrashed()->count());Mail::assertNothingSent();Http::assertNothingSent();
    }
    public function test_vehicle_lookups_and_writes_are_permission_and_tenant_scoped(): void {
        $mine=$this->vehiclePayload($this->owner);$foreign=$this->vehiclePayload($this->account('owner'));$token=$this->login();
        $this->callApi('GET','lookups/vehicle-customers',$token)->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.id',$mine['client']);
        $this->callApi('GET','lookups/vehicle-types',$token)->assertJsonCount(1,'data')->assertJsonPath('data.0.id',$mine['type']);
        $this->callApi('GET','lookups/vehicle-brands?type='.$foreign['type'],$token)->assertUnprocessable();
        $this->callApi('GET','lookups/vehicle-brands?type='.$mine['type'],$token)->assertJsonCount(1,'data')->assertJsonPath('data.0.id',$mine['brand']);
        $this->callApi('POST','vehicles',$token,$foreign,['Idempotency-Key'=>(string)\Illuminate\Support\Str::uuid()])->assertUnprocessable();
        $staff=$this->login($this->account('employee',$this->owner->id));
        $this->callApi('GET','lookups/vehicle-types',$staff)->assertForbidden();
        $this->callApi('POST','vehicles',$staff,$mine,['Idempotency-Key'=>(string)\Illuminate\Support\Str::uuid()])->assertForbidden();
        $this->assertSame(0,Vehicle::count());
    }
    public function test_vehicle_capacity_blocks_creation_before_allocating_qr(): void {
        $plan=\App\Models\Subscription::whereNotNull('vehicle_limit')->firstOrFail();$plan->vehicle_limit=0;$plan->save();
        $this->owner->subscription=$plan->id;$this->owner->save();
        $data=$this->vehiclePayload($this->owner);$token=$this->login();
        $this->callApi('POST','vehicles',$token,$data,['Idempotency-Key'=>(string)\Illuminate\Support\Str::uuid()])->assertUnprocessable();
        $this->assertSame(0,Vehicle::count());$this->assertSame(0,\App\Models\VehicleQrCode::count());
    }
    public function test_login_session_expiration_and_device_revocation(): void {
        $one=$this->login();$two=$this->login();
        $this->callApi('GET','session',$one)->assertOk()->assertJsonPath('data.business.id',$this->owner->id)->assertJsonMissingPath('data.user.password');
        $this->callApi('GET','devices',$one)->assertOk()->assertJsonCount(2,'data');
        $this->callApi('POST','auth/logout',$one)->assertNoContent();
        $this->callApi('GET','session',$one)->assertUnauthorized();
        $this->callApi('GET','session',$two)->assertOk();
        $this->travel(31)->days();$this->callApi('GET','session',$two)->assertUnauthorized();
    }
    public function test_pending_two_factor_cannot_access_data_and_code_is_single_use(): void {
        $this->owner->twofa_secret='JBSWY3DPEHPK3PXP';$this->owner->save();
        $pending=$this->login();
        foreach(['session','dashboard','customers','vehicles','services','notifications','appointments'] as $path)$this->callApi('GET',$path,$pending)->assertForbidden();
        $this->callApi('PUT','push-device',$pending,['fid'=>str_repeat('synthetic-2fa',4),'platform'=>'android'])->assertForbidden();
        $this->callApi('POST','auth/2fa',$pending,['code'=>'invalid'])->assertUnprocessable();
        $otp=(new \PragmaRX\Google2FA\Google2FA)->getCurrentOtp($this->owner->twofa_secret);
        $full=$this->callApi('POST','auth/2fa',$pending,['code'=>$otp])->assertOk()->json('token');
        $this->callApi('GET','session',$pending)->assertUnauthorized();
        $this->callApi('GET','session',$full)->assertOk();
        $pending=$this->login();$this->callApi('POST','auth/2fa',$pending,['code'=>$otp])->assertUnprocessable();
    }
    public function test_required_setup_and_customer_login_are_denied(): void {
        $this->owner->twofa_required=true;$this->owner->save();
        $this->postJson('/api/v1/auth/login',['email'=>$this->owner->email,'password'=>'Test-password-123','device_name'=>'Test'])->assertForbidden();
        $this->assertSame(0,$this->owner->tokens()->count());
        $client=$this->account('client',$this->owner->id);
        $this->postJson('/api/v1/auth/login',['email'=>$client->email,'password'=>'Test-password-123','device_name'=>'Test'])->assertUnprocessable();
    }
    public function test_customer_creation_is_idempotent_and_has_no_business_side_effects(): void {
        $token=$this->login();$headers=['Idempotency-Key'=>(string)\Illuminate\Support\Str::uuid()];
        $data=['name'=>'Yeni müşteri','phone_number'=>'5551234567','parent_id'=>987,'type'=>'owner'];
        $id=$this->callApi('POST','customers',$token,$data,$headers)->assertCreated()->json('data.id');
        $this->callApi('POST','customers',$token,$data,$headers)->assertOk()->assertJsonPath('data.id',$id);
        $this->callApi('POST','customers',$token,['name'=>'Değişti','phone_number'=>'5551234567'],$headers)->assertConflict();
        $this->assertSame(1,Client::count());$this->assertSame($this->owner->id,User::find($id)->parent_id);
        foreach([Service::class,Vehicle::class,Invoice::class] as $model)$this->assertSame(0,$model::count());
        $this->callApi('GET','customers?q=Yeni',$token)->assertOk()->assertJsonCount(1,'data');
        $this->callApi('GET','customers/'.$id,$token)->assertOk()->assertJsonPath('data.name','Yeni müşteri');
        $this->callApi('POST','customers',$token,['name'=>'No key','phone_number'=>'5551234567'])->assertUnprocessable();
        Mail::assertNothingSent();
    }
    public function test_tenant_isolation_search_and_pagination(): void {
        $other=$this->account('owner');$foreign=$this->account('client',$other->id);
        for($i=0;$i<23;$i++)$this->account('client',$this->owner->id);
        $token=$this->login();
        $this->callApi('GET','customers',$token)->assertOk()->assertJsonCount(20,'data')->assertJsonPath('meta.has_more',true);
        $this->callApi('GET','customers?page=2',$token)->assertJsonCount(3,'data')->assertJsonPath('meta.has_more',false);
        $this->callApi('GET','customers/'.$foreign->id,$token)->assertNotFound();
        $this->callApi('GET','customers?per_page=1000',$token)->assertUnprocessable();
        $this->callApi('GET','customers?_branch=forged',$token)->assertForbidden();
    }
    public function test_staff_permissions_assignment_departure_and_recovery_epoch(): void {
        $employee=$this->account('employee',$this->owner->id);$token=$this->login($employee);
        $this->callApi('GET','customers',$token)->assertForbidden();
        $employee->givePermissionTo(['manage service','show service']);
        $mine=Service::create(['service_id'=>1,'parent_id'=>$this->owner->id,'assign'=>$employee->id,'status'=>'scheduled']);
        $other=Service::create(['service_id'=>2,'parent_id'=>$this->owner->id,'assign'=>$this->owner->id,'status'=>'scheduled']);
        $this->callApi('GET','services',$token)->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.id',$mine->id);
        $this->callApi('GET','services/'.$other->id,$token)->assertNotFound();
        $employee->is_active=0;$employee->session_epoch++;$employee->save();
        $this->callApi('GET','session',$token)->assertUnauthorized();
        $employee->is_active=1;$employee->save();$this->callApi('GET','session',$token)->assertUnauthorized();
        $token=$this->login();$this->owner->recovery_version++;$this->owner->save();
        $this->callApi('GET','session',$token)->assertUnauthorized();
    }
    public function test_maintenance_subscription_recovery_and_password_change(): void {
        $token=$this->login();
        \App\Services\PlatformMaintenance::set(true);$this->callApi('GET','session',$token)->assertStatus(503);
        \App\Services\PlatformMaintenance::set(false);
        $this->owner->subscription_suspended_at=now();$this->owner->save();$this->callApi('GET','session',$token)->assertForbidden();
        $this->owner->subscription_suspended_at=null;$this->owner->save();
        $this->owner->password=Hash::make('changed-password');$this->owner->save();$this->callApi('GET','session',$token)->assertUnauthorized();
    }

    public function test_recovery_blocks_writes_and_own_vehicle_service_details_are_scoped(): void {
        $token=$this->login();$other=$this->account('owner');
        $vehicle=Vehicle::create(['parent_id'=>$this->owner->id,'license_plate'=>'34 TEST 01','model'=>'Sentetik model']);
        $foreign=Vehicle::create(['parent_id'=>$other->id,'license_plate'=>'06 HIDDEN 01']);
        $job=Service::create(['parent_id'=>$other->id,'service_id'=>999,'vehicle'=>$foreign->id,'status'=>'scheduled']);
        $this->callApi('GET','vehicles?q=TEST',$token)->assertOk()->assertJsonCount(1,'data')->assertJsonPath('data.0.model','Sentetik model');
        $this->callApi('GET','vehicles/'.$vehicle->id,$token)->assertOk();
        $this->callApi('GET','vehicles/'.$foreign->id,$token)->assertNotFound();
        $this->callApi('GET','services/'.$job->id,$token)->assertNotFound();
        DB::table('business_recovery_locks')->insert(['owner_id'=>$this->owner->id,'recovery_id'=>(string)\Illuminate\Support\Str::uuid(),'created_at'=>now()]);
        $this->callApi('POST','customers',$token,['name'=>'Blocked','phone_number'=>'5551234567'],['Idempotency-Key'=>(string)\Illuminate\Support\Str::uuid()])->assertStatus(503);
        $this->assertSame(0,Client::count());
    }
    public function test_changed_two_factor_expired_subscription_and_revoked_permissions(): void {
        $token=$this->login();$this->owner->revokePermissionTo('manage client');
        $this->callApi('GET','customers',$token)->assertForbidden()->assertHeader('Cache-Control','no-store, private');
        $this->owner->subscription_expire_date=now()->subDay()->toDateString();$this->owner->save();
        $this->callApi('GET','session',$token)->assertForbidden();
        $this->callApi('POST','auth/logout',$token)->assertNoContent();
        $this->owner->subscription_expire_date=null;$this->owner->save();$token=$this->login();
        $this->owner->twofa_secret='JBSWY3DPEHPK3PXP';$this->owner->save();
        $this->callApi('GET','session',$token)->assertUnauthorized();
    }
    public function test_failed_logins_are_throttled_and_json_without_accept_header(): void {
        for($i=0;$i<5;$i++)$this->postJson('/api/v1/auth/login',['email'=>$this->owner->email,'password'=>'wrong','device_name'=>'Test'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/login',['email'=>$this->owner->email,'password'=>'wrong','device_name'=>'Test'])->assertStatus(429);
        auth()->forgetGuards();$this->get('/api/v1/session')->assertUnauthorized()->assertHeader('Content-Type','application/json');
    }
}
