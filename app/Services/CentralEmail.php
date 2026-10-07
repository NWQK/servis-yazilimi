<?php
namespace App\Services;

use App\Models\{User, Notification};

class CentralEmail
{
    public static function administrator(): ?User
    {
        return User::where('type', 'super admin')->orderBy('id')->first();
    }

    public static function enabled(): bool
    {
        $id = self::administrator()?->id;
        return !in_array((string) \Illuminate\Support\Facades\DB::table('settings')->where('parent_id', $id)->where('type', 'smtp')->where('name', 'EMAIL_ENABLED')->value('value'), ['0', 'off'], true);
    }

    public static function template(string $module): ?Notification
    {
        $admin = self::administrator();
        $definition = defaultTemplateList()[$module] ?? null;
        if (!$admin || !$definition) return null;
        return Notification::firstOrCreate(['parent_id'=>$admin->id, 'module'=>$module], [
            'name'=>$definition['name'], 'subject'=>$definition['subject'], 'message'=>$definition['templete'],
            'short_code'=>json_encode($definition['short_code']), 'enabled_email'=>0, 'enabled_sms'=>0, 'sms_message'=>'',
        ]);
    }
}
