<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BookingService extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['vehicle_types'=>'array', 'is_active'=>'boolean'];
}
