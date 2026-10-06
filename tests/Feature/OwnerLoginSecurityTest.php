<?php
namespace Tests\Feature;

use App\Mail\Common;
use App\Models\{LoggedHistory, User};
use App\Services\{OwnerLoginSecurity, SecurityEmail};
use Illuminate\Support\Facades\{DB, Gate, Hash, Mail};
use Tests\TestCase;

class OwnerLoginSecurityTest extends TestCase
{
    private $admin;
    private $owner;
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array','app.url'=>'https://sanayirandevu.com']);
        DB::purge('sqlite');
        $paths=array_values(array_filter(glob(database_path('migrations/*.php')),fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        $this->admin=User::create(['name'=>'Admin','email'=>'security-admin@example.test','password'=>Hash::make('password'),'type'=>'super admin']);
        $this->owner=User::create(['name'=>'Test Servis','email'=>'security-owner@example.test','password'=>Hash::make('password'),'type'=>'owner']);
        $this->owner->forceFill(['email_verified_at'=>now()])->save();
        Gate::before(fn($user)=>in_array($user->type,['owner','super admin']) ? true : null);
        foreach (['FROM_EMAIL'=>'security@sanayirandevu.com','FROM_NAME'=>'Platform','SERVER_HOST'=>'smtp.example.test','SERVER_PORT'=>587,'SERVER_ENCRYPTION'=>'tls','SERVER_USERNAME'=>'security','SERVER_PASSWORD'=>'secret'] as $key=>$value) {
            DB::table('settings')->insert(['parent_id'=>$this->admin->id,'type'=>'smtp','name'=>$key,'value'=>$value]);
        }
        $this->withoutMiddleware(\App\Http\Middleware\XSS::class);
        Mail::fake();
    }

    public function test_successful_login_records_ip_device_and_sends_platform_email()
    {
        $this->withServerVariables(['REMOTE_ADDR'=>'203.0.113.17'])->post('/login',['email'=>$this->owner->email,'password'=>'wrong'])->assertSessionHasErrors('email');
        $this->assertSame(0,LoggedHistory::count()); Mail::assertNothingSent();
        $this->withHeaders(['User-Agent'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36','X-Forwarded-For'=>'198.51.100.9'])->post('/login',['email'=>$this->owner->email,'password'=>'password'])->assertRedirect();
        $history=LoggedHistory::firstOrFail();
        $this->assertSame('203.0.113.17',$history->ip);
        $this->assertSame($this->owner->id,$history->user_id);
        $details=json_decode($history->details,true);
        $this->assertStringContainsString('Chrome',$details['browser']);
        $this->assertStringContainsString('Windows',$details['os']);
        $this->assertSame('desktop',$details['device']);
        Mail::assertSent(Common::class,fn($mail)=>$mail->hasTo($this->owner->email)
            && $mail->data['settings']['FROM_NAME']==='sanayirandevu.com'
            && str_contains($mail->data['message'],'203.0.113.17')
            && str_contains($mail->data['message'],'Chrome')
            && str_contains($mail->data['settings']['brand_logo'],'sanayirandevu-email-logo.png'));
    }

    public function test_two_factor_login_is_recorded_only_after_otp_and_only_once()
    {
        $google=new \PragmaRX\Google2FAQRCode\Google2FA();
        $secret=$google->generateSecretKey();
        $this->owner->update(['twofa_secret'=>$secret]);
        $this->post('/login',['email'=>$this->owner->email,'password'=>'password'])->assertRedirect();
        $this->assertSame(0,LoggedHistory::count()); Mail::assertNothingSent();
        $this->post(route('otp.check'),['otp'=>'invalid'])->assertRedirect();
        $this->assertSame(0,LoggedHistory::count());
        $this->post(route('otp.check'),['otp'=>$google->getCurrentOtp($secret)])->assertRedirect();
        $this->assertSame(1,LoggedHistory::count()); Mail::assertSent(Common::class,1);
        $this->post(route('otp.check'),['otp'=>$google->getCurrentOtp($secret)])->assertRedirect();
        $this->assertSame(1,LoggedHistory::count()); Mail::assertSent(Common::class,1);
    }

    public function test_history_is_admin_only_filtered_and_old_records_are_deleted()
    {
        $old=LoggedHistory::create(['type'=>'owner','user_id'=>$this->owner->id,'parent_id'=>$this->owner->id,'date'=>now()->subDays(7),'ip'=>'old']);
        $recent=LoggedHistory::create(['type'=>'owner','user_id'=>$this->owner->id,'parent_id'=>$this->owner->id,'date'=>now()->subDays(6),'ip'=>'203.0.113.22','details'=>'{"device":"desktop","browser":"Chrome","os":"Windows"}']);
        $other=User::create(['name'=>'Other','email'=>'other-security@example.test','password'=>'x','type'=>'owner']);
        LoggedHistory::create(['type'=>'owner','user_id'=>$other->id,'parent_id'=>$other->id,'date'=>now(),'ip'=>'203.0.113.23']);
        $this->actingAs($this->owner)->get(route('owner-logins.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('owner-logins.index',['owner'=>$this->owner->id]))->assertOk()->assertSee('203.0.113.22')->assertDontSee('203.0.113.23');
        $this->assertNull($old->fresh()); $this->assertNotNull($recent->fresh());
        $recent->update(['date'=>now()->subDays(8)]);
        $this->artisan('security:purge-owner-logins')->assertExitCode(0);
        $this->assertNull($recent->fresh());
    }

    public function test_login_mail_failure_or_disabled_template_does_not_block_login_history()
    {
        SecurityEmail::template($this->admin,SecurityEmail::LOGIN)->update(['enabled_email'=>0]);
        $this->post('/login',['email'=>$this->owner->email,'password'=>'password'])->assertRedirect();
        $this->assertAuthenticatedAs($this->owner); $this->assertSame(1,LoggedHistory::count()); Mail::assertNothingSent();
        $this->post('/logout');
        SecurityEmail::template($this->admin,SecurityEmail::LOGIN)->update(['enabled_email'=>1]);
        DB::table('settings')->where('type','smtp')->delete();
        $this->post('/login',['email'=>$this->owner->email,'password'=>'password'])->assertRedirect();
        $this->assertAuthenticatedAs($this->owner); $this->assertSame(2,LoggedHistory::count()); Mail::assertNothingSent();
    }

    public function test_branded_password_reset_mail_and_pages_and_token_still_work()
    {
        $this->get('/forgot-password')->assertOk()->assertSee('sanayirandevu-logo-light.svg')->assertSee('sanayi-auth.css')->assertDontSee('Your Trusted Gateway');
        $this->post('/forgot-password',['email'=>$this->owner->email])->assertSessionHas('status');
        $mail=Mail::sent(Common::class)->first();
        $this->assertSame(SecurityEmail::RESET,$mail->data['module']);
        $html=view($mail->build()->view,$mail->buildViewData())->render();
        $this->assertStringContainsString('sanayirandevu-email-logo.png',$html);
        $this->assertStringContainsString('60 dakika',$html);
        preg_match('/href="([^"]+)"/',$mail->data['message'],$match);
        $url=html_entity_decode($match[1]);
        $this->assertStringStartsWith('https://sanayirandevu.com/reset-password/',$url);
        $token=basename(parse_url($url,PHP_URL_PATH));
        $this->get('/reset-password/'.$token.'?email='.urlencode($this->owner->email))->assertOk()->assertSee('sanayirandevu-logo-light.svg')->assertSee('Yeni şifre');
        $this->post('/reset-password',['token'=>$token,'email'=>$this->owner->email,'password'=>'updated-password','password_confirmation'=>'updated-password'])->assertRedirect(route('login'));
        $this->assertTrue(Hash::check('updated-password',$this->owner->fresh()->password));
    }

    public function test_admin_can_edit_security_templates_and_reset_link_cannot_be_removed()
    {
        $this->actingAs($this->admin)->get(route('notification.index'))->assertOk()->assertSee('Şifre sıfırlama')->assertSee('İşletme hesabına giriş');
        $template=SecurityEmail::template($this->admin,SecurityEmail::RESET);
        $this->put(route('notification.update',$template),['subject'=>'Yeni konu','message'=>'Bağlantı yok'])->assertSessionHas('error');
        $this->put(route('notification.update',$template),['subject'=>'Yeni konu','message'=>'Merhaba {user_name}: {reset_link}'])->assertRedirect();
        $this->assertSame(1,(int)$template->fresh()->enabled_email);
        $this->assertSame('Yeni konu',$template->fresh()->subject);
        $this->actingAs($this->owner)->get(route('notification.edit',$template))->assertForbidden();
    }
}
