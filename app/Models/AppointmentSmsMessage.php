<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentSmsMessage extends Model
{
    protected $guarded = [];
    protected $hidden = ['recipient', 'body'];
    protected $casts = ['recipient' => 'encrypted', 'body' => 'encrypted', 'sent_at' => 'datetime'];
    public function appointment() { return $this->belongsTo(Appointment::class); }
}
