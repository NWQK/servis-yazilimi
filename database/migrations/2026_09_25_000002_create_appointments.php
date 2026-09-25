<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('appointment_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('display_name', 150);
            $table->boolean('is_active')->default(false);
            $table->json('weekly_hours');
            $table->timestamps();
        });
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_profile_id')->constrained()->cascadeOnDelete();
            $table->string('public_token', 64)->unique();
            $table->uuid('request_key');
            $table->string('customer_name', 150);
            $table->string('phone', 20);
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('license_plate', 20)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('occupied_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['appointment_profile_id', 'request_key'], 'appointment_request_unique');
            $table->unique(['appointment_profile_id', 'occupied_at'], 'appointment_slot_unique');
            $table->index(['appointment_profile_id', 'status', 'starts_at'], 'appointment_list_index');
        });
    }

    public function down()
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('appointment_profiles');
    }
};
