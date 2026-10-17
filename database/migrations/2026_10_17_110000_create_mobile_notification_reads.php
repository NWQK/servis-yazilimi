<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('mobile_notification_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->string('event', 20);
            $table->unsignedBigInteger('recovery_version')->default(0);
            $table->timestamp('read_at');
            $table->unique(['user_id', 'appointment_id', 'event', 'recovery_version'], 'mobile_notification_read_unique');
        });
    }
    public function down(): void { Schema::dropIfExists('mobile_notification_reads'); }
};
