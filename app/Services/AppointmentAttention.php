<?php

namespace App\Services;

use App\Models\Appointment;
use Illuminate\Database\Eloquent\Builder;

class AppointmentAttention
{
    /** GET requests do not expire bookings or change the web acknowledgement. */
    public function query(int $owner, bool $includeAcknowledgedCancellations = false): Builder
    {
        return Appointment::whereHas('profile', fn ($q) => $q->where('owner_id', $owner))
            ->where(function ($q) use ($includeAcknowledgedCancellations) {
                $q->where(fn ($pending) => $pending->where('status', 'pending')
                    ->where(fn ($expiry) => $expiry->whereNull('pending_expires_at')->orWhere('pending_expires_at', '>', now())))
                    ->orWhere(function ($cancelled) use ($includeAcknowledgedCancellations) {
                        $cancelled->where('status', 'cancelled')->where('cancelled_by_customer', true);
                        if (!$includeAcknowledgedCancellations) $cancelled->whereNull('cancellation_read_at');
                    });
            });
    }
}
