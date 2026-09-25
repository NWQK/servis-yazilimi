<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['public_token', 'request_key'];
    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'occupied_at' => 'datetime',
        'approved_at' => 'datetime', 'completed_at' => 'datetime', 'phone_verified_at' => 'datetime'];

    public function profile() { return $this->belongsTo(AppointmentProfile::class, 'appointment_profile_id'); }
    public static function statuses(): array
    {
        return ['pending' => 'Onay bekliyor', 'approved' => 'Onaylandı', 'rejected' => 'Reddedildi',
            'cancelled' => 'İptal edildi', 'completed' => 'Tamamlandı', 'no_show' => 'Müşteri gelmedi'];
    }
    public function statusUrl(): string
    {
        return $this->profile->publicUrl() . '/talep/' . $this->public_token;
    }
}
