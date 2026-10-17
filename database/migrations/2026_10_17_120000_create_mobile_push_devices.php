<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('mobile_push_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('access_token_id')->unique()->constrained('personal_access_tokens')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash',64)->unique();
            $table->text('encrypted_token');
            $table->string('platform',10);
            $table->timestamps();
        });
        Schema::create('mobile_push_outbox', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('mobile_push_devices')->cascadeOnDelete();
            $table->string('event',20);
            $table->unsignedBigInteger('recovery_version')->default(0);
            $table->string('status',20)->default('pending')->index();
            $table->timestamps();
            $table->unique(['appointment_id','device_id','event','recovery_version'],'mobile_push_event_unique');
        });
    }
    public function down(): void {
        Schema::dropIfExists('mobile_push_outbox');
        Schema::dropIfExists('mobile_push_devices');
    }
};
