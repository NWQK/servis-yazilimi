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
        foreach(['manage client','show client','create client','manage vehicle','show vehicle','manage service','show service'] as $permission){
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
        foreach(['session','dashboard','customers','vehicles','services'] as $path)$this->callApi('GET',$path,$pending)->assertForbidden();
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
