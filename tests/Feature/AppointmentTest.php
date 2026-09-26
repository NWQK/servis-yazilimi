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
        \App\Models\AppointmentSmsSetting::central()->update(['enabled' => true, 'account_sid' => 'AC'.str_repeat('a', 32),
            'auth_token' => str_repeat('b', 32), 'from_number' => '+15005550006']);
        \Illuminate\Support\Facades\Http::fake(['api.twilio.com/*' => \Illuminate\Support\Facades\Http::response(['sid' => 'SM'.str_repeat('c', 32)], 201)]);
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
        preg_match('/\b(\d{6})\b/', $smsRequest['Body'], $match);
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

    private function verificationCode(): string
    {
        $request = \Illuminate\Support\Facades\Http::recorded()->last()[0];
        preg_match('/\b(\d{6})\b/', $request['Body'], $matches);
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

    public function test_sms_disabled_or_failed_never_creates_unverified_appointment()
    {
        $settings = \App\Models\AppointmentSmsSetting::central();
        $settings->update(['enabled' => false]);
        $this->get($this->profile->publicUrl())->assertOk()->assertDontSee('name="customer_name"', false);
        $this->post($this->profile->publicUrl(), $this->data())->assertSessionHasErrors('sms');
        \Illuminate\Support\Facades\Http::assertNothingSent();
        $settings->update(['enabled' => true]);
        \Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory());
        \Illuminate\Support\Facades\Http::fake(['api.twilio.com/*' => \Illuminate\Support\Facades\Http::response(['code' => 21608], 400)]);
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
        $this->owner->update(['type' => 'super admin']);
        // The legacy MySQL-only migration is intentionally skipped by this SQLite suite.
        $this->withoutMiddleware(\App\Http\Middleware\XSS::class);
        $this->get('/appointments/sms-settings')->assertOk()->assertDontSee(str_repeat('b', 32));
        $settings = \App\Models\AppointmentSmsSetting::central();
        $data = $settings->only(['brand', 'account_sid', 'from_number', 'messaging_service_sid', 'daily_limit', 'verification_template', 'approval_template']);
        $data['enabled'] = 1;
        $data['auth_token'] = '';
        $this->post('/appointments/sms-settings', $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(str_repeat('b', 32), $settings->fresh()->auth_token);
        $this->assertNotSame(str_repeat('b', 32), DB::table('appointment_sms_settings')->value('auth_token'));
        $this->post('/appointments/sms-settings', array_replace($data, ['auth_token' => 'secret-token-never-flashed', 'verification_template' => 'No code']))->assertSessionHasErrors('verification_template');
        $this->assertFalse(session()->has('_old_input.auth_token'));
        $this->assertSame(str_repeat('b', 32), $settings->fresh()->auth_token);
    }

    public function test_uncertain_approval_is_not_automatically_sent_twice()
    {
        $appointment = $this->booking->book($this->profile, $this->data(['phone' => '+905551234567']));
        $appointment->update(['phone_verified_at' => now()]);
        \Illuminate\Support\Facades\Http::swap(new \Illuminate\Http\Client\Factory());
        \Illuminate\Support\Facades\Http::fake(['api.twilio.com/*' => \Illuminate\Support\Facades\Http::response([], 503)]);
        $this->actingAs($this->owner);
        for ($i = 0; $i < 2; $i++) $this->post('/appointments/'.$appointment->id.'/status', ['status' => 'approved'])->assertRedirect();
        \Illuminate\Support\Facades\Http::assertSentCount(1);
        $this->assertSame('unknown', \App\Models\AppointmentSmsMessage::first()->status);
        $this->assertSame('approved', $appointment->fresh()->status);
    }
}
