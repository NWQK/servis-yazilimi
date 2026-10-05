<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasColumn('users','subscription_suspended_at')) {
            Schema::table('users',fn(Blueprint $table)=>$table->timestamp('subscription_suspended_at')->nullable());
        }
        if (!Schema::hasColumn('users','subscription_suspension_reason')) {
            Schema::table('users',fn(Blueprint $table)=>$table->string('subscription_suspension_reason',500)->nullable());
        }
        if (!Schema::hasTable('subscription_changes')) { Schema::create('subscription_changes',function(Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('owner_id')->index(); $table->unsignedBigInteger('actor_id');
            $table->string('action',30); $table->json('before'); $table->json('after'); $table->timestamps();
        }); }
    }
    public function down()
    {
        Schema::dropIfExists('subscription_changes');
        Schema::table('users',fn(Blueprint $table)=>$table->dropColumn(['subscription_suspended_at','subscription_suspension_reason']));
    }
};
