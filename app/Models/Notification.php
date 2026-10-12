<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    // Also protect installations that have not yet run the data migration.
    public function getNameAttribute($value)
    {
        return \App\Services\EmailBranding::text($value);
    }

    public function getSubjectAttribute($value)
    {
        return \App\Services\EmailBranding::text($value);
    }

    public function getMessageAttribute($value)
    {
        return \App\Services\EmailBranding::html($value);
    }

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
