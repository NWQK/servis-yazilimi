<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleCapacityRequest extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['owner_id' => 'integer', 'subscription_id' => 'integer', 'vehicles' => 'integer', 'amount' => 'decimal:2', 'reviewed_at' => 'datetime'];
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
}
