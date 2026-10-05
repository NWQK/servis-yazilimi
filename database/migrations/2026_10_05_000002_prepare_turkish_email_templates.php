<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        $defaults = defaultTemplateList();
        $oldDefaults = legacyEmailTemplateList();
        foreach (DB::table('users')->whereIn('type', ['owner','super admin'])->pluck('id') as $ownerId) {
            foreach ($defaults as $module => $definition) {
                $existing = DB::table('notifications')->where('parent_id',$ownerId)->where('module',$module)->first();
                $values = ['name'=>$definition['name'],'subject'=>$definition['subject'], 'message'=>$definition['templete'], 'short_code'=>json_encode($definition['short_code'])];
                if (!$existing) {
                    DB::table('notifications')->insert($values + ['parent_id'=>$ownerId,'module'=>$module,'enabled_email'=>0,'enabled_sms'=>0,'sms_message'=>$definition['sms_message'],'created_at'=>now(),'updated_at'=>now()]);
                } else {
                    // Only replace untouched factory content; custom messages and enabled flags remain intact.
                    if (trim($existing->subject) !== trim($oldDefaults[$module]['subject']) || trim($existing->message) !== trim($oldDefaults[$module]['templete'])) {
                        $values = ['short_code'=>json_encode($definition['short_code'])];
                    }
                    DB::table('notifications')->where('id',$existing->id)->update($values);
                }
            }
        }
    }
    public function down() { /* Preserve customized templates and notification preferences. */ }
};
