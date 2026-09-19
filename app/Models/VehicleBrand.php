<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
class VehicleBrand extends Model
{
    use HasFactory;
    protected $fillable=[
        'name',
        'type',
        'parent_id',
    ];

    public function types()
    {
        return $this->hasOne('App\Models\VehicleType','id','type');
    }
}
