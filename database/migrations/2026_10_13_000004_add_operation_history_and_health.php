<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up()
    {
        Schema::table('invoices', fn(Blueprint $t)=>$t->unsignedBigInteger('revision')->default(0));
        Schema::table('stock_movements', function(Blueprint $t){
            $t->unsignedBigInteger('actor_id')->nullable(); $t->string('actor_name')->nullable();
            $t->string('item_title')->nullable(); $t->unsignedBigInteger('invoice_id')->nullable();
            $t->index(['parent_id','created_at']);
            $t->index('created_at');
        });
        Schema::create('invoice_changes', function(Blueprint $t){
            $t->id(); $t->unsignedBigInteger('parent_id'); $t->unsignedBigInteger('invoice_id');
            $t->string('invoice_number')->nullable(); $t->unsignedBigInteger('actor_id')->nullable(); $t->string('actor_name')->nullable();
            $t->string('action',70); $t->json('changes'); $t->uuid('operation_key')->nullable(); $t->string('request_hash',64)->nullable();
            $t->string('redirect_to',1000)->nullable(); $t->timestamp('created_at')->index();
            $t->index(['parent_id','invoice_id','created_at']); $t->index(['parent_id','created_at']); $t->unique(['parent_id','operation_key']);
        });
        Schema::create('system_health', function(Blueprint $t){
            $t->string('component',50)->primary(); $t->string('status',20); $t->string('code',80)->nullable();
            $t->unsignedInteger('consecutive_failures')->default(0); $t->timestamp('last_success_at')->nullable(); $t->timestamp('last_failure_at')->nullable(); $t->timestamp('checked_at')->useCurrent();
        });
        Schema::create('system_health_errors', function(Blueprint $t){
            $t->id(); $t->string('component',50); $t->string('code',80); $t->date('day'); $t->unsignedInteger('count')->default(1);
            $t->timestamp('last_seen_at'); $t->unique(['component','code','day']); $t->index('day');
        });
    }
    public function down()
    {
        Schema::dropIfExists('system_health_errors'); Schema::dropIfExists('system_health'); Schema::dropIfExists('invoice_changes');
        Schema::table('invoices',fn(Blueprint $t)=>$t->dropColumn('revision'));
        Schema::table('stock_movements',function(Blueprint $t){$t->dropIndex(['parent_id','created_at']);$t->dropIndex(['created_at']);$t->dropColumn(['actor_id','actor_name','item_title','invoice_id']);});
    }
};
