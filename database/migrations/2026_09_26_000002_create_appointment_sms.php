<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasTable('appointment_sms_settings')) Schema::create('appointment_sms_settings', function (Blueprint $t) {
            $t->unsignedInteger('id')->primary();
            $t->boolean('enabled')->default(false);
            $t->string('brand')->default('sanayirandevu.com');
            $t->string('account_sid')->nullable();
            $t->text('auth_token')->nullable();
            $t->string('from_number')->nullable();
            $t->string('messaging_service_sid')->nullable();
            $t->unsignedInteger('daily_limit')->default(100);
            $t->text('verification_template');
            $t->text('approval_template');
            $t->timestamps();
        });
        if (!Schema::hasTable('appointment_sms_challenges')) Schema::create('appointment_sms_challenges', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('appointment_profile_id')->index();
            $t->string('token', 64)->unique();
            $t->uuid('request_key');
            $t->string('phone_hash', 64)->index();
            $t->string('ip_hash', 64)->index();
            $t->text('payload');
            $t->string('code_hash')->nullable();
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->unsignedTinyInteger('send_count')->default(1);
            $t->string('send_status')->default('sending');
            $t->dateTime('expires_at');
            $t->dateTime('last_sent_at');
            $t->unsignedBigInteger('appointment_id')->nullable();
            $t->timestamps();
            $t->unique(['appointment_profile_id', 'request_key'], 'appointment_sms_request_unique');
        });
        if (!Schema::hasTable('appointment_sms_messages')) Schema::create('appointment_sms_messages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('appointment_id')->unique();
            $t->text('recipient');
            $t->text('body');
            $t->string('status')->default('pending');
            $t->string('provider_sid')->nullable();
            $t->string('error_code')->nullable();
            $t->dateTime('sent_at')->nullable();
            $t->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('appointment_sms_messages');
        Schema::dropIfExists('appointment_sms_challenges');
        Schema::dropIfExists('appointment_sms_settings');
    }
};
