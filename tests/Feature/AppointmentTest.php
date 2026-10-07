<?php

namespace Tests\Feature;

use App\Models\{Appointment, User};
use App\Services\AppointmentBooking;
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Support\Facades\{DB, Gate};
use Illuminate\Support\Str;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    private $owner;
    private $profile;
    private $booking;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'session.driver' => 'array']);
        DB::purge('sqlite');
        $paths = array_values(array_filter(glob(database_path('migrations/*.php')), fn ($p) => !str_contains($p, 'version_1_7_filled')));
        $this->artisan('migrate', ['--path' => $paths, '--realpath' => true, '--force' => true])->assertExitCode(0);
        Carbon::setTestNow(Carbon::parse('2026-09-25 08:15:00', 'Europe/Istanbul'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-25 08:15:00', 'Europe/Istanbul'));
        $this->owner = User::create(['name' => 'Test servis', 'email' => 'appointments@example.test', 'password' => bcrypt('test'), 'type' => 'owner', 'lang' => 'tr']);
        DB::table('settings')->insert(['parent_id' => $this->owner->id, 'name' => 'pricing_feature', 'value' => 'off']);
        Gate::before(fn ($user) => $user->type === 'owner' ? true : null);
        $this->booking = app(AppointmentBooking::class);
        $this->profile = $this->booking->profileForOwner($this->owner->id);
        $this->profile->update(['is_active' => true, 'weekly_hours' => [5 => [8, 9, 10, 23]]]);
        \App\Models\AppointmentSmsSetting::central()->update(['enabled' => true, 'api_key' => str_repeat('a', 32),
            'api_hash' => str_repeat('b', 32), 'sender' => 'SANAYI']);
        \Illuminate\Support\Facades\Http::fake(['api.iletimerkezi.com/*' => \Illuminate\Support\Facades\Http::response(['response' => ['status' => ['code' => 200], 'order' => ['id' => '12345']]], 200)]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function data(array $changes = []): array
    {
        return array_replace(['customer_name' => 'Ayşe Test', 'phone' => '5551234567', 'date' => '2026-09-25',
            'hour' => 9, 'request_key' => (string) Str::uuid(), 'notes' => 'Yağ bakımı'], $changes);
    }

    public function test_migration_can_resume_with_existing_profile_table_and_preserves_records()
    {
        $migration = require database_path('migrations/2026_09_25_000002_create_appointments.php');
        \Illuminate\Support\Facades\Schema::drop('appointments');
        $migration->up();
        // Restore later columns after recreating the historical base table.
        (require database_path('migrations/2026_09_27_000001_appointment_cancellation_and_expiry.php'))->up();
        (require database_path('migrations/2026_09_27_000002_create_booking_directory.php'))->up();
        (require database_path('migrations/2026_09_28_000004_vehicle_links_and_sms_controls.php'))->up();
        $appointment = $this->booking->book($this->profile, $this->data());
        $before = $appointment->fresh()->getAttributes();
        $migration->up();
        $this->assertSame($before, $appointment->fresh()->getAttributes());
        $this->assertSame($this->profile->public_id, $this->profile->fresh()->public_id);
        $this->assertSame(1, Appointment::count());
    }

    public function test_owner_settings_grid_and_stable_unique_links()
    {
        $this->actingAs($this->owner)->get('/appointments/settings')->assertOk()->assertSee('Pazartesi')->assertSee('23:00');
        $id = $this->profile->public_id;
        $this->post('/appointments/settings', ['display_name' => 'Yeni isim', 'is_active' => 1, 'hours' => [1 => [9, 10, 9], 5 => [23]]])->assertSessionHasNoErrors();
        $this->assertSame($id, $this->profile->fresh()->public_id);
        $this->assertSame([9, 10], $this->profile->fresh()->weekly_hours[1]);
        $this->post('/appointments/settings', ['display_name' => 'Test', 'hours' => [8 => [9]]])->assertSessionHasErrors('hours');
        $other = User::create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'test', 'type' => 'owner']);
        $this->assertNotSame($id, $this->booking->profileForOwner($other->id)->public_id);
        $this->post('/appointments/settings', ['display_name' => 'Test', 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertSame([], $this->booking->availableHours($this->profile->fresh(), '2026-09-25'));
    }

    public function test_guest_booking_reserves_slot_and_retry_is_idempotent()
    {
        $url = $this->profile->publicUrl();
        $this->get($url)->assertOk()->assertViewHas('hours', [9, 10, 23]);
        $data = $this->data();
        $this->post($url, $data)->assertRedirect();
        $challenge = \App\Models\AppointmentSmsChallenge::firstOrFail();
        $this->assertSame(0, Appointment::count());
        $smsRequest = \Illuminate\Support\Facades\Http::recorded()->first()[0];
        preg_match('/\b(\d{6})\b/', $smsRequest['request']['order']['message']['text'], $match);
        $this->post(route('booking.verify.submit', [$this->profile->public_id, $challenge->token]), ['code' => $match[1]])->assertRedirect();
        $appointment = Appointment::firstOrFail();
        $this->assertSame('+905551234567', $appointment->phone);
        $this->assertNotNull($appointment->phone_verified_at);
        $this->assertSame('pending', $appointment->status);
        $this->post($url, $data)->assertRedirect(route('booking.verify', [$this->profile->public_id, $challenge->token]));
        $this->get(route('booking.verify', [$this->profile->public_id, $challenge->token]))->assertRedirect($appointment->statusUrl());
        $this->assertSame(1, Appointment::count());
        $this->post($url, $this->data())->assertSessionHasErrors('hour');
        $this->get($url)->assertViewHas('hours', [10, 23]);
        $this->get($appointment->statusUrl())->assertOk()->assertDontSee('0555')->assertDontSee('Ayşe Test');
        $this->assertSame(1, User::count());
        $this->assertSame(0, DB::table('invoices')->count());
        $this->assertSame(0, DB::table('services')->count());
    }

    public function test_rejection_reopens_slot_and_approval_updates_customer_status()
    {
        $appointment = $this->booking->book($this->profile, $this->data());
        $this->actingAs($this->owner)->get('/appointments')->assertOk()->assertSee('Ayşe Test');
        $this->post('/appointments/'.$appointment->id.'/status', ['status' => 'rejected'])->assertSessionHasNoErrors();
        $this->assertNull($appointment->fresh()->occupied_at);
        $second = $this->booking->book($this->profile, $this->data());
        $this->post('/appointments/'.$second->id.'/status', ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->get($second->statusUrl())->assertOk()->assertSee('onaylandı');
        $this->post('/appointments/'.$second->id.'/status', ['status' => 'completed'])->assertSessionHasErrors('status');
        $this->post('/appointments/'.$appointment->id.'/status', ['status' => 'approved'])->assertSessionHasErrors('status');
        $this->assertSame('approved', $second->fresh()->status);
        $this->post('/appointments/'.$second->id.'/status', ['status' => 'cancelled'])->assertRedirect();
        $this->assertContains(9, $this->booking->availableHours($this->profile, '2026-09-25'));
    }

    public function test_business_and_customer_tokens_cannot_access_other_business_records()
    {
        $appointment = $this->booking->book($this->profile, $this->data());
        $other = User::create(['name' => 'Other', 'email' => 'other@example.test', 'password' => 'test', 'type' => 'owner']);
        $profile = $this->booking->profileForOwner($other->id);
        $this->get($profile->publicUrl().'/talep/'.$appointment->public_token)->assertNotFound();
        $this->actingAs($other)->post('/appointments/'.$appointment->id.'/status', ['status' => 'approved'])->assertNotFound();
        $this->assertSame('pending', $appointment->fresh()->status);
        $other->update(['type' => 'client']);
        $this->get('/appointments/settings')->assertForbidden();
        auth()->logout();
        $this->get('/appointments')->assertRedirect();
    }

    public function test_closed_hours_dates_and_disabled_profile_are_rejected()
    {
        $url = $this->profile->publicUrl();
        $this->post($url, $this->data(['hour' => 8]))->assertSessionHasErrors('hour');
        $this->post($url, $this->data(['date' => '2026-09-24']))->assertSessionHasErrors('date');
        $this->post($url, $this->data(['date' => '2027-01-01']))->assertSessionHasErrors('date');
        $this->post($url, $this->data(['hour' => 12]))->assertSessionHasErrors('hour');
        $this->profile->update(['is_active' => false]);
        $this->post($url, $this->data())->assertSessionHasErrors('hour');
        $this->assertSame(0, Appointment::count());
    }

    public function test_midnight_boundary_and_weekly_schedule()
    {
        $appointment = $this->booking->book($this->profile, $this->data(['hour' => 23]));
        $this->assertSame('2026-09-26 00:00', $appointment->ends_at->format('Y-m-d H:i'));
        $this->assertSame([8, 9, 10, 23], $this->booking->availableHours($this->profile, '2026-10-02'));
        $this->assertSame([], $this->booking->availableHours($this->profile, '2026-09-26'));
    }

    public function test_basic_spam_controls()
    {
        $url = $this->profile->publicUrl();
        $this->post($url, $this->data(['website' => 'spam']))->assertStatus(422);
        for ($i = 0; $i < 4; $i++) $this->post($url, $this->data(['phone' => 'bad']))->assertSessionHasErrors('phone');
        $this->post($url, $this->data())->assertStatus(429);
        $this->assertSame(0, Appointment::count());
    }

    public function test_notifications_are_scoped_and_follow_pending_requests()
    {
        $this->getJson('/appointments/notifications')->assertUnauthorized();
        $this->actingAs($this->owner)->getJson('/appointments/notifications')->assertOk()->assertJsonPath('count', 0);
        $appointment = $this->booking->book($this->profile, $this->data());
        $this->get('/appointments')->assertOk()->assertSee('id="appointment-notifications-toggle"', false);
        $this->getJson('/appointments/notifications')->assertOk()->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.id', $appointment->id)->assertJsonPath('items.0.title', 'Ayşe Test randevu talep etti');
        $other = User::create(['name' => 'Other', 'email' => 'notify@example.test', 'password' => 'test', 'type' => 'owner']);
        $this->actingAs($other)->getJson('/appointments/notifications')->assertOk()->assertJsonPath('count', 0)->assertJsonCount(0, 'items');
        $this->assertSame(1, \App\Models\AppointmentProfile::count());
        $other->update(['type' => 'client']);
        $this->getJson('/appointments/notifications')->assertForbidden();
        $this->booking->changeStatus($this->profile, $appointment->id, 'approved');
        $this->actingAs($this->owner)->getJson('/appointments/notifications')->assertOk()->assertJsonPath('count', 0)->assertJsonCount(0, 'items');
    }

    public function test_booking_window_includes_day_seven_and_rejects_day_eight()
    {
        $this->profile->update(['weekly_hours' => array_fill(1, 7, [9])]);
        $url = $this->profile->publicUrl();
        $this->get($url)->assertOk()->assertSee('max="2026-10-02"', false);
        $this->get($url.'?date=2026-10-02')->assertOk()->assertViewHas('hours', [9]);
        $this->post($url, $this->data(['date' => '2026-10-02']))->assertSessionHasNoErrors()->assertRedirect();
        $this->get($url.'?date=2026-10-03')->assertSessionHasErrors('date');
        $this->post($url, $this->data(['date' => '2026-10-03']))->assertSessionHasErrors('date');
        $this->assertSame([], $this->booking->availableHours($this->profile, '2026-10-03'));
        $this->assertSame(0, Appointment::count());
        $this->assertSame(1, \App\Models\AppointmentSmsChallenge::count());
    }

    public function test_customer_cancel_frees_slot_and_notifies_only_owner_until_viewed()
    {
        $appointment = $this->booking->book($this->profile, $this->data(['hour' => 23]));
        $this->booking->changeStatus($this->profile, $appointment->id, 'approved');
        $url = route('booking.cancel', [$this->profile->public_id, $appointment->public_token]);
        $other = User::create(['name'=>'Other', 'email'=>'cancel@example.test', 'password'=>'test', 'type'=>'owner']);
        $otherProfile = $this->booking->profileForOwner($other->id);
        $this->post(route('booking.cancel', [$otherProfile->public_id, $appointment->public_token]))->assertNotFound();
        $this->post($url)->assertSessionHasNoErrors()->assertRedirect($appointment->statusUrl());
        $this->assertNull($appointment->fresh()->occupied_at);
        $this->assertTrue($appointment->fresh()->cancelled_by_customer);
        $this->assertContains(23, $this->booking->availableHours($this->profile, '2026-09-25'));
        $this->actingAs($other)->getJson('/appointments/notifications')->assertJsonPath('count', 0);
        $this->actingAs($this->owner)->getJson('/appointments/notifications')->assertJsonPath('count', 1)
            ->assertJsonPath('items.0.title', 'Ayşe Test randevusunu iptal etti');
        $this->get('/appointments?status=pending')->assertOk();
        $this->assertNull($appointment->fresh()->cancellation_read_at);
        $this->get('/appointments?status=cancelled')->assertOk();
        $this->assertNotNull($appointment->fresh()->cancellation_read_at);
        $this->post($url)->assertSessionHasNoErrors();
        $this->getJson('/appointments/notifications')->assertJsonPath('count', 0);
        $replacement = $this->booking->book($this->profile, $this->data(['hour'=>23]));
        $this->assertNotSame($appointment->id, $replacement->id);
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_cancellation_cutoff_boundary_and_pending_cancellation()
    {
        $appointment = $this->booking->book($this->profile, $this->data(['hour'=>23]));
        $appointment->update(['customer_cancel_until'=>now()]);
        $this->booking->cancelByCustomer($this->profile, $appointment->public_token);
        $this->assertSame('cancelled', $appointment->fresh()->status);
        $second = $this->booking->book($this->profile, $this->data(['hour'=>23]));
        $second->update(['customer_cancel_until'=>now()->subSecond()]);
        $this->post(route('booking.cancel', [$this->profile->public_id, $second->public_token]))->assertSessionHasErrors('cancellation');
        $this->assertSame('pending', $second->fresh()->status);
        $this->assertNotNull($second->fresh()->occupied_at);
    }

    public function test_expiry_frees_slot_without_expiring_approved_appointments()
    {
        $pending = $this->booking->book($this->profile, $this->data(['hour'=>23]));
        $approved = $this->booking->book($this->profile, $this->data(['hour'=>10]));
        $this->booking->changeStatus($this->profile, $approved->id, 'approved');
        $pending->update(['pending_expires_at'=>now()]);
        $approved->update(['pending_expires_at'=>now()]);
        $this->artisan('appointments:expire')->assertExitCode(0);
        $this->artisan('appointments:expire')->assertExitCode(0);
        $this->assertSame('expired', $pending->fresh()->status);
        $this->assertNull($pending->fresh()->occupied_at);
        $this->assertSame('approved', $approved->fresh()->status);
        $this->assertNotNull($approved->fresh()->occupied_at);
        $this->actingAs($this->owner)->post('/appointments/'.$pending->id.'/status', ['status'=>'approved'])->assertSessionHasErrors('status');
        $this->getJson('/appointments/notifications')->assertJsonPath('count', 0);
        $this->assertContains(23, $this->booking->availableHours($this->profile, '2026-09-25'));
        $this->booking->book($this->profile, $this->data(['hour'=>23]));
        $this->assertSame(3, Appointment::count());
    }

    public function test_lazy_expiry_persists_when_customer_cancellation_is_rejected()
    {
        $appointment = $this->booking->book($this->profile, $this->data(['hour'=>23]));
        $appointment->update(['pending_expires_at'=>now()]);
        $this->post(route('booking.cancel', [$this->profile->public_id, $appointment->public_token]))->assertSessionHasErrors('cancellation');
        $this->assertSame('expired', $appointment->fresh()->status);
        $this->assertNull($appointment->fresh()->occupied_at);
        $this->get($appointment->statusUrl())->assertOk()->assertDontSee('Randevuyu iptal et');
    }

    public function test_policy_settings_validate_and_only_change_future_request_deadlines()
    {
        $old = $this->booking->book($this->profile, $this->data(['hour'=>23]));
        $this->assertSame('2026-09-25 20:15:00', $old->pending_expires_at->toDateTimeString());
        $this->assertSame('2026-09-25 21:00:00', $old->customer_cancel_until->toDateTimeString());
        $this->actingAs($this->owner);
        $settings = ['display_name'=>'Test', 'is_active'=>1, 'hours'=>[5=>[9,10,23]], 'cancellation_cutoff_hours'=>0, 'pending_timeout_hours'=>1];
        $this->post('/appointments/settings', $settings)->assertSessionHasNoErrors();
        $new = $this->booking->book($this->profile, $this->data(['hour'=>10]));
        $this->assertSame('2026-09-25 09:15:00', $new->pending_expires_at->toDateTimeString());
        $this->assertTrue($new->customer_cancel_until->equalTo($new->starts_at));
        $this->assertTrue($old->pending_expires_at->equalTo($old->fresh()->pending_expires_at));
        $this->post('/appointments/settings', array_replace($settings, ['pending_timeout_hours'=>0, 'cancellation_cutoff_hours'=>-1]))
            ->assertSessionHasErrors(['pending_timeout_hours','cancellation_cutoff_hours']);
        $soon = $this->booking->book($this->profile, $this->data(['hour'=>9]));
        $this->assertTrue($soon->pending_expires_at->equalTo($soon->starts_at));
    }

    public function test_policy_migration_backfills_old_records_and_can_be_repeated()
    {
        $appointment = $this->booking->book($this->profile, $this->data(['hour'=>23]));
        $appointment->update(['pending_expires_at'=>null, 'customer_cancel_until'=>null]);
        $migration = require database_path('migrations/2026_09_27_000001_appointment_cancellation_and_expiry.php');
        $migration->up();
        $before = $appointment->fresh()->getAttributes();
        $this->assertNotNull($before['pending_expires_at']);
        $this->assertNotNull($before['customer_cancel_until']);
        $migration->up();
        $this->assertSame($before, $appointment->fresh()->getAttributes());
    }

    private function publishDirectory(): \App\Models\BookingService
    {
        $service = \App\Models\BookingService::firstOrFail();
        $this->profile->update(['directory_region'=>'34-avrupa','directory_visible'=>true,'public_address'=>'Test adresi']);
        DB::table('booking_offerings')->insert(['appointment_profile_id'=>$this->profile->id,'booking_service_id'=>$service->id,'vehicle_type'=>'motosiklet']);
        return $service;
    }

    public function test_directory_steps_filter_region_vehicle_service_and_visibility()
    {
        $service = $this->publishDirectory();
        $this->get('/')->assertOk()->assertSee('Konumumu kullan');
        $this->get('/randevu')->assertOk()->assertSee('İstanbul Avrupa')->assertSee('İstanbul Anadolu')->assertSee('Düzce');
        $this->get('/randevu?region=34-avrupa')->assertOk()->assertSee('Ne kullanıyorsunuz?');
        $this->get('/randevu?region=34-avrupa&vehicle=motosiklet')->assertOk()->assertSee($service->name);
        $url = '/randevu?region=34-avrupa&vehicle=motosiklet&service='.$service->id;
        $this->get($url)->assertOk()->assertSee($this->profile->display_name)->assertSee('Test adresi');
        $this->get(str_replace('34-avrupa','34-anadolu',$url))->assertOk()->assertDontSee('Test adresi');
        $this->get(str_replace('motosiklet','otomobil',$url))->assertOk()->assertDontSee('Test adresi');
        $this->profile->update(['directory_visible'=>false]);
        $this->get($url)->assertOk()->assertDontSee('Test adresi');
        $this->profile->update(['directory_visible'=>true,'is_active'=>false]);
        $this->get($url)->assertOk()->assertDontSee('Test adresi');
        $this->get('/randevu?region=unknown')->assertSessionHasErrors('region');
    }

    public function test_selected_service_survives_date_and_sms_verification_into_appointment()
    {
        $service = $this->publishDirectory();
        $selection = ['vehicle'=>'motosiklet','service'=>$service->id];
        $this->get($this->profile->publicUrl().'?'.http_build_query($selection + ['date'=>'2026-09-25']))->assertOk()
            ->assertSee('name="vehicle" value="motosiklet"', false)->assertSee($service->name);
        $this->post($this->profile->publicUrl(), $this->data($selection))->assertSessionHasNoErrors();
        $challenge = \App\Models\AppointmentSmsChallenge::firstOrFail();
        $this->post(route('booking.verify.submit', [$this->profile->public_id,$challenge->token]), ['code'=>$this->verificationCode()])->assertSessionHasNoErrors();
        $appointment = Appointment::firstOrFail();
        $this->assertSame('Motosiklet',$appointment->requested_vehicle);
        $this->assertSame($service->name,$appointment->requested_service);
        $service->update(['name'=>'Yeni ad']);
        $this->assertNotSame($service->name,$appointment->fresh()->requested_service);
        $this->actingAs($this->owner)->get('/appointments')->assertOk()->assertSee($appointment->requested_service);
    }

    public function test_service_removed_during_verification_cannot_be_booked()
    {
        $service = $this->publishDirectory();
        $this->post($this->profile->publicUrl(),$this->data(['vehicle'=>'motosiklet','service'=>$service->id]));
        $challenge = \App\Models\AppointmentSmsChallenge::firstOrFail();
        $code = $this->verificationCode();
        DB::table('booking_offerings')->delete();
        $this->post(route('booking.verify.submit',[$this->profile->public_id,$challenge->token]),['code'=>$code])->assertSessionHasErrors('service');
        $this->assertSame(0,Appointment::count());
    }

    public function test_directory_settings_and_catalog_are_role_scoped()
    {
        $service = \App\Models\BookingService::firstOrFail();
        $this->actingAs($this->owner)->post('/appointments/settings', ['display_name'=>'Servis','directory_visible'=>1,'directory_region'=>'34-avrupa','offerings'=>['motosiklet:'.$service->id]])->assertSessionHasNoErrors();
        $this->assertSame(1,DB::table('booking_offerings')->count());
        $this->post('/appointments/settings',['display_name'=>'Servis','directory_visible'=>1,'directory_region'=>'34'])->assertSessionHasErrors('directory_region');
        $this->get('/appointments/catalog')->assertForbidden();
        $this->post('/appointments/catalog',['name'=>'Test'])->assertForbidden();
        $this->owner->update(['type'=>'super admin']);
        $this->withoutMiddleware(\App\Http\Middleware\XSS::class);
        $this->get('/appointments/catalog')->assertOk();
        $this->post('/appointments/catalog',['name'=>'Yeni hizmet','vehicle_types'=>['agir-vasita'],'is_active'=>1])->assertSessionHasNoErrors();
        $this->assertSame(['agir-vasita'],\App\Models\BookingService::where('name','Yeni hizmet')->firstOrFail()->vehicle_types);
    }

    public function test_optional_verification_books_without_sms_and_cannot_be_overridden_by_guest()
    {
        $settings = \App\Models\AppointmentSmsSetting::central();
        $settings->update(['verification_required'=>false,'enabled'=>false]);
        $this->get($this->profile->publicUrl())->assertOk()->assertSee('Randevu talebi oluştur')->assertDontSee('Randevu al — SMS kodu gönder');
        $data = $this->data();
        $this->post($this->profile->publicUrl(),$data)->assertSessionHasNoErrors();
        $this->post($this->profile->publicUrl(),$data)->assertSessionHasNoErrors();
        $this->assertSame(1,Appointment::count());
        $appointment = Appointment::firstOrFail();
        $this->assertNull($appointment->phone_verified_at);
        $this->assertTrue((bool)$appointment->verification_bypassed);
        \Illuminate\Support\Facades\Http::assertNothingSent();
        $this->actingAs($this->owner)->getJson('/appointments/notifications')->assertJsonPath('count',1);
        $settings->update(['verification_required'=>true,'enabled'=>true,'api_key'=>null]);
        $this->post($this->profile->publicUrl(),$this->data(['hour'=>10,'verification_bypassed'=>true,'verification_required'=>false]))->assertSessionHasErrors('sms');
        $this->assertSame(1,Appointment::count());
    }

    public function test_sms_templates_and_verification_switch_are_admin_only()
    {
        $settings = \App\Models\AppointmentSmsSetting::central();
        $data = $settings->only(['brand','sender','daily_limit','verification_template','approval_template']);
        $data += ['enabled'=>0,'verification_required'=>0,'vehicle_sms_enabled'=>1,'vehicle_template'=>'{isletme}: {link}'];
        $this->actingAs($this->owner)->post('/appointments/sms-settings',$data)->assertForbidden();
        $this->owner->update(['type'=>'super admin']);
        $this->withoutMiddleware(\App\Http\Middleware\XSS::class);
        $this->post('/appointments/sms-settings',$data)->assertSessionHasNoErrors();
        $this->assertFalse($settings->fresh()->verification_required);
        $this->post('/appointments/sms-settings',array_replace($data,['vehicle_template'=>'Bağlantı yok']))->assertSessionHasErrors('vehicle_template');
        $this->get('/appointments/sms-settings')->assertOk()->assertSee('Araç takip bağlantısı mesajı');
    }

    private function verificationCode(): string
    {
        $request = \Illuminate\Support\Facades\Http::recorded()->last()[0];
        preg_match('/\b(\d{6})\b/', $request['request']['order']['message']['text'], $matches);
        return $matches[1];
    }

    /** @dataProvider invalidNationalPhones */
    public function test_booking_phone_requires_ten_national_mobile_digits($phone)
    {
        $this->postJson($this->profile->publicUrl(), $this->data(['phone' => $phone]))->assertStatus(422)->assertJsonValidationErrors('phone');
        \Illuminate\Support\Facades\Http::assertNothingSent();
        $this->assertSame(0, \App\Models\AppointmentSmsChallenge::count());
    }

    public static function invalidNationalPhones(): array
    {
        return [['05551234567'], ['+905551234567'], ['555123456'], ['55512345678'], ['2121234567'], ['555123456a'], ['555 123 45 67']];
    }

    public function test_phone_field_has_fixed_prefix_and_preserves_national_input_after_error()
    {
        $url = $this->profile->publicUrl();
        $this->get($url)->assertOk()->assertSee('booking-phone-prefix', false)->assertSee('maxlength="10"', false)->assertSee('pattern="5[0-9]{9}"', false);
        $this->from($url)->post($url, $this->data(['hour' => 12]))->assertSessionHasErrors('hour');
        $this->get($url)->assertSee('value="5551234567"', false)->assertDontSee('value="+905551234567"', false);
    }

    public function test_sms_code_verification_precedes_notification_and_approval_is_sent_once()
    {
        $this->post($this->profile->publicUrl(), $this->data())->assertRedirect();
        $challenge = \App\Models\AppointmentSmsChallenge::firstOrFail();
        $code = $this->verificationCode();
        $this->assertNotSame($code, $challenge->code_hash);
        $this->assertStringNotContainsString('Ayşe', DB::table('appointment_sms_challenges')->value('payload'));
        $this->actingAs($this->owner)->getJson('/appointments/notifications')->assertJsonPath('count', 0);
        $verifyUrl = route('booking.verify.submit', [$this->profile->public_id, $challenge->token]);
        $this->post($verifyUrl, ['code' => $code])->assertSessionHasNoErrors()->assertRedirect();
        $this->post($verifyUrl, ['code' => $code])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, Appointment::count());
        $appointment = Appointment::firstOrFail();
        $this->assertNotNull($appointment->phone_verified_at);
        $this->getJson('/appointments/notifications')->assertJsonPath('count', 1);
        for ($i = 0; $i < 2; $i++) $this->post('/appointments/'.$appointment->id.'/status', ['status' => 'approved'])->assertSessionHasNoErrors();
        \Illuminate\Support\Facades\Http::assertSentCount(2);
        $message = \App\Models\AppointmentSmsMessage::firstOrFail();
        $this->assertSame('accepted', $message->status);
        $this->assertStringContainsString('09:00', $message->body);
        $this->assertStringContainsString('25 Eyl 2026', $message->body);
        $this->getJson('/appointments/notifications')->assertJsonPath('count', 0);
    }

    public function test_wrong_code_limit_persists_and_expired_code_is_rejected()
    {
        $sms = app(\App\Services\AppointmentSms::class);
        $challenge = $sms->start($this->profile, $this->data(['phone' => '+905551234567']), '127.0.0.1');
        $code = $this->verificationCode();
        $url = route('booking.verify.submit', [$this->profile->public_id, $challenge->token]);
        for ($i = 0; $i < 5; $i++) $this->post($url, ['code' => '000000'])->assertSessionHasErrors('sms');
        $this->assertSame(5, (int) $challenge->fresh()->attempts);
        $this->post($url, ['code' => $code])->assertSessionHasErrors('sms');
        $challenge->update(['attempts' => 0, 'expires_at' => now()->subSecond()]);
        $this->post($url, ['code' => $code])->assertSessionHasErrors('sms');
        $this->assertSame(0, Appointment::count());
        $this->assertFalse(session()->has('_old_input.code'));
    }

    public function test_sms_resend_cooldown_and_phone_limit()
    {
        $sms = app(\App\Services\AppointmentSms::class);
        $challenge = $sms->start($this->profile, $this->data(['phone' => '+905551234567']), '127.0.0.1');
        $url = route('booking.verify.resend', [$this->profile->public_id, $challenge->token]);
        $this->post($url)->assertSessionHasErrors('sms');
        $challenge->fresh()->update(['last_sent_at' => now()->subMinutes(2)]);
        $this->post($url)->assertRedirect();
        $this->assertSame(2, (int) $challenge->fresh()->send_count);
        \Illuminate\Support\Facades\Http::assertSentCount(2);
        $challenge->fresh()->update(['last_sent_at' => now()->subMinutes(2)]);
        $this->post($url)->assertRedirect();
        $this->assertSame(3, (int) $challenge->fresh()->send_count);
        $this->post($this->profile->publicUrl(), $this->data())->assertSessionHasErrors('sms');
        \Illuminate\Support\Facades\Http::assertSentCount(3);
    }

    public function test_sms_disabled_allows_booking_and_provider_failure_still_requires_verification()
    {
        $settings = \App\Models\AppointmentSmsSetting::central();
        $settings->update(['enabled' => false]);
        $this->get($this->profile->publicUrl())->assertOk()->assertSee('name="customer_name"', false);
        $this->post($this->profile->publicUrl(), $this->data())->assertSessionHasNoErrors();
        $this->assertSame(1, Appointment::count());
        $this->assertTrue((bool) Appointment::first()->verification_bypassed);
        Appointment::query()->delete();
        \Illuminate\Support\Facades\Http::assertNothingSent();
        $settings->update(['enabled' => true]);
        \Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory());
        \Illuminate\Support\Facades\Http::fake(['api.iletimerkezi.com/*' => \Illuminate\Support\Facades\Http::response(['response' => ['status' => ['code' => 401]]], 200)]);
        $this->post($this->profile->publicUrl(), $this->data())->assertRedirect();
        $challenge = \App\Models\AppointmentSmsChallenge::firstOrFail();
        $this->assertSame('failed', $challenge->send_status);
        $this->post(route('booking.verify.submit', [$this->profile->public_id, $challenge->token]), ['code' => '123456'])->assertSessionHasErrors('sms');
        $this->assertSame(0, Appointment::count());
    }

    public function test_taken_slot_and_foreign_profile_cannot_be_verified()
    {
        $challenge = app(\App\Services\AppointmentSms::class)->start($this->profile, $this->data(['phone' => '+905551234567']), '127.0.0.1');
        $code = $this->verificationCode();
        $other = User::create(['name' => 'Other', 'email' => 'sms-other@example.test', 'password' => 'test', 'type' => 'owner']);
        $otherProfile = $this->booking->profileForOwner($other->id);
        $this->post(route('booking.verify.submit', [$otherProfile->public_id, $challenge->token]), ['code' => $code])->assertNotFound();
        $this->booking->book($this->profile, $this->data());
        $this->post(route('booking.verify.submit', [$this->profile->public_id, $challenge->token]), ['code' => $code])->assertSessionHasErrors('hour');
        $this->assertNull($challenge->fresh()->appointment_id);
        $this->assertSame(1, Appointment::count());
    }

    public function test_only_super_admin_can_manage_encrypted_central_sms_settings()
    {
        $this->actingAs($this->owner)->get('/appointments/sms-settings')->assertForbidden();
        $this->post('/appointments/sms-settings', [])->assertForbidden();
        $this->post('/appointments/sms-messages/999/retry')->assertForbidden();
        $this->owner->update(['type' => 'super admin']);
        // The legacy MySQL-only migration is intentionally skipped by this SQLite suite.
        $this->withoutMiddleware(\App\Http\Middleware\XSS::class);
        $this->get('/appointments/sms-settings')->assertOk()->assertDontSee(str_repeat('b', 32));
        $settings = \App\Models\AppointmentSmsSetting::central();
        $data = $settings->only(['brand', 'sender', 'daily_limit', 'verification_template', 'approval_template']);
        $data['enabled'] = 1;
        $data['api_hash'] = '';
        $this->post('/appointments/sms-settings', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(str_repeat('b', 32), $settings->fresh()->api_hash);
        $this->assertNotSame(str_repeat('b', 32), DB::table('appointment_sms_settings')->value('api_hash'));
        $this->post('/appointments/sms-settings', array_replace($data, ['api_hash' => 'secret-token-never-flashed', 'verification_template' => 'No code']))->assertSessionHasErrors('verification_template');
        $this->assertFalse(session()->has('_old_input.api_hash'));
        $this->assertSame(str_repeat('b', 32), $settings->fresh()->api_hash);
    }

    public function test_uncertain_approval_is_not_automatically_sent_twice()
    {
        $appointment = $this->booking->book($this->profile, $this->data(['phone' => '+905551234567']));
        $appointment->update(['phone_verified_at' => now()]);
        \Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory());
        \Illuminate\Support\Facades\Http::fake(['api.iletimerkezi.com/*' => \Illuminate\Support\Facades\Http::response([], 503)]);
        $this->actingAs($this->owner);
        for ($i = 0; $i < 2; $i++) $this->post('/appointments/'.$appointment->id.'/status', ['status' => 'approved'])->assertRedirect();
        \Illuminate\Support\Facades\Http::assertSentCount(1);
        $this->assertSame('unknown', \App\Models\AppointmentSmsMessage::first()->status);
        $this->assertSame('approved', $appointment->fresh()->status);
    }

    public function test_iletimerkezi_request_contract_and_encrypted_keys()
    {
        $result = app(\App\Services\AppointmentSmsGateway::class)->send('+905551234567', 'Randevu testi');
        $this->assertSame('accepted', $result['status']);
        $this->assertSame('12345', $result['provider_sid']);
        \Illuminate\Support\Facades\Http::assertSent(function ($request) {
            return $request->url() === 'https://api.iletimerkezi.com/v1/send-sms/json'
                && $request['request']['authentication'] === ['key' => str_repeat('a', 32), 'hash' => str_repeat('b', 32)]
                && $request['request']['order'] === ['sender' => 'SANAYI', 'iys' => '0', 'message' => [
                    'text' => 'Randevu testi', 'receipents' => ['number' => ['905551234567']]]];
        });
        $this->assertNotSame(str_repeat('a', 32), DB::table('appointment_sms_settings')->value('api_key'));
    }

    /** @dataProvider providerResponses */
    public function test_provider_failure_and_uncertainty($body, $http, $status, $error)
    {
        \Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory());
        \Illuminate\Support\Facades\Http::fake(fn () => \Illuminate\Support\Facades\Http::response($body, $http));
        $result = app(\App\Services\AppointmentSmsGateway::class)->send('+905551234567', 'Test');
        $this->assertSame($status, $result['status']);
        $this->assertSame($error, $result['error_code']);
        \Illuminate\Support\Facades\Http::assertSentCount(1);
    }

    public static function providerResponses(): array
    {
        return [
            [['response'=>['status'=>['code'=>401]]], 200, 'failed', '401'],
            [['response'=>['status'=>['code'=>450]]], 400, 'failed', '450'],
            [['response'=>['status'=>['code'=>451]]], 200, 'unknown', '451'],
            [['response'=>['status'=>['code'=>200]]], 200, 'unknown', '200'],
            [[], 503, 'unknown', 'provider_error'],
        ];
    }

    public function test_test_sender_cannot_open_booking_and_no_network_is_used()
    {
        \App\Models\AppointmentSmsSetting::central()->update(['sender'=>'APITEST']);
        $this->post($this->profile->publicUrl(), $this->data())->assertSessionHasErrors('sms');
        \Illuminate\Support\Facades\Http::assertNothingSent();
    }

    public function test_provider_error_is_retained_without_message_or_credentials()
    {
        \Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory());
        \Illuminate\Support\Facades\Http::fake(fn () => \Illuminate\Support\Facades\Http::response(['response'=>['status'=>['code'=>402, 'message'=>'private-data']]], 200));
        $this->post($this->profile->publicUrl(), $this->data())->assertRedirect();
        $challenge = \App\Models\AppointmentSmsChallenge::firstOrFail();
        $this->assertSame('402', $challenge->error_code);
        $this->assertSame('failed', $challenge->send_status);
        $this->assertStringNotContainsString('private-data', json_encode($challenge->getAttributes()));
    }

    public function test_provider_migration_preserves_records_and_clears_old_credentials()
    {
        $appointment = $this->booking->book($this->profile, $this->data());
        $before = $appointment->fresh()->getAttributes();
        DB::table('appointment_sms_settings')->update(['api_key'=>null, 'api_hash'=>null, 'sender'=>null, 'enabled'=>true, 'account_sid'=>'old', 'auth_token'=>'old']);
        $migration = require database_path('migrations/2026_09_26_000003_switch_sms_to_iletimerkezi.php');
        $migration->up();
        $settings = \App\Models\AppointmentSmsSetting::central();
        $this->assertFalse($settings->enabled);
        $this->assertNull($settings->getRawOriginal('auth_token'));
        $this->assertSame($before, $appointment->fresh()->getAttributes());
        $settings->update(['enabled'=>true, 'api_key'=>'new-key', 'api_hash'=>'new-hash', 'sender'=>'SANAYI']);
        $migration->up();
        $this->assertTrue($settings->fresh()->ready());
    }

    public function test_currency_is_fixed_for_all_settings_and_formatters()
    {
        $this->actingAs($this->owner);
        foreach (['CURRENCY'=>'USD', 'CURRENCY_SYMBOL'=>'$'] as $key=>$value) {
            DB::table('settings')->insert(['parent_id'=>$this->owner->id, 'type'=>'payment', 'name'=>$key, 'value'=>$value]);
        }
        foreach ([settings(), settingsById($this->owner->id), invoicePaymentSettings($this->owner->id), subscriptionPaymentSettings()] as $settings) {
            $this->assertSame('TRY', $settings['CURRENCY']);
            $this->assertSame('₺', $settings['CURRENCY_SYMBOL']);
        }
        $this->assertSame('1.234,50 ₺', priceFormat(1234.5));
        $this->assertSame('1.234,50 ₺', settingPriceFormat(['CURRENCY_SYMBOL'=>'$'], 1234.5));
        $this->withoutMiddleware(\App\Http\Middleware\XSS::class);
        $this->post('/settings/payment', ['CURRENCY'=>'EUR', 'CURRENCY_SYMBOL'=>'€', 'stripe_payment'=>'on'])->assertSessionHasNoErrors();
        $this->assertSame('TRY', DB::table('settings')->where('parent_id',$this->owner->id)->where('name','CURRENCY')->value('value'));
        $this->assertSame(0, DB::table('settings')->where('name','STRIPE_PAYMENT')->count());
        $this->post('/settings/company', ['company_name'=>'Servis', 'company_email'=>'test@example.test', 'company_phone'=>'2121234567', 'company_address'=>'Test', 'CURRENCY'=>'EUR', 'CURRENCY_SYMBOL'=>'€'])->assertSessionHasNoErrors();
        $this->assertSame('₺', DB::table('settings')->where('parent_id',$this->owner->id)->where('name','CURRENCY_SYMBOL')->value('value'));
    }

    public function test_retired_gateway_routes_and_shop_communication_settings_are_absent()
    {
        foreach (app('router')->getRoutes() as $route) {
            $this->assertDoesNotMatchRegularExpression('/stripe|paypal|flutterwave|razorpay|paystack|twilio/i', $route->uri());
        }
        $this->actingAs($this->owner)->withoutMiddleware(\App\Http\Middleware\XSS::class);
        $this->get('/settings')->assertOk()->assertDontSee('İşletme SMS ayarları')->assertDontSee('href="#sms_system"',false)->assertDontSee('name="server_host"',false)
            ->assertDontSee('name="CURRENCY"',false)->assertDontSee('name="CURRENCY_SYMBOL"',false)
            ->assertDontSee('stripe_payment')->assertDontSee('Twilio');
    }

    public function test_currency_migration_preserves_bank_settings()
    {
        DB::table('settings')->insert([
            ['parent_id'=>$this->owner->id,'name'=>'CURRENCY','value'=>'USD'],
            ['parent_id'=>$this->owner->id,'name'=>'STRIPE_KEY','value'=>'retired'],
            ['parent_id'=>$this->owner->id,'name'=>'bank_name','value'=>'Test banka'],
        ]);
        $migration=require database_path('migrations/2026_09_26_000004_standardize_currency_and_retire_gateways.php');
        $migration->up();
        $migration->up();
        $this->assertSame('TRY',DB::table('settings')->where('name','CURRENCY')->value('value'));
        $this->assertSame('Test banka',DB::table('settings')->where('name','bank_name')->value('value'));
        $this->assertSame(0,DB::table('settings')->where('name','STRIPE_KEY')->count());
    }
}
