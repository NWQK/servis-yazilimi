<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['public_token', 'request_key'];
    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'occupied_at' => 'datetime',
        'approved_at' => 'datetime', 'completed_at' => 'datetime', 'phone_verified_at' => 'datetime',
        'pending_expires_at' => 'datetime', 'customer_cancel_until' => 'datetime', 'cancelled_at' => 'datetime',
        'cancellation_read_at' => 'datetime', 'cancelled_by_customer' => 'boolean'];

    public function profile() { return $this->belongsTo(AppointmentProfile::class, 'appointment_profile_id'); }
    public static function statuses(): array
    {
        return ['pending' => 'Onay bekliyor', 'approved' => 'Onaylandı', 'rejected' => 'Reddedildi',
            'cancelled' => 'İptal edildi', 'completed' => 'Tamamlandı', 'no_show' => 'Müşteri gelmedi', 'expired' => 'Yanıt süresi doldu'];
    }
    public function statusUrl(): string
    {
        return $this->profile->publicUrl() . '/talep/' . $this->public_token;
    }

    public function canCustomerCancel(): bool
    {
        return in_array($this->status, ['pending', 'approved'], true) && $this->starts_at->isFuture()
            && $this->customer_cancel_until && now()->lte($this->customer_cancel_until)
            && ($this->status !== 'pending' || ($this->pending_expires_at && $this->pending_expires_at->isFuture()));
    }
}
