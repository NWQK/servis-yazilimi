<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceItem extends Model
{
    use HasFactory;
    protected static function booted(): void {
        static::saved(fn(self $line)=>app(\App\Services\PublicVehicleHistory::class)->capture((int)$line->service_id));
    }
    protected $fillable = [
        'service_id',
        'type_id',
        'rate',
        'tax',
        'note',
        'parent_id',

    ];
}
