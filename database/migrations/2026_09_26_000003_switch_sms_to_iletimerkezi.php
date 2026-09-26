<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
return new class extends Migration {
    public function up(): void
    {
        foreach (['api_key', 'api_hash', 'sender'] as $column) {
            if (!Schema::hasColumn('appointment_sms_settings', $column)) {
                Schema::table('appointment_sms_settings', fn (Blueprint $t) => $t->text($column)->nullable());
            }
        }
        // Retire credentials, preserving appointments and message history.
        DB::table('appointment_sms_settings')->whereNull('api_key')->update(['enabled' => false,
            'account_sid' => null, 'auth_token' => null, 'from_number' => null, 'messaging_service_sid' => null]);
        DB::table('settings')->whereIn('name', ['twilio_sid', 'twilio_token', 'twilio_from_number', 'twilio_whatsapp_number'])->delete();
        foreach (['error_code', 'provider_sid'] as $column) {
            if (!Schema::hasColumn('appointment_sms_challenges', $column)) {
                Schema::table('appointment_sms_challenges', fn (Blueprint $t) => $t->string($column)->nullable());
            }
        }
    }
    public function down(): void
    {
        DB::table('appointment_sms_settings')->update(['enabled' => false]);
        Schema::table('appointment_sms_settings', fn (Blueprint $t) => $t->dropColumn(['api_key', 'api_hash', 'sender']));
        Schema::table('appointment_sms_challenges', fn (Blueprint $t) => $t->dropColumn(['error_code', 'provider_sid']));
    }
};
