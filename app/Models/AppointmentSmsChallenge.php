<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentSmsChallenge extends Model
{
    protected $guarded = [];
    protected $hidden = ['payload', 'code_hash', 'token', 'phone_hash', 'ip_hash'];
    protected $casts = ['payload' => 'encrypted:array', 'expires_at' => 'datetime', 'last_sent_at' => 'datetime'];
}
