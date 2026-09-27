<?php

namespace App\Services;

use App\Models\{Appointment, AppointmentProfile, User};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AppointmentBooking
{
    public const BOOKING_WINDOW_DAYS = 7;

    public function expirePending(?int $profileId = null): int
    {
        // Conditional update prevents a concurrently approved record from being expired.
        return Appointment::where('status', 'pending')
            ->when($profileId !== null, fn ($query) => $query->where('appointment_profile_id', $profileId))
            ->where(fn ($query) => $query->where('pending_expires_at', '<=', now())->orWhere('starts_at', '<=', now()))
            ->update(['status' => 'expired', 'occupied_at' => null, 'updated_at' => now()]);
    }

    public function cancelByCustomer(AppointmentProfile $profile, string $token): Appointment
    {
        $result = DB::transaction(function () use ($profile, $token) {
            $profile = AppointmentProfile::lockForUpdate()->findOrFail($profile->id);
            $appointment = $profile->appointments()->where('public_token', $token)->lockForUpdate()->firstOrFail();
            $this->expirePending($profile->id);
            $appointment->refresh();
            if ($appointment->status === 'cancelled' && $appointment->cancelled_by_customer) return $appointment;
            if (!$appointment->canCustomerCancel()) return 'Bu randevu için çevrimiçi iptal süresi dolmuş veya talep kapanmış. Lütfen işletmeyle iletişime geçin.';
            $appointment->update(['status' => 'cancelled', 'occupied_at' => null, 'cancelled_by_customer' => true,
                'cancelled_at' => now(), 'cancellation_read_at' => null]);
            \App\Models\AppointmentSmsMessage::where('appointment_id', $appointment->id)->where('status', 'pending')->update(['status' => 'cancelled']);
            return $appointment;
        });
        if (is_string($result)) throw ValidationException::withMessages(['cancellation' => $result]);
        return $result;
    }

    public function profileForOwner(int $ownerId): AppointmentProfile
    {
        return DB::transaction(function () use ($ownerId) {
            $owner = User::where('type', 'owner')->lockForUpdate()->findOrFail($ownerId);
            $profile = AppointmentProfile::where('owner_id', $ownerId)->first();
            if (!$profile) {
                $profile = new AppointmentProfile();
                $profile->owner_id = $ownerId;
                $profile->public_id = (string) Str::uuid();
                $profile->display_name = mb_substr(settingsById($ownerId)['company_name'] ?: $owner->name, 0, 150);
                $profile->weekly_hours = [];
                $profile->is_active = false;
                $profile->save();
            }
            return $profile;
        });
    }

    public function availableHours(AppointmentProfile $profile, string $date): array
    {
        $this->expirePending($profile->id);
        $day = CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Europe/Istanbul');
        if (!$profile->is_active || $day->lt(today()) || $day->gt(today()->addDays(self::BOOKING_WINDOW_DAYS))) return [];
        $reserved = $profile->appointments()->whereNotNull('occupied_at')->whereDate('occupied_at', $date)
            ->get()->map(fn ($appointment) => (int) $appointment->starts_at->format('G'))->all();
        return array_values(array_filter($profile->weekly_hours[$day->isoWeekday()] ?? [],
            fn ($hour) => $day->setTime($hour, 0)->isFuture() && !in_array($hour, $reserved, true)));
    }

    public function book(AppointmentProfile $profile, array $data): Appointment
    {
        return DB::transaction(function () use ($profile, $data) {
            $profile = AppointmentProfile::lockForUpdate()->findOrFail($profile->id);
            $existing = $profile->appointments()->where('request_key', $data['request_key'])->first();
            if ($existing) return $existing; // Double-click/back-button retry does not create another request.
            $selectedService = app(BookingDirectory::class)->selection($profile, $data['vehicle'] ?? null, $data['service'] ?? null);
            if (!in_array((int) $data['hour'], $this->availableHours($profile, $data['date']), true)) {
                throw ValidationException::withMessages(['hour' => 'Bu saat artık uygun değil. Lütfen başka bir saat seçin.']);
            }
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $data['date'], 'Europe/Istanbul')->setTime((int) $data['hour'], 0);
            return $profile->appointments()->create([
                'public_token' => bin2hex(random_bytes(32)), 'request_key' => $data['request_key'],
                'customer_name' => $data['customer_name'], 'phone' => $data['phone'],
                'license_plate' => $data['license_plate'] ?? null, 'notes' => $data['notes'] ?? null,
                'starts_at' => $start, 'ends_at' => $start->addHour(), 'occupied_at' => $start, 'status' => 'pending',
                'pending_expires_at' => now()->addHours($profile->pending_timeout_hours ?? 12)->min($start),
                'customer_cancel_until' => $start->subHours($profile->cancellation_cutoff_hours ?? 2),
                'requested_vehicle' => $selectedService ? config('booking_directory.vehicles.'.$data['vehicle']) : null,
                'requested_service' => $selectedService?->name,
            ]);
        });
    }

    public function changeStatus(AppointmentProfile $profile, int $id, string $status): Appointment
    {
        $result = DB::transaction(function () use ($profile, $id, $status) {
            $profile = AppointmentProfile::lockForUpdate()->findOrFail($profile->id);
            $appointment = $profile->appointments()->lockForUpdate()->findOrFail($id);
            $this->expirePending($profile->id);
            $appointment->refresh();
            if ($appointment->status === 'expired') return 'Bu talebin yanıt süresi doldu. Yeni bir randevu talebi oluşturulmalıdır.';
            if ($appointment->status === $status) return $appointment;
            $allowed = ['pending' => ['approved', 'rejected', 'cancelled'],
                'approved' => ['cancelled', 'completed', 'no_show']];
            if (!in_array($status, $allowed[$appointment->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'Bu randevu için seçilen işlem yapılamaz.']);
            }
            if ($status === 'approved' && !$appointment->starts_at->isFuture()) {
                throw ValidationException::withMessages(['status' => 'Saati geçmiş bir randevu onaylanamaz.']);
            }
            if (in_array($status, ['completed', 'no_show']) && $appointment->starts_at->isFuture()) {
                throw ValidationException::withMessages(['status' => 'Randevu saati gelmeden bu işlem yapılamaz.']);
            }
            $appointment->status = $status;
            if ($status === 'approved') $appointment->approved_at = now();
            else $appointment->occupied_at = null;
            if ($status === 'completed') $appointment->completed_at = now();
            $appointment->save();
            if ($status === 'approved') app(AppointmentSms::class)->queueApproval($appointment);
            return $appointment;
        });
        if (is_string($result)) throw ValidationException::withMessages(['status' => $result]);
        return $result;
    }
}
