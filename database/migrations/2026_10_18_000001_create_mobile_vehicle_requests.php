<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('mobile_vehicle_requests',function(Blueprint $table){
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            // Keep a tombstone if the web permanently deletes the vehicle; never
            // block web cleanup or recreate a deleted record on an old retry.
            $table->unsignedBigInteger('vehicle_id')->index();
            $table->uuid('request_key');$table->string('payload_hash',64);$table->timestamps();
            $table->unique(['owner_id','actor_id','request_key'],'mobile_vehicle_request_unique');
        });
    }
    public function down(): void { Schema::dropIfExists('mobile_vehicle_requests'); }
};
