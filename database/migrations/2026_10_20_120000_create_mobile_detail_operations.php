<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('mobile_detail_operations',function(Blueprint $t){
            $t->id();$t->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedBigInteger('recovery_version');$t->uuid('request_key');
            $t->string('resource',30);$t->unsignedBigInteger('record_id');$t->string('action',30);
            $t->char('payload_hash',64);$t->json('result');$t->timestamps();
            $t->unique(['owner_id','actor_id','recovery_version','request_key'],'mobile_detail_operation_unique');
        });
    }
    public function down(): void { Schema::dropIfExists('mobile_detail_operations'); }
};
