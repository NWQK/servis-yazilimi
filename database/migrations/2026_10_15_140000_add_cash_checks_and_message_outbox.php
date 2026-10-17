<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
    public function up() {
        foreach(['invoice_payments','counter_sales','expenses'] as $table) Schema::table($table,function(Blueprint $t){$t->string('cash_channel',20)->default('unclassified');});
        foreach(['invoice_payments'=>'payment_date','counter_sales'=>'sale_date','expenses'=>'date'] as $table=>$date)Schema::table($table,fn(Blueprint $t)=>$t->index(['parent_id',$date],'cash_lookup_'.$table));
        Schema::table('stock_movements',fn(Blueprint $t)=>$t->index(['parent_id','inventory_key','id'],'inventory_checkpoint_lookup'));
        Schema::table('invoices',function(Blueprint $t){$t->string('invoice_kind',20)->default('service');$t->timestamp('customer_email_queued_at')->nullable();});
        Schema::create('cash_closings',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('parent_id');$t->date('business_date');$t->unsignedBigInteger('actor_id')->nullable();
            $t->string('actor_name');$t->decimal('opening',15,2);$t->decimal('counted',15,2);$t->decimal('expected',15,2);
            $t->decimal('difference',15,2);$t->json('snapshot');$t->string('source_hash',64);$t->text('note')->nullable();$t->timestamps();
            $t->unique(['parent_id','business_date']);
        });
        Schema::create('inventory_checkpoints',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('parent_id');$t->uuid('inventory_key');$t->bigInteger('quantity');
            $t->unsignedBigInteger('movement_id')->default(0);$t->timestamps();$t->unique(['parent_id','inventory_key']);
        });
        // Historical stock is an opening balance, never a fictitious purchase.
        DB::table('items')->whereNotNull('inventory_key')->orderBy('id')->chunkById(200,function($items){foreach($items as $item){
            DB::table('inventory_checkpoints')->insert(['parent_id'=>$item->parent_id,'inventory_key'=>$item->inventory_key,'quantity'=>$item->quantity,
                'movement_id'=>DB::table('stock_movements')->where('parent_id',$item->parent_id)->where('inventory_key',$item->inventory_key)->max('id')??0,'created_at'=>now(),'updated_at'=>now()]);
        }});
        Schema::create('integrity_checks',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('parent_id')->unique();$t->string('status',20);$t->unsignedInteger('issue_count');
            $t->json('issues');$t->timestamp('checked_at')->nullable();
        });
        Schema::create('communication_outbox',function(Blueprint $t){
            $t->id();$t->uuid('event_key')->unique();$t->unsignedBigInteger('parent_id')->nullable()->index();
            $t->string('channel',10);$t->string('status',20)->default('pending');$t->unsignedTinyInteger('priority')->default(5);
            $t->longText('payload')->nullable();$t->unsignedTinyInteger('attempts')->default(0);$t->string('error_code',60)->nullable();
            $t->timestamp('available_at')->nullable();$t->timestamp('claimed_at')->nullable();$t->timestamp('expires_at')->nullable();$t->timestamps();
            $t->index(['status','available_at','priority'],'outbox_ready');
        });
    }
    public function down() {
        foreach(['communication_outbox','integrity_checks','inventory_checkpoints','cash_closings'] as $table)Schema::dropIfExists($table);
        Schema::table('invoices',fn(Blueprint $t)=>$t->dropColumn(['invoice_kind','customer_email_queued_at']));
        foreach(['invoice_payments','counter_sales','expenses'] as $table)Schema::table($table,fn(Blueprint $t)=>$t->dropIndex('cash_lookup_'.$table));
        Schema::table('stock_movements',fn(Blueprint $t)=>$t->dropIndex('inventory_checkpoint_lookup'));
        foreach(['invoice_payments','counter_sales','expenses'] as $table)Schema::table($table,fn(Blueprint $t)=>$t->dropColumn('cash_channel'));
    }
};
