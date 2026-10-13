<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class PlatformMaintenance
{
    public static function enabled(): bool
    {
        return DB::table('settings')->where('parent_id',0)->where('type','platform')->where('name','maintenance_enabled')->value('value') === '1';
    }
    public static function set(bool $enabled): void
    {
        DB::table('settings')->updateOrInsert(['parent_id'=>0,'type'=>'platform','name'=>'maintenance_enabled'],['value'=>$enabled ? '1' : '0']);
    }
}
