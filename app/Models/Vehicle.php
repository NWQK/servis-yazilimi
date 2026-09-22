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
        'brand',
        'name',
        'model',
        'engine_type',
        'engine_no',
        'fuel_type',
        'chassis_no',
        'mileage',
        'last_service_date',
        'next_service_due_date',
        'insurance_details',
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

    // Retain legacy database names and role permissions while using a
    // manufacturer -> catalogue model hierarchy throughout the application.
    public function getBrandNameAttribute()
    {
        return $this->types?->type;
    }

    public function getModelAttribute($value)
    {
        return $this->brands?->name ?? $value;
    }

    public function getDisplayNameAttribute()
    {
        return trim(($this->brand_name ?? '') . ' ' . ($this->model ?? ''));
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

