<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlatformBackupSetting extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['enabled'=>'boolean', 'requested_at'=>'datetime', 'last_started_at'=>'datetime', 'last_finished_at'=>'datetime'];
    public static function central(): self
    {
        return static::firstOrCreate(['id'=>1], ['installation_id'=>(string) Str::uuid()]);
    }
}
