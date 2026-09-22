<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    use HasFactory;

    public function qrCode()
    {
        return $this->hasOne(VehicleQrCode::class);
    }

    protected $fillable=[
        'client',
        'vehicle_id',
        'type',
        'name',
        'model',
        'engine_type',
        'engine_no',
        'registration_expiry_date',
        'license_plate',
        'document',
        'color',
        'notes',
        'parent_id',
    ];

    public function types()
    {
        return $this->hasOne('App\Models\VehicleType','id','type');
    }
    public function brands()
    {
        return $this->hasOne('App\Models\VehicleBrand','id','brand');
    }

    public function clients()
    {
        return $this->hasOne('App\Models\User','id','client');
    }
}

