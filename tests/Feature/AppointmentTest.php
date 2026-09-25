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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function data(array $changes = []): array
    {
        return array_replace(['customer_name' => 'Ayşe Test', 'phone' => '0555 123 45 67', 'date' => '2026-09-25',
            'hour' => 9, 'request_key' => (string) Str::uuid(), 'notes' => 'Yağ bakımı'], $changes);
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
        $appointment = Appointment::firstOrFail();
        $this->assertSame('+905551234567', $appointment->phone);
        $this->assertNull($appointment->phone_verified_at);
        $this->assertSame('pending', $appointment->status);
        $this->post($url, $data)->assertRedirect($appointment->statusUrl());
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
}
