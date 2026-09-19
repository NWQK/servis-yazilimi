<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'module',
        'name',
        'subject',
        'message',
        'short_code',
        'enabled_email',
        'enabled_sms',
        'sms_message',
        'parent_id',
        'enabled_email',
    ];
}
