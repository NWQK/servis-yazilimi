<?php
namespace Tests\Feature;
use App\Models\User;
use App\Services\TwoFactorAuthentication;
use Illuminate\Support\Facades\{DB,Gate,Hash,Mail};
use PragmaRX\Google2FAQRCode\Google2FA;
use Tests\TestCase;

class TwoFactorAuthenticationTest extends TestCase
{
    private $owner;
    protected function setUp():void {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']);DB::purge('sqlite');
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        $this->owner=User::create(['name'=>'Servis','email'=>'twofa-owner@example.test','password'=>Hash::make('password'),'type'=>'owner']);
        Gate::before(fn($u)=>$u->type==='owner'?true:null);$this->actingAs($this->owner);Mail::fake();
        $this->withoutMiddleware(\App\Http\Middleware\XSS::class);
    }
    public function test_setup_qr_has_valid_image_source_stays_stable_and_is_bound_to_user() {
        $service=app(TwoFactorAuthentication::class);$qr=$service->qr($this->owner);$secret=session('2fa_secret');
        $this->assertStringStartsWith('data:image/svg+xml;base64,',$qr);
        $this->assertStringContainsString('<svg',base64_decode(substr($qr,strpos($qr,',')+1)));
        $this->assertSame($qr,$service->qr($this->owner));$this->assertSame($secret,session('2fa_secret'));
        $other=User::create(['name'=>'Other','email'=>'other-twofa@example.test','password'=>'x','type'=>'owner']);
        $service->qr($other);$this->assertNotSame($secret,session('2fa_secret'));
        $html=view('settings.two-factor',['errors'=>new \Illuminate\Support\ViewErrorBag()])->render();$this->assertStringContainsString('inputmode="numeric"',$html);$this->assertStringContainsString('data:image/svg+xml;base64,',$html);
    }
    private function enable() {
        $service=app(TwoFactorAuthentication::class);$secret=$service->setupSecret($this->owner);
        $this->post(route('setting.twofa.enable'),['otp'=>(new Google2FA())->getCurrentOtp($secret)])->assertRedirect(route('setting.index'));
        return $secret;
    }
    public function test_enrollment_encrypts_secret_hides_sensitive_fields_and_shows_recovery_codes_once() {
        $secret=$this->enable();$codes=session('twofa_recovery_codes');$this->assertCount(8,$codes);
        $stored=DB::table('users')->where('id',$this->owner->id)->first();$this->assertNotSame($secret,$stored->twofa_secret);
        $this->assertSame($secret,$this->owner->fresh()->twofa_secret);$this->assertNull(session('2fa_secret'));
        $this->assertArrayNotHasKey('twofa_secret',$this->owner->fresh()->toArray());
        $this->assertArrayNotHasKey('twofa_recovery_codes',$this->owner->fresh()->toArray());
        $this->assertTrue(Hash::check($codes[0],$this->owner->fresh()->twofa_recovery_codes[0]));
        $this->get(route('setting.index'))->assertOk()->assertSee($codes[0]);
        $this->get(route('setting.index'))->assertOk()->assertDontSee($codes[0]);
    }
    public function test_wrong_expired_repeated_and_leading_zero_codes() {
        $g=new Google2FA();$s=$g->generateSecretKey();$this->owner->twofa_secret=$s;$this->owner->save();
        $service=app(TwoFactorAuthentication::class);$this->assertFalse($service->consume($this->owner,'invalid'));
        $expired=$g->oathTotp($s,$g->getTimestamp()-6);$this->assertFalse($service->consume($this->owner,$expired));
        $code=$g->getCurrentOtp($s);$this->assertTrue($service->consume($this->owner,$code));$this->assertFalse($service->consume($this->owner,$code));
        do {$s=$g->generateSecretKey();$code=$g->getCurrentOtp($s);} while($code[0]!=='0');
        $this->owner->twofa_secret=$s;$this->owner->twofa_last_used_at=null;$this->owner->save();
        $this->assertTrue($service->consume($this->owner,$code));
    }
    public function test_recovery_code_is_single_use_and_unlocks_login() {
        $this->enable();$codes=session('twofa_recovery_codes');
        $this->withSession(['2fa_checked'=>false])->get('/dashboard')->assertRedirect(route('otp.show'));
        $this->post(route('otp.check'),['otp'=>strtolower($codes[0])])->assertRedirect('/');
        $this->assertTrue(session('2fa_checked'));$this->assertCount(7,$this->owner->fresh()->twofa_recovery_codes);
        $this->withSession(['2fa_checked'=>false])->post(route('otp.check'),['otp'=>$codes[0]])->assertSessionHas('error');
        $this->assertFalse(session('2fa_checked',false));
    }
    public function test_disable_requires_post_password_and_unused_code() {
        $this->enable();$code=session('twofa_recovery_codes')[0];
        $this->get(route('2fa.disable'))->assertStatus(405);
        $this->post(route('2fa.disable'),['password'=>'wrong','otp'=>$code])->assertSessionHasErrors('otp');
        $this->assertNotNull($this->owner->fresh()->twofa_secret);
        $this->post(route('2fa.disable'),['password'=>'password','otp'=>$code])->assertRedirect(route('setting.index'));
        $this->assertNull($this->owner->fresh()->twofa_secret);$this->assertNull($this->owner->fresh()->twofa_recovery_codes);
    }
    public function test_missing_setup_denied_permissions_and_rate_limit() {
        $this->post(route('setting.twofa.enable'),['otp'=>'123456'])->assertSessionHasErrors('otp');
        $this->assertNull($this->owner->fresh()->twofa_secret);
        \Illuminate\Support\Facades\Cache::flush();
        $this->enable();\Illuminate\Support\Facades\Cache::flush();$this->withSession(['2fa_checked'=>false]);
        for($i=0;$i<6;$i++) $this->post(route('otp.check'),['otp'=>'invalid'])->assertRedirect();
        $this->post(route('otp.check'),['otp'=>'invalid'])->assertStatus(429);
    }
    public function test_can_logout_before_verification_and_legacy_secret_still_works() {
        $s=(new Google2FA())->generateSecretKey();DB::table('users')->where('id',$this->owner->id)->update(['twofa_secret'=>$s]);
        $this->assertSame($s,$this->owner->fresh()->twofa_secret);
        $this->actingAs($this->owner->fresh())->withSession(['2fa_checked'=>false])->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    private function admin() {
        return User::create(['name'=>'Super admin','email'=>'twofa-admin@example.test','password'=>Hash::make('admin-password'),'type'=>'super admin']);
    }
    public function test_only_super_admin_can_manage_owner_authentication_and_password_is_required() {
        $this->get(route('two-factor-admin.index'))->assertForbidden();
        $this->post(route('two-factor-admin.update',$this->owner->id),['action'=>'disable','reason'=>'test','password'=>'password'])->assertForbidden();
        $admin=$this->admin();$this->actingAs($admin);
        $this->get(route('two-factor-admin.index'))->assertOk()->assertSee('Aç / zorunlu tut');
        $this->post(route('two-factor-admin.update',$this->owner->id),['action'=>'require','reason'=>'test','password'=>'wrong'])->assertSessionHasErrors('password');
        $this->assertSame(0,DB::table('two_factor_admin_actions')->count());
        $this->post(route('two-factor-admin.update',$admin->id),['action'=>'require','reason'=>'test','password'=>'admin-password'])->assertNotFound();
    }
    public function test_admin_can_require_setup_and_owner_cannot_bypass_or_disable_it() {
        $admin=$this->admin();$this->actingAs($admin)->post(route('two-factor-admin.update',$this->owner->id),
            ['action'=>'require','reason'=>'Güvenlik politikası','password'=>'admin-password'])->assertRedirect();
        $this->assertTrue($this->owner->fresh()->twofa_required);$this->assertNull($this->owner->fresh()->twofa_secret);
        $this->actingAs($this->owner->fresh())->withSession(['2fa_checked'=>true])->get('/dashboard')->assertRedirect(route('setting.index'));
        $this->get(route('setting.index'))->assertOk()->assertSee('data:image/svg+xml;base64,',false);
        $this->post(route('setting.password'),['current_password'=>'password','password'=>'other-password','password_confirmation'=>'other-password'])->assertRedirect(route('setting.index'));
        $this->enable();$code=session('twofa_recovery_codes')[0];
        $this->post(route('2fa.disable'),['password'=>'password','otp'=>$code])->assertSessionHasErrors('otp');
        $this->assertNotNull($this->owner->fresh()->twofa_secret);
        $this->assertSame('require',DB::table('two_factor_admin_actions')->first()->action);
    }
    public function test_admin_reset_invalidates_old_keys_and_recovery_codes_and_records_support_actions() {
        $old=$this->enable();$oldCode=session('twofa_recovery_codes')[0];$admin=$this->admin();
        $this->actingAs($admin)->post(route('two-factor-admin.update',$this->owner->id),
            ['action'=>'reset','reason'=>'Telefon kaybı','password'=>'admin-password'])->assertRedirect();
        $fresh=$this->owner->fresh();$this->assertNull($fresh->twofa_secret);$this->assertNull($fresh->twofa_recovery_codes);$this->assertTrue($fresh->twofa_required);
        $this->actingAs($fresh)->withSession(['2fa_checked'=>true,'2fa_verified_key'=>hash('sha256',$old)])->get('/dashboard')->assertRedirect(route('setting.index'));
        $new=$this->enable();$this->assertNotSame($old,$new);
        $this->assertFalse(app(TwoFactorAuthentication::class)->consume($fresh,$oldCode));
        $this->withSession(['2fa_checked'=>true,'2fa_verified_key'=>hash('sha256',$old)])->get('/dashboard')->assertRedirect(route('otp.show'));
        $this->actingAs($admin)->withSession(['2fa_checked'=>false])->post(route('two-factor-admin.update',$this->owner->id),
            ['action'=>'disable','reason'=>'Destek talebi','password'=>'admin-password'])->assertRedirect();
        $fresh=$this->owner->fresh();$this->assertNull($fresh->twofa_secret);$this->assertFalse($fresh->twofa_required);
        $this->assertSame(2,DB::table('two_factor_admin_actions')->count());
        $this->assertEquals($admin->id,DB::table('two_factor_admin_actions')->first()->actor_id);
    }
}
