<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformBackup extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['local_available'=>'boolean', 'backup_date'=>'date', 'drive_verified_at'=>'datetime'];
}
