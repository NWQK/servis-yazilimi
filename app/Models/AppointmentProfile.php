<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentProfile extends Model
{
    protected $guarded = ['id', 'public_id', 'owner_id'];
    protected $casts = ['weekly_hours' => 'array', 'is_active' => 'boolean'];

    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function appointments() { return $this->hasMany(Appointment::class); }
    public function publicUrl() { return rtrim(config('app.url'), '/') . '/randevu/' . $this->public_id; }
    public static function days(): array
    {
        return [1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar'];
    }
}
