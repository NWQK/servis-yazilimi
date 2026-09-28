<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class VehicleSmsMessage extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['recipient'=>'encrypted','body'=>'encrypted','sent_at'=>'datetime'];
}
