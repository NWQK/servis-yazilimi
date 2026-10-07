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

    public function test_legacy_logos_resolve_for_each_business_and_only_uploads_are_exposed()
    {
        foreach (['company_logo'=>'shop-logo.png','company_light_logo'=>'shop-dark.png','company_favicon'=>'shop-icon.png','company_landing_logo'=>'shop-landing.png'] as $key=>$value) {
            DB::table('settings')->updateOrInsert(['parent_id'=>$this->owner->id,'name'=>$key],['value'=>$value]);
        }
        $this->assertSame('shop-dark.png',getSettingsValByName('light_logo'));
        $this->assertSame('shop-landing.png',settingsById($this->owner->id)['landing_logo']);
        $this->assertSame('shop-logo.png',getSettingsValByName('company_logo'));
        DB::table('settings')->insert(['parent_id'=>99,'name'=>'logo','value'=>'admin-new.png']);
        DB::table('settings')->insert(['parent_id'=>99,'name'=>'favicon','value'=>'admin-new-icon.png']);
        $this->assertSame('admin-new.png',settingsById(99)['company_logo']);
        $this->assertSame('admin-new-icon.png',settingsById(99)['company_favicon']);
        $this->assertSame('shop-logo.png',getSettingsValByName('company_logo'));
        $this->assertSame(storage_path('upload'),config('filesystems.links')[public_path('storage/upload')]);
        $this->assertSame(storage_path('app/public'),config('filesystems.links')[public_path('storage')]);
        $this->assertNotContains(storage_path(),array_values(config('filesystems.links')));
    }

    public function test_business_general_settings_save_without_brand_fields_and_cannot_change_platform_brand()
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $envBefore = hash_file('sha256', app()->environmentFilePath());
        $this->post(route('setting.general'),[
            'logo'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('logo.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==')),
            'light_logo'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('dark.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==')),
            'favicon'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('favicon.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==')),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists('upload/logo/'.$this->owner->id.'_logo.png');
        $this->assertSame('sanayirandevu.com',getSettingsValByName('app_name'));
        $this->assertSame('© sanayirandevu.com. Tüm hakları saklıdır.',getSettingsValByName('copyright'));
        $this->post(route('setting.general'),[
            'application_name'=>'Hatalı ad','copyright'=>'Değiştirilemez','landing_page'=>'off',
            'logo'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('new.png',base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==')),
        ])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('sanayirandevu.com',getSettingsValByName('app_name'));
        $this->assertSame($envBefore,hash_file('sha256',app()->environmentFilePath()));
        $this->assertSame(1,DB::table('settings')->where('parent_id',$this->owner->id)->where('name','company_logo')->count());
        $this->assertSame(0,DB::table('settings')->where('parent_id',$this->owner->id)->where('name','landing_page')->count());
        $this->get(route('setting.index'))->assertOk()->assertDontSee('name="application_name"',false)->assertDontSee('name="copyright"',false);
        DB::table('settings')->where('parent_id',$this->owner->id)->where('name','app_name')->update(['value'=>'Eski ad']);
        $this->assertSame('sanayirandevu.com',settingsById($this->owner->id)['app_name']);
        $client=User::create(['name'=>'Client','email'=>'general-client@example.test','password'=>'x','type'=>'client','parent_id'=>$this->owner->id]);
        $this->actingAs($client)->post(route('setting.general'),[])->assertForbidden();
    }

    public function test_super_admin_can_save_branding_without_writing_env()
    {
        $admin=User::create(['name'=>'Admin','email'=>'general-admin@example.test','password'=>'x','type'=>'super admin']);
        Gate::before(fn($user)=>$user->type==='super admin' ? true : null);
        $this->actingAs($admin);
        $before=hash_file('sha256',app()->environmentFilePath());
        $this->post(route('setting.general'),['application_name'=>'sanayirandevu.com','copyright'=>'Platform'])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertSame('Platform',settingsById($admin->id)['copyright']);
        $this->assertSame($before,hash_file('sha256',app()->environmentFilePath()));
        $this->get(route('setting.index'))->assertOk()->assertSee('name="application_name"',false);
    }

    public function test_invoice_logo_upload_is_separate_bounded_and_uses_invoice_owner_in_public_views()
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->assertStringContainsString('/upload/logo/logo.png',invoiceLogoUrl($this->owner->id));
        $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWQAAAABJRU5ErkJggg==');
        $this->post(route('setting.general'),['invoice_logo'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('invoice.png',$png)])->assertSessionHasNoErrors()->assertSessionHas('success')->assertSessionHas('tab','user_profile_settings');
        $filename=DB::table('settings')->where('parent_id',$this->owner->id)->where('name','invoice_logo')->value('value');
        $this->assertNotEmpty($filename);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists('upload/logo/'.$filename);
        $this->assertSame(0,DB::table('settings')->where('parent_id',$this->owner->id)->where('name','company_logo')->count());
        $this->post(route('setting.general'),[])->assertSessionHasNoErrors();
        $this->assertSame($filename,settingsById($this->owner->id)['invoice_logo']);
        $this->get(route('setting.index'))->assertOk()->assertSee('name="invoice_logo"',false)->assertSee('600 × 240')->assertDontSee('href="#general_settings"',false);
        $this->post(route('setting.general'),['invoice_logo'=>\Illuminate\Http\UploadedFile::fake()->create('bad.pdf',10,'application/pdf')])->assertSessionHasErrors('invoice_logo');
        $large=substr_replace($png,pack('N',7000),16,4);
        $this->post(route('setting.general'),['invoice_logo'=>\Illuminate\Http\UploadedFile::fake()->createWithContent('large.png',$large)])->assertSessionHasErrors('invoice_logo');
        $client=User::create(['name'=>'Logo client','email'=>'invoice-logo-client@example.test','password'=>'x','type'=>'client','parent_id'=>$this->owner->id]);
        $vehicle=Vehicle::create(['client'=>$client->id,'parent_id'=>$this->owner->id]);
        $service=Service::create(['client'=>$client->id,'vehicle'=>$vehicle->id,'parent_id'=>$this->owner->id]);
        $invoice=Invoice::create(['client'=>$client->id,'service'=>$service->id,'parent_id'=>$this->owner->id,'invoice_date'=>'2026-10-06','external_labor_amount'=>100]);
        $this->get(route('invoice.show',encrypt($invoice->id)))->assertOk()->assertSee($filename)->assertSee('object-fit:contain',false)->assertSee('height:80px',false);
        $code=VehicleQrCode::create(['parent_id'=>$this->owner->id,'vehicle_id'=>$vehicle->id,'assigned_at'=>now(),'token'=>str_repeat('f',64)]);
        $other=User::create(['name'=>'Other','email'=>'invoice-logo-other@example.test','password'=>'x','type'=>'owner']);
        DB::table('settings')->insert(['parent_id'=>$other->id,'name'=>'invoice_logo','value'=>'other-shop.png']);
        $this->actingAs($other)->get(route('vehicle-portal.invoice',[$code->token,$invoice->id]))->assertOk()->assertSee($filename)->assertDontSee('other-shop.png')->assertSee('object-fit:contain',false);
    }

    public function test_invoice_notice_and_list_shortcuts_respect_balance_and_permissions()
    {
        $client=User::create(['name'=>'Invoice client','email'=>'invoice-actions-client@example.test','password'=>'x','type'=>'client','parent_id'=>$this->owner->id]);
        $vehicle=Vehicle::create(['client'=>$client->id,'parent_id'=>$this->owner->id]);
        $service=Service::create(['client'=>$client->id,'vehicle'=>$vehicle->id,'parent_id'=>$this->owner->id]);
        $invoice=Invoice::create(['client'=>$client->id,'service'=>$service->id,'parent_id'=>$this->owner->id,'invoice_date'=>'2026-10-06','external_labor_amount'=>100]);
        $this->get(route('invoice.index'))->assertOk()->assertSee('aria-label="Ödeme ekle"',false)->assertSee('aria-label="Faturayı yazdır"',false);
        $this->get(route('invoice.payment',$invoice->id))->assertOk();
        $this->get(route('invoice.show',encrypt($invoice->id)).'?print=1')->assertOk()->assertSee('Temsili faturadır. Resmî fatura yerine geçmez.')->assertSee("window.addEventListener('load', printInvoice",false);
        $code=VehicleQrCode::create(['parent_id'=>$this->owner->id,'vehicle_id'=>$vehicle->id,'assigned_at'=>now(),'token'=>str_repeat('a',64)]);
        $this->get(route('vehicle-portal.invoice',[$code->token,$invoice->id]))->assertOk()->assertSee('Temsili faturadır. Resmî fatura yerine geçmez.');
        DB::table('invoice_payments')->insert(['invoice_id'=>$invoice->id,'parent_id'=>$this->owner->id,'amount'=>100,'payment_date'=>'2026-10-06']);
        $this->get(route('invoice.index'))->assertOk()->assertDontSee('aria-label="Ödeme ekle"',false)->assertSee('aria-label="Faturayı yazdır"',false);
        $staff=User::create(['name'=>'Read only','email'=>'invoice-actions-staff@example.test','password'=>'x','type'=>'employee','parent_id'=>$this->owner->id]);
        Gate::before(fn($user,$ability)=>$user->id===$staff->id ? in_array($ability,['manage invoice','show invoice']) : null);
        $this->actingAs($staff)->get(route('invoice.index'))->assertOk()->assertDontSee('aria-label="Ödeme ekle"',false)->assertSee('aria-label="Faturayı yazdır"',false);
        $other=User::create(['name'=>'Other','email'=>'invoice-actions-owner@example.test','password'=>'x','type'=>'owner']);
        $this->actingAs($other)->get(route('invoice.show',encrypt($invoice->id)).'?print=1')->assertNotFound();
    }

    public function test_platform_invoice_email_uses_admin_smtp_brand_and_vehicle_link_once()
    {
        $admin=User::create(['name'=>'Admin','email'=>'platform-admin@example.test','password'=>'x','type'=>'super admin']);
        $this->smtp($admin->id,'central.example.test');
        $this->smtp($this->owner->id,'shop.example.test');
        Mail::fake();
        $client=User::create(['name'=>'Ali <script>','email'=>'platform-client@example.test','password'=>'x','type'=>'client','parent_id'=>$this->owner->id]);
        $vehicle=Vehicle::create(['client'=>$client->id,'parent_id'=>$this->owner->id]);
        $qr=VehicleQrCode::create(['vehicle_id'=>$vehicle->id,'parent_id'=>$this->owner->id,'token'=>str_repeat('d',64)]);
        $service=Service::create(['client'=>$client->id,'vehicle'=>$vehicle->id,'parent_id'=>$this->owner->id,'external_labor_amount'=>1000]);
        DB::table('settings')->insert(['parent_id'=>$this->owner->id,'name'=>'company_name','value'=>'Örnek Motor']);
        defaultTemplate($this->owner->id);
        Notification::where('parent_id',$this->owner->id)->where('module','invoice_create')->update(['enabled_email'=>1]);
        $this->post('/invoice',['client'=>$client->id,'service'=>$service->id,'invoice_date'=>'2026-10-06','types'=>[]])->assertRedirect()->assertSessionMissing('error');
        $invoice=Invoice::firstOrFail();
        $this->assertNotNull($invoice->customer_email_sent_at);
        $this->assertSame('central.example.test',config('mail.mailers.smtp.host'));
        Mail::assertSent(Common::class,function($mail) use($client,$qr) {
            return $mail->hasTo($client->email) && str_contains($mail->data['subject'],'numaralı fatura')
                && str_contains($mail->data['message'],'Örnek Motor') && str_contains($mail->data['message'],$qr->publicUrl())
                && !str_contains($mail->data['message'],'<script>')
                && $mail->data['settings']['FROM_NAME']==='sanayirandevu.com'
                && str_contains(view($mail->build()->view, $mail->buildViewData())->render(),'sanayirandevu-email-logo.png');
        });
        app(\App\Services\InvoiceCustomerEmail::class)->send($invoice);
        Mail::assertSent(Common::class,1);
        $this->put('/invoice/'.encrypt($invoice->id),['client'=>$client->id,'service'=>$service->id,'invoice_date'=>'2026-10-06','types'=>[]])->assertRedirect();
        Mail::assertSent(Common::class,1);
        $template=\App\Services\InvoiceCustomerEmail::template($admin);
        $this->get(route('notification.edit',$template))->assertForbidden();
        Gate::before(fn($user)=>$user->type==='super admin' ? true : null);
        $this->actingAs($admin)->get(route('notification.index'))->assertOk()->assertSee('Müşteriye fatura bildirimi');
        $this->get(route('notification.edit',$template))->assertOk()->assertSee('{vehicle_link}');
        $this->put(route('notification.update',$template),['subject'=>'Yeni fatura {invoice_number}','message'=>'{company_name}: {vehicle_link}','enabled_email'=>1])->assertRedirect();
        $this->actingAs($this->owner)->post('/invoice',['client'=>$client->id,'service'=>$service->id,'invoice_date'=>'2026-10-06','types'=>[]])->assertRedirect();
        Mail::assertSent(Common::class,2);
        Mail::assertSent(Common::class,fn($mail)=>str_starts_with($mail->data['subject'],'Yeni fatura'));
        $this->actingAs($admin)->put(route('notification.update',$template),['subject'=>'x','message'=>'x','use_default_template'=>1,'enabled_email'=>0])->assertRedirect();
        $this->assertSame(\App\Services\InvoiceCustomerEmail::definition()['subject'],$template->fresh()->subject);
        $this->actingAs($this->owner);
        $this->post('/invoice',['client'=>$client->id,'service'=>$service->id,'invoice_date'=>'2026-10-06','types'=>[]])->assertRedirect();
        Mail::assertSent(Common::class,2);
    }

    public function test_platform_invoice_email_skips_missing_email_and_foreign_link_and_preserves_invoice_on_smtp_failure()
    {
        $admin=User::create(['name'=>'Admin','email'=>'platform-fail-admin@example.test','password'=>'x','type'=>'super admin']);
        Mail::fake();
        $client=User::create(['name'=>'Client','password'=>'x','type'=>'client','parent_id'=>$this->owner->id]);
        $vehicle=Vehicle::create(['client'=>$client->id,'parent_id'=>$this->owner->id]);
        $service=Service::create(['client'=>$client->id,'vehicle'=>$vehicle->id,'parent_id'=>$this->owner->id]);
        $invoice=Invoice::create(['client'=>$client->id,'service'=>$service->id,'parent_id'=>$this->owner->id]);
        $sender=app(\App\Services\InvoiceCustomerEmail::class);
        $sender->send($invoice);
        $client->email='no-smtp@example.test'; $client->save();
        VehicleQrCode::create(['parent_id'=>999,'vehicle_id'=>$vehicle->id,'token'=>str_repeat('e',64)]);
        $sender->send($invoice);
        Mail::assertNothingSent();
        VehicleQrCode::where('parent_id',999)->delete();
        VehicleQrCode::create(['parent_id'=>$this->owner->id,'vehicle_id'=>$vehicle->id,'token'=>str_repeat('a',64)]);
        $sender->send($invoice);
        $this->assertNotNull($invoice->fresh());
        $this->assertNull($invoice->fresh()->customer_email_sent_at);
        Mail::assertNothingSent();
        $this->smtp($admin->id);
        DB::beginTransaction();
        $sender->send($invoice);
        Mail::assertNothingSent();
        DB::rollBack();
        Mail::assertNothingSent();
        $sender->send($invoice);
        Mail::assertSent(Common::class,1);
        $this->assertNotNull($invoice->fresh()->customer_email_sent_at);
    }

    public function test_platform_invoice_email_accepts_optional_invoice_customer_email()
    {
        $admin=User::create(['name'=>'Admin','email'=>'billing-mail-admin@example.test','password'=>'x','type'=>'super admin']);
        $this->smtp($admin->id); Mail::fake();
        $client=User::create(['name'=>'Client','password'=>'x','type'=>'client','parent_id'=>$this->owner->id]);
        $vehicle=Vehicle::create(['client'=>$client->id,'parent_id'=>$this->owner->id]);
        VehicleQrCode::create(['vehicle_id'=>$vehicle->id,'parent_id'=>$this->owner->id,'token'=>str_repeat('c',64)]);
        $service=Service::create(['client'=>$client->id,'vehicle'=>$vehicle->id,'parent_id'=>$this->owner->id]);
        $this->post('/invoice',['client'=>$client->id,'service'=>$service->id,'invoice_date'=>'2026-10-06','types'=>[],
            'billing'=>['email'=>'optional-billing@example.test']])->assertRedirect()->assertSessionMissing('error');
        Mail::assertSent(Common::class,fn($mail)=>$mail->hasTo('optional-billing@example.test'));
        $this->assertNull($client->fresh()->email);
    }

    public function test_platform_invoice_email_covers_service_and_customer_wizard_creation()
    {
        \Illuminate\Support\Facades\Schema::table('service_items',fn($table)=>$table->string('tax')->nullable());
        DB::table('settings')->insert(['parent_id'=>$this->owner->id,'name'=>'pricing_feature','value'=>'off']);
        $admin=User::create(['name'=>'Admin','email'=>'platform-hook-admin@example.test','password'=>'x','type'=>'super admin']);
        $this->smtp($admin->id);
        Mail::fake();
        $client=User::create(['name'=>'Client','email'=>'hook-client@example.test','password'=>'x','type'=>'client','parent_id'=>$this->owner->id]);
        $vehicle=Vehicle::create(['client'=>$client->id,'parent_id'=>$this->owner->id]);
        VehicleQrCode::create(['vehicle_id'=>$vehicle->id,'parent_id'=>$this->owner->id,'token'=>str_repeat('b',64)]);
        $this->post('/service',['client'=>$client->id,'vehicle'=>$vehicle->id,'assign'=>$this->owner->id,'status'=>'scheduled','types'=>[]])->assertRedirect();
        Mail::assertSent(Common::class,1);
        \Spatie\Permission\Models\Role::create(['name'=>'client','parent_id'=>$this->owner->id,'guard_name'=>'web']);
        $brand=DB::table('vehicle_types')->insertGetId(['parent_id'=>$this->owner->id,'type'=>'Ford']);
        $model=DB::table('vehicle_brands')->insertGetId(['parent_id'=>$this->owner->id,'type'=>$brand,'name'=>'Focus']);
        $type=DB::table('service_types')->insertGetId(['parent_id'=>$this->owner->id,'type'=>'Bakım']);
        $this->post('/client',['name'=>'Wizard Client','email'=>'wizard-email@example.test','phone_number'=>'5551234567',
            'type'=>$brand,'brand'=>$model,'license_plate'=>'34 MAIL 01','assign'=>$this->owner->id,'status'=>'scheduled',
            'types'=>[['service_type'=>$type,'rate'=>1000]]])->assertRedirect()->assertSessionHasNoErrors();
        Mail::assertSent(Common::class,2);
        $this->assertSame(2,Invoice::whereNotNull('customer_email_sent_at')->count());
    }

    public function test_shop_cannot_manage_smtp_or_templates_even_with_all_permissions()
    {
        $template = Notification::create(['parent_id'=>$this->owner->id,'module'=>'vehicle_create','subject'=>'Old','message'=>'Old']);
        $this->get(route('setting.index'))->assertOk()->assertDontSee('name="server_host"',false)->assertDontSee('href="#email_SMTP_settings"',false)->assertDontSee('E-posta şablonları');
        $this->post(route('setting.smtp'),[])->assertForbidden();
        $this->get(route('setting.smtp.test'))->assertForbidden();
        $this->post(route('setting.smtp.testing'),[])->assertForbidden();
        $this->get(route('notification.index'))->assertForbidden();
        $this->get(route('notification.create'))->assertForbidden();
        $this->post(route('notification.store'),[])->assertForbidden();
        $this->get(route('notification.show',$template))->assertForbidden();
        $this->get(route('notification.edit',$template))->assertForbidden();
        $this->put(route('notification.update',$template),[])->assertForbidden();
        $this->delete(route('notification.destroy',$template))->assertForbidden();
        $this->assertSame('Old',$template->fresh()->message);
        $this->assertSame([],defaultTemplate($this->owner->id));
        $admin=User::create(['name'=>'Admin','email'=>'legacy-central@example.test','password'=>'x','type'=>'super admin']);
        defaultSMSTemplate();
        $this->assertSame(1,Notification::where('parent_id',$this->owner->id)->count());
        $this->assertSame(8,Notification::where('parent_id',$admin->id)->count());
    }

    public function test_shop_and_guest_use_only_central_mail_and_shop_context_in_message()
    {
        $admin=User::create(['name'=>'Admin','email'=>'central-regression@example.test','password'=>'x','type'=>'super admin']);
        $this->smtp($admin->id,'central.example.test');
        $this->smtp($this->owner->id,'shop.example.test');
        DB::table('settings')->insert(['parent_id'=>$this->owner->id,'name'=>'company_name','value'=>'Örnek Oto']);
        Notification::create(['parent_id'=>$this->owner->id,'module'=>'service_create','subject'=>'Old','message'=>'Old','enabled_email'=>1]);
        $template=\App\Services\CentralEmail::template('service_create');
        $template->update(['subject'=>'Merkezi servis mesajı','message'=>'{company_name}: {client_name}','enabled_email'=>1]);
        $client=User::create(['name'=>'Ali','email'=>'central-client@example.test','password'=>'x','type'=>'client','parent_id'=>$this->owner->id]);
        $vehicle=Vehicle::create(['client'=>$client->id,'parent_id'=>$this->owner->id]);
        Mail::fake();
        $this->post('/service',['client'=>$client->id,'vehicle'=>$vehicle->id,'assign'=>$this->owner->id,'status'=>'scheduled','types'=>[]])->assertRedirect()->assertSessionMissing('error');
        Mail::assertSent(Common::class,1);
        Mail::assertSent(Common::class,fn($mail)=>$mail->data['subject']==='Merkezi servis mesajı' && $mail->data['message']==='Örnek Oto: Ali' && $mail->data['settings']['FROM_NAME']==='sanayirandevu.com');
        $this->assertSame('central.example.test',config('mail.mailers.smtp.host'));
        auth()->logout();
        $this->assertSame('success',commonEmailSend($client->email,['module'=>'vehicle_create','subject'=>'Test','message'=>'Test','parent_id'=>$this->owner->id])['status']);
        $this->assertSame('central.example.test',config('mail.mailers.smtp.host'));
        DB::table('settings')->where('parent_id',$admin->id)->where('type','smtp')->delete();
        $this->assertSame('error',commonEmailSend($client->email,['module'=>'vehicle_create','subject'=>'Test','message'=>'Test'])['status']);
        $this->assertSame('',config('mail.mailers.smtp.password'));
        Mail::assertSent(Common::class,2);
    }

    public function test_global_communication_switches_preserve_settings_and_stop_every_email_path()
    {
        $admin=User::create(['name'=>'Admin','email'=>'switch-admin@example.test','password'=>'x','type'=>'super admin']);
        $this->smtp($admin->id); Mail::fake();
        $sms=\App\Models\AppointmentSmsSetting::central();
        $sms->update(['enabled'=>true,'verification_required'=>true,'api_key'=>'retained-key','api_hash'=>'retained-hash','sender'=>'SANAYI']);
        $this->get(route('communication-settings.index'))->assertForbidden();
        $this->post(route('communication-settings.save'),['email_enabled'=>0,'sms_enabled'=>0])->assertForbidden();
        $this->actingAs($admin)->get(route('communication-settings.index'))->assertOk()->assertSee('E-posta sistemi aktif');
        $this->post(route('communication-settings.save'),['email_enabled'=>0,'sms_enabled'=>0])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertFalse(\App\Services\CentralEmail::enabled());
        $this->assertFalse($sms->fresh()->requiresVerification());
        $this->assertTrue($sms->fresh()->verification_required);
        $this->assertSame('retained-key',$sms->fresh()->api_key);
        $this->assertSame('smtp-secret',DB::table('settings')->where('parent_id',$admin->id)->where('name','SERVER_PASSWORD')->value('value'));
        $this->assertTrue(commonEmailSend('test@example.test',['module'=>'client_create','subject'=>'Test','message'=>'Test'])['skipped']);
        $this->assertTrue(sendEmail('test@example.test',['subject'=>'Test','message'=>'Test'])['skipped']);
        $this->assertTrue(sendEmailVerification('test@example.test',['name'=>'Test','url'=>'https://example.test'])['skipped']);
        app(\App\Services\SecurityEmail::class)->passwordReset($this->owner,'token');
        auth()->logout();
        $this->post(route('password.email'),['email'=>$this->owner->email])->assertSessionHasErrors('email');
        $this->actingAs($admin);
        Mail::assertNothingSent();
        $this->post(route('communication-settings.save'),['email_enabled'=>1,'sms_enabled'=>1])->assertSessionHasNoErrors();
        $this->assertTrue(\App\Services\CentralEmail::enabled());
        $this->assertTrue($sms->fresh()->requiresVerification());
        $this->assertSame('success',commonEmailSend('test@example.test',['module'=>'client_create','subject'=>'Test','message'=>'Test'])['status']);
        Mail::assertSent(Common::class,1);
        $this->post(route('communication-settings.save'),['email_enabled'=>'bad','sms_enabled'=>0])->assertSessionHasErrors('email_enabled');
        $this->assertTrue(\App\Services\CentralEmail::enabled());
    }

    public function test_business_detail_is_admin_only_and_contains_only_selected_shop_data()
    {
        $admin=User::create(['name'=>'Admin','email'=>'detail-admin@example.test','password'=>'x','type'=>'super admin']);
        $client=User::create(['name'=>'Ali','email'=>'detail-client@example.test','password'=>'x','type'=>'client','parent_id'=>$this->owner->id]);
        $vehicle=Vehicle::create(['client'=>$client->id,'parent_id'=>$this->owner->id]);
        $service=Service::create(['client'=>$client->id,'vehicle'=>$vehicle->id,'parent_id'=>$this->owner->id]);
        Invoice::create(['client'=>$client->id,'service'=>$service->id,'parent_id'=>$this->owner->id]);
        $ticket=\App\Models\SupportTicket::create(['owner_id'=>$this->owner->id,'subject'=>'Bu işletmenin bileti','last_message_at'=>now()]);
        $other=User::create(['name'=>'Other','email'=>'detail-other@example.test','password'=>'x','type'=>'owner']);
        Vehicle::create(['parent_id'=>$other->id]);
        \App\Models\SupportTicket::create(['owner_id'=>$other->id,'subject'=>'Başka işletmenin gizli bileti','last_message_at'=>now()]);
        $this->get(route('business-detail.show',$this->owner->id))->assertForbidden();
        $this->actingAs($client)->get(route('business-detail.show',$this->owner->id))->assertForbidden();
        $this->actingAs($admin)->get(route('business-detail.show',$this->owner->id))->assertOk()
            ->assertSee('Bu işletmenin bileti')->assertDontSee('Başka işletmenin gizli bileti')
            ->assertSee('Paket ve kapasite')->assertSee('Kurulum ve eksik ayarlar')->assertSee('İşletme bilgilerinizi tamamlayın')
            ->assertViewHas('counts',fn($counts)=>$counts['Araçlar']===1 && $counts['Müşteriler']===1 && $counts['Servisler']===1 && $counts['Faturalar']===1);
        $this->get(route('business-detail.show',$client->id))->assertNotFound();
        $this->get(route('business-detail.show',999999))->assertNotFound();
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
        $this->owner = User::create(['name'=>'Central admin','email'=>'smtp-admin@example.test','password'=>'x','type'=>'super admin']);
        $this->actingAs($this->owner);
        $this->smtp($this->owner->id);
        emailSettings($this->owner->id);
        $old=Mail::mailer('smtp');
        $this->smtp($this->owner->id,'different.example.test');
        $this->smtp(99,'ignored-shop.example.test');
        emailSettings(99);
        $this->assertNotSame($old,Mail::mailer('smtp'));
        $this->assertEquals('different.example.test',config('mail.mailers.smtp.host'));
        $this->assertSame(15,config('mail.mailers.smtp.timeout'));
        DB::table('settings')->where('parent_id',$this->owner->id)->where('name','SERVER_ENCRYPTION')->update(['value'=>'ssl']);
        DB::table('settings')->where('parent_id',$this->owner->id)->where('name','SERVER_PORT')->update(['value'=>465]);
        emailSettings(99);
        $this->assertSame('smtps',config('mail.mailers.smtp.scheme'));
        $this->assertTrue(Mail::mailer('smtp')->getSymfonyTransport()->getStream()->isTLS());
        DB::table('settings')->where('parent_id',$this->owner->id)->where('type','smtp')->delete();
        try { emailSettings(100); $this->fail('Missing SMTP must not use previous credentials'); }
        catch (\RuntimeException $e) { $this->assertSame('',config('mail.mailers.smtp.password')); }
    }

    public function test_smtp_identity_uses_app_domain_and_preserves_explicit_override_between_shops()
    {
        $this->owner = User::create(['name'=>'Central admin','email'=>'smtp-admin@example.test','password'=>'x','type'=>'super admin']);
        $this->actingAs($this->owner);
        $this->smtp($this->owner->id);
        $this->smtp(99, 'other-smtp.example.test');
        config(['app.url'=>'https://sanayirandevu.com/some/path', 'mail.ehlo_domain'=>null]);
        emailSettings($this->owner->id);
        $this->assertSame('sanayirandevu.com', Mail::mailer('smtp')->getSymfonyTransport()->getLocalDomain());
        config(['mail.ehlo_domain'=>'MAIL.SANAYIRANDEVU.COM']);
        emailSettings(99);
        $this->assertSame('mail.sanayirandevu.com', Mail::mailer('smtp')->getSymfonyTransport()->getLocalDomain());
        emailSettings($this->owner->id);
        $this->assertSame('mail.sanayirandevu.com', Mail::mailer('smtp')->getSymfonyTransport()->getLocalDomain());
        config(['mail.ehlo_domain'=>"bad\r\nMAIL FROM:inject@example.test"]);
        $this->expectException(\RuntimeException::class);
        emailSettings($this->owner->id);
    }

    public function test_smtp_test_logs_message_id_without_credentials_or_message_body()
    {
        $this->owner = User::create(['name'=>'Central admin','email'=>'smtp-admin@example.test','password'=>'x','type'=>'super admin']);
        $this->actingAs($this->owner);
        $this->smtp($this->owner->id);
        config(['app.url'=>'https://sanayirandevu.com', 'mail.ehlo_domain'=>null]);
        $message = (new \Symfony\Component\Mime\Email())->from('sender@example.test')->to('recipient@example.test')->text('Private body');
        $message->getHeaders()->addIdHeader('Message-ID', 'smtp-test@sanayirandevu.com');
        $sent = new \Illuminate\Mail\SentMessage(new \Symfony\Component\Mailer\SentMessage($message, \Symfony\Component\Mailer\Envelope::create($message)));
        $pending = \Mockery::mock(\Illuminate\Mail\PendingMail::class);
        $pending->shouldReceive('send')->once()->with(\Mockery::type(TestMail::class))->andReturn($sent);
        Mail::shouldReceive('to')->once()->with('recipient@example.test')->andReturn($pending);
        Mail::shouldReceive('purge')->once()->with('smtp');
        \Illuminate\Support\Facades\Log::shouldReceive('info')->once()->with(
            'SMTP test e-postası gönderim için kabul edildi; son teslimat doğrulanmadı.',
            \Mockery::on(fn($context)=>$context === ['parent_id'=>$this->owner->id, 'smtp_host'=>'smtp.example.test',
                'smtp_port'=>587, 'ehlo_domain'=>'sanayirandevu.com', 'message_id'=>'smtp-test@sanayirandevu.com'])
        );
        $this->assertSame('success', sendEmail('recipient@example.test',['subject'=>'Test','message'=>'Private body'])['status']);
    }

    public function test_smtp_password_can_be_retained_and_invalid_configuration_is_rejected()
    {
        $this->owner = User::create(['name'=>'Central admin','email'=>'smtp-admin@example.test','password'=>'x','type'=>'super admin']);
        $this->actingAs($this->owner);
        $this->smtp($this->owner->id);
        $data=['sender_name'=>'Servis','sender_email'=>'sender@example.test','server_driver'=>'smtp','server_host'=>'smtp.example.test','server_port'=>587,'server_username'=>'sender@example.test','server_password'=>'','server_encryption'=>'tls'];
        $this->post(route('setting.smtp'),$data)->assertSessionHasNoErrors();
        $this->assertSame('smtp-secret',DB::table('settings')->where('name','SERVER_PASSWORD')->where('parent_id',$this->owner->id)->value('value'));
        $this->post(route('setting.smtp'),array_merge($data,['sender_email'=>'bad','server_port'=>70000,'server_driver'=>'invalid']))->assertSessionHasErrors(['sender_email','server_port','server_driver']);
        $this->post(route('setting.smtp.testing'),['email'=>'bad'])->assertSessionHasErrors('email');
    }

    public function test_send_path_uses_central_sender_without_sending_real_email()
    {
        $this->owner = User::create(['name'=>'Central admin','email'=>'smtp-admin@example.test','password'=>'x','type'=>'super admin']);
        $this->actingAs($this->owner);
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
        $this->owner = User::create(['name'=>'Central admin','email'=>'smtp-admin@example.test','password'=>'x','type'=>'super admin']);
        $this->actingAs($this->owner);
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
