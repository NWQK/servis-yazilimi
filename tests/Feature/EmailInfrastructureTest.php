<?php
namespace Tests\Feature;

use App\Mail\{Common, TestMail, EmailVerification, Document};
use App\Models\{User, Notification, Invoice, Service, Vehicle, VehicleQrCode};
use Illuminate\Support\Facades\{DB, Gate, Mail};
use Tests\TestCase;

class EmailInfrastructureTest extends TestCase
{
    private $owner;
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default'=>'sqlite','database.connections.sqlite.database'=>':memory:','cache.default'=>'array','session.driver'=>'array']);
        DB::purge('sqlite');
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn($p)=>!str_contains($p,'version_1_7_filled')));
        $this->artisan('migrate',['--path'=>$paths,'--realpath'=>true,'--force'=>true])->assertExitCode(0);
        $this->owner = User::create(['name'=>'Test Servis','email'=>'owner@example.test','password'=>'test','type'=>'owner']);
        Gate::before(fn($user)=>$user->type === 'owner' ? true : null);
        $this->actingAs($this->owner)->withoutMiddleware(\App\Http\Middleware\XSS::class);
    }

    private function smtp(int $id, string $host='smtp.example.test'): array
    {
        $values=['FROM_EMAIL'=>'sender@example.test','FROM_NAME'=>'Test Servis','SERVER_DRIVER'=>'smtp','SERVER_HOST'=>$host,'SERVER_PORT'=>587,'SERVER_ENCRYPTION'=>'tls','SERVER_USERNAME'=>'sender@example.test','SERVER_PASSWORD'=>'smtp-secret'];
        foreach ($values as $key=>$value) { DB::table('settings')->updateOrInsert(['parent_id'=>$id,'type'=>'smtp','name'=>$key], ['value'=>$value]); }
        return $values;
    }

    public function test_all_email_views_render_without_variable_errors()
    {
        $settings=$this->smtp($this->owner->id)+['company_name'=>'Test Servis'];
        $templates=defaultTemplateList();
        foreach ($templates as $module=>$definition) {
            $mail=new Common(['settings'=>$settings,'module'=>$module,'subject'=>$definition['subject'],'message'=>$definition['templete']]);
            $html=$mail->render();
            $this->assertStringContainsString('lang="tr"',$html);
            $this->assertStringContainsString('#ed001b',$html);
        }
        $this->assertStringContainsString('test mesajı',(new TestMail(['settings'=>$settings,'subject'=>'Test','message'=>'test mesajı']))->render());
        $this->assertStringContainsString('doğrulayın',(new EmailVerification(['settings'=>$settings,'subject'=>'Doğrulama','name'=>'Ali','url'=>'https://example.test/verify']))->render());
        $this->assertStringContainsString('giriş yapın',(new Common(['settings'=>$settings,'module'=>'owner_create','subject'=>'Hoş geldiniz','name'=>'Ali','email'=>'ali@example.test','url'=>'https://example.test','password'=>'secret']))->render());
        $this->assertStringNotContainsString('secret',(new Common(['settings'=>$settings,'module'=>'owner_create','subject'=>'Hoş geldiniz','name'=>'Ali','email'=>'ali@example.test','url'=>'https://example.test','password'=>'secret']))->render());
        $this->assertStringContainsString('Belge',(new Document(['from'=>'sender@example.test','from_name'=>'Servis','subject'=>'Belge','message'=>'Belge içeriği']))->render());
    }

    public function test_smtp_configuration_switch_clears_cached_transport_and_missing_tenant_credentials()
    {
        $this->smtp($this->owner->id);
        emailSettings($this->owner->id);
        $old=Mail::mailer('smtp');
        $this->smtp(99,'different.example.test');
        emailSettings(99);
        $this->assertNotSame($old,Mail::mailer('smtp'));
        $this->assertEquals('different.example.test',config('mail.mailers.smtp.host'));
        $this->assertSame(15,config('mail.mailers.smtp.timeout'));
        DB::table('settings')->where('parent_id',99)->where('name','SERVER_ENCRYPTION')->update(['value'=>'ssl']);
        DB::table('settings')->where('parent_id',99)->where('name','SERVER_PORT')->update(['value'=>465]);
        emailSettings(99);
        $this->assertSame('smtps',config('mail.mailers.smtp.scheme'));
        $this->assertTrue(Mail::mailer('smtp')->getSymfonyTransport()->getStream()->isTLS());
        try { emailSettings(100); $this->fail('Missing SMTP must not use previous credentials'); }
        catch (\RuntimeException $e) { $this->assertSame('',config('mail.mailers.smtp.password')); }
    }

    public function test_smtp_password_can_be_retained_and_invalid_configuration_is_rejected()
    {
        $this->smtp($this->owner->id);
        $data=['sender_name'=>'Servis','sender_email'=>'sender@example.test','server_driver'=>'smtp','server_host'=>'smtp.example.test','server_port'=>587,'server_username'=>'sender@example.test','server_password'=>'','server_encryption'=>'tls'];
        $this->post(route('setting.smtp'),$data)->assertSessionHasNoErrors();
        $this->assertSame('smtp-secret',DB::table('settings')->where('name','SERVER_PASSWORD')->where('parent_id',$this->owner->id)->value('value'));
        $this->post(route('setting.smtp'),array_merge($data,['sender_email'=>'bad','server_port'=>70000,'server_driver'=>'invalid']))->assertSessionHasErrors(['sender_email','server_port','server_driver']);
        $this->post(route('setting.smtp.testing'),['email'=>'bad'])->assertSessionHasErrors('email');
    }

    public function test_send_path_uses_tenant_sender_without_sending_real_email()
    {
        $this->smtp($this->owner->id);
        Mail::fake();
        $this->assertEquals('success',commonEmailSend('customer@example.test',['module'=>'invoice_create','subject'=>'Fatura','message'=>'Fatura hazır'])['status']);
        Mail::assertSent(Common::class,function ($mail) {
            $mail->build();
            return $mail->hasFrom('sender@example.test') && $mail->hasTo('customer@example.test');
        });
        $this->assertEquals('success',sendEmail('owner@example.test',['subject'=>'Test','message'=>'Test'])['status']);
        Mail::assertSent(TestMail::class);
    }

    public function test_migration_preserves_custom_templates_and_enabled_flags()
    {
        defaultTemplate($this->owner->id);
        $this->get(route('notification.create'))->assertOk()->assertSee('Fatura oluşturma');
        $invoice=Notification::where('module','invoice_create')->first();
        $this->get(route('notification.edit',$invoice))->assertOk()->assertSee('Hazır şablonu ön izle');
        $invoice->update(['subject'=>'Özel konu','message'=>'Özel içerik','enabled_email'=>1]);
        $payment=Notification::where('module','payment_create')->first();
        $legacy=legacyEmailTemplateList()['payment_create'];
        $payment->update(['subject'=>$legacy['subject'],'message'=>$legacy['templete'],'enabled_email'=>1]);
        $migration=require database_path('migrations/2026_10_05_000002_prepare_turkish_email_templates.php');
        $migration->up(); $migration->up(); defaultTemplate($this->owner->id);
        $this->assertSame(8,Notification::where('parent_id',$this->owner->id)->count());
        $this->assertEquals('Özel içerik',$invoice->fresh()->message);
        $this->assertEquals(defaultTemplateList()['payment_create']['templete'],$payment->fresh()->message);
        $this->assertSame(1,(int)$payment->fresh()->enabled_email);
        $this->put(route('notification.update',$invoice),['subject'=>'Özel konu','message'=>'Özel içerik','use_default_template'=>1])->assertSessionHasNoErrors();
        $this->assertEquals(defaultTemplateList()['invoice_create']['templete'],$invoice->fresh()->message);
        $other=Notification::create(['parent_id'=>999,'module'=>'invoice_create','subject'=>'Other','message'=>'Other']);
        $this->get(route('notification.edit',$other))->assertForbidden();
        $this->put(route('notification.update',$other),['subject'=>'x','message'=>'x'])->assertForbidden();
    }

    public function test_invoice_message_uses_discounted_total_and_public_vehicle_link_with_escaped_customer_name()
    {
        $client=User::create(['name'=>'<script>test</script>','email'=>'client@example.test','password'=>'x','type'=>'client','parent_id'=>$this->owner->id]);
        $vehicle=Vehicle::create(['client'=>$client->id,'parent_id'=>$this->owner->id]);
        $qr=VehicleQrCode::create(['vehicle_id'=>$vehicle->id,'parent_id'=>$this->owner->id,'token'=>str_repeat('a',64)]);
        $service=Service::create(['client'=>$client->id,'vehicle'=>$vehicle->id,'parent_id'=>$this->owner->id,'external_labor_amount'=>1000]);
        $invoice=Invoice::create(['client'=>$client->id,'service'=>$service->id,'invoice_id'=>1,'invoice_date'=>'2026-10-05','external_labor_amount'=>1000,'discount_amount'=>100,'status'=>1,'parent_id'=>$this->owner->id]);
        $definition=defaultTemplateList()['invoice_create'];
        $notification=new Notification(['module'=>'invoice_create','subject'=>$definition['subject'],'message'=>$definition['templete']]);
        $output=MessageReplace($notification,$invoice->id);
        $this->assertStringContainsString('900,00',$output['message']);
        $this->assertStringContainsString($qr->publicUrl(),$output['message']);
        $this->assertStringNotContainsString('<script>',$output['message']);
        $this->assertStringContainsString('Kısmen ödendi',$output['message']);
    }

    public function test_service_email_handles_unassigned_employee_and_optional_vehicle_fields()
    {
        $client=User::create(['name'=>'Ali','email'=>'client@example.test','password'=>'x','type'=>'client','parent_id'=>$this->owner->id]);
        $vehicle=Vehicle::create(['client'=>$client->id,'parent_id'=>$this->owner->id]);
        $service=Service::create(['client'=>$client->id,'vehicle'=>$vehicle->id,'parent_id'=>$this->owner->id]);
        $definition=defaultTemplateList()['service_create'];
        $output=MessageReplace(new Notification(['module'=>'service_create','subject'=>$definition['subject'],'message'=>$definition['templete']]),$service->id);
        $this->assertStringContainsString('Merhaba Ali',$output['message']);
        $this->assertStringNotContainsString('{client_name}',$output['message']);
    }
}
