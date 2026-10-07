<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up() {
  Schema::create('announcements',function(Blueprint $t){$t->id();$t->unsignedBigInteger('actor_id');$t->string('title',150);$t->text('body');$t->string('audience',20);$t->boolean('published')->default(true);$t->timestamps();});
  Schema::create('announcement_recipients',function(Blueprint $t){$t->id();$t->foreignId('announcement_id')->constrained()->cascadeOnDelete();$t->unsignedBigInteger('owner_id')->index();$t->timestamp('read_at')->nullable();$t->index(['owner_id','read_at']);$t->unique(['announcement_id','owner_id']);});
  Schema::create('admin_audit_logs',function(Blueprint $t){$t->id();$t->unsignedBigInteger('actor_id')->nullable()->index();$t->string('actor_name');$t->unsignedBigInteger('owner_id')->nullable()->index();$t->string('action',80)->index();$t->json('before')->nullable();$t->json('after')->nullable();$t->timestamp('created_at')->index();});
 }
 public function down(){Schema::dropIfExists('admin_audit_logs');Schema::dropIfExists('announcement_recipients');Schema::dropIfExists('announcements');}
};
