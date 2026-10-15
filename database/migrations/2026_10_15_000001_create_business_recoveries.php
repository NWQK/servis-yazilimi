<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::table('users',fn(Blueprint $t)=>$t->unsignedInteger('recovery_version')->default(0));
  Schema::create('business_recoveries',function(Blueprint $t){
   $t->uuid('id')->primary();$t->unsignedBigInteger('owner_id')->index();$t->unsignedBigInteger('actor_id');
   $t->unsignedBigInteger('backup_id')->nullable();$t->uuid('source_recovery_id')->nullable();$t->string('mode',20);$t->string('status',30)->index();
   $t->json('summary')->nullable();$t->string('live_hash',64)->nullable();$t->string('source_hash',64)->nullable();
   $t->string('checkpoint_hash',64)->nullable();$t->string('error',500)->nullable();
   $t->timestamp('requested_at')->nullable();$t->timestamp('started_at')->nullable();$t->timestamp('finished_at')->nullable();
   $t->timestamp('expires_at')->nullable();$t->timestamp('checkpoint_expires_at')->nullable();$t->timestamps();
  });
  Schema::create('business_recovery_locks',function(Blueprint $t){$t->unsignedBigInteger('owner_id')->primary();$t->uuid('recovery_id')->unique();$t->timestamp('created_at');});
 }
 public function down(): void {Schema::dropIfExists('business_recovery_locks');Schema::dropIfExists('business_recoveries');Schema::table('users',fn(Blueprint $t)=>$t->dropColumn('recovery_version'));}
};
