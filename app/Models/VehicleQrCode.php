<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleQrCode extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['printed_at' => 'datetime', 'assigned_at' => 'datetime'];

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function scopeAvailable($query)
    {
        return $query->whereNull('vehicle_id')->whereNull('assigned_at');
    }

    public function getLabelAttribute()
    {
        return 'QR-' . str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }

    public function publicUrl()
    {
        // Always encode the configured permanent origin, never the request Host header.
        return rtrim(config('app.url'), '/') . '/q/' . $this->token;
    }
}
