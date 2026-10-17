<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('mobile_appointment_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->uuid('request_key');
            $table->string('payload_hash',64);
            $table->timestamps();
            $table->unique(['owner_id','actor_id','request_key'],'mobile_appointment_operation_unique');
        });
    }
    public function down(): void { Schema::dropIfExists('mobile_appointment_operations'); }
};
