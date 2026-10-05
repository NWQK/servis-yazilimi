<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VehicleCatalogImport extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['entries'=>'array','active'=>'boolean'];
}
