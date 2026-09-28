<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('appointments','verification_bypassed')) Schema::table('appointments',fn (Blueprint $t)=>$t->boolean('verification_bypassed')->default(false));
        foreach (['verification_required'=>true, 'vehicle_sms_enabled'=>true] as $name=>$default) {
            if (!Schema::hasColumn('appointment_sms_settings',$name)) Schema::table('appointment_sms_settings',fn (Blueprint $t)=>$t->boolean($name)->default($default));
        }
        if (!Schema::hasColumn('appointment_sms_settings','vehicle_template')) Schema::table('appointment_sms_settings',fn (Blueprint $t)=>$t->string('vehicle_template',700)->default('{isletme} için kesilen faturaları ve yapılan işlemleri buradan görüntüleyebilirsiniz: {link} {marka}'));
        if (!Schema::hasTable('vehicle_sms_messages')) Schema::create('vehicle_sms_messages',function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('vehicle_id')->unique(); $t->unsignedBigInteger('parent_id')->index();
            $t->text('recipient')->nullable(); $t->text('body')->nullable(); $t->string('status')->default('pending');
            $t->string('error_code')->nullable(); $t->string('provider_sid')->nullable(); $t->timestamp('sent_at')->nullable(); $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::table('appointments',fn (Blueprint $t)=>$t->dropColumn('verification_bypassed'));
        Schema::dropIfExists('vehicle_sms_messages');
        Schema::table('appointment_sms_settings',fn (Blueprint $t)=>$t->dropColumn(['verification_required','vehicle_sms_enabled','vehicle_template']));
    }
};
