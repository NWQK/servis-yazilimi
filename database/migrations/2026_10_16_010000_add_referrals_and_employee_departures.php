<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(){
        Schema::table('users',function(Blueprint $t){$t->unsignedInteger('session_epoch')->default(0);$t->timestamp('departed_at')->nullable();});
        Schema::create('employee_departures',function(Blueprint $t){$t->id();$t->unsignedBigInteger('parent_id')->index();$t->unsignedBigInteger('employee_id')->index();$t->unsignedBigInteger('actor_id')->nullable();$t->string('employee_name');$t->json('assignments');$t->timestamps();});
        Schema::create('branch_referrals',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('group_id')->index();$t->unsignedBigInteger('source_owner_id')->index();$t->unsignedBigInteger('target_owner_id')->index();
            $t->unsignedBigInteger('source_vehicle_id');$t->unsignedBigInteger('target_service_id')->nullable();$t->unsignedBigInteger('created_by');$t->unsignedBigInteger('resolved_by')->nullable();
            $t->string('source_name');$t->string('target_name');$t->longText('shared_data');$t->string('status',20)->default('pending');$t->unsignedInteger('version')->default(1);
            $t->uuid('request_key')->unique();$t->string('request_hash',64);$t->string('accept_hash',64)->nullable();$t->timestamps();$t->timestamp('resolved_at')->nullable();
        });
    }
    public function down(){Schema::dropIfExists('branch_referrals');Schema::dropIfExists('employee_departures');Schema::table('users',fn(Blueprint $t)=>$t->dropColumn(['session_epoch','departed_at']));}
};
