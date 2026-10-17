<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('mobile_requests', function(Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('owner_id'); $table->unsignedBigInteger('actor_id');
            $table->uuid('request_key'); $table->string('payload_hash',64); $table->unsignedBigInteger('customer_id');
            $table->timestamps(); $table->unique(['owner_id','actor_id','request_key']);
        });
    }
    public function down(): void { Schema::dropIfExists('mobile_requests'); }
};
