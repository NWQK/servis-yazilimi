<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;
    protected $fillable = [
        'client_id',
        'user_id',
        'gender',
        'city',
        'state',
        'country',
        'zip_code',
        'address',
        'parent_id',
        'notes',
    ];

    protected static function booted(): void
    {
        static::created(function (self $client) {
            \Illuminate\Support\Facades\DB::afterCommit(fn () => app(\App\Services\BusinessReviews::class)->registered((int)$client->user_id));
        });
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'client', 'user_id');
    }

    public function services()
    {
        return $this->hasMany(Service::class, 'client', 'user_id');
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class, 'client_id', 'user_id');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class, 'client', 'user_id');
    }
}
