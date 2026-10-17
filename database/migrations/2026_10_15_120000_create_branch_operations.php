<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('branch_staff_access', function(Blueprint $t){
            $t->id();
            $t->foreignId('group_id')->constrained('business_groups')->restrictOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->json('modules');
            $t->boolean('can_write')->default(false);
            $t->boolean('can_delete')->default(false);
            $t->timestamps();
            $t->unique(['user_id','owner_id']);
            $t->index(['group_id','owner_id']);
        });
        Schema::create('branch_activity_logs', function(Blueprint $t){
            $t->id(); $t->unsignedBigInteger('group_id'); $t->unsignedBigInteger('owner_id')->nullable();
            $t->unsignedBigInteger('actor_id')->nullable(); $t->string('actor_name',191);
            $t->string('action',100); $t->string('route_name',120)->nullable();
            $t->json('details')->nullable(); $t->timestamp('created_at');
            $t->index(['group_id','created_at']); $t->index(['owner_id','created_at']);
        });
        Schema::create('branch_stock_transfers', function(Blueprint $t){
            $t->id(); $t->foreignId('group_id')->constrained('business_groups')->restrictOnDelete();
            $t->unsignedBigInteger('source_owner_id'); $t->unsignedBigInteger('target_owner_id');
            $t->unsignedBigInteger('source_item_id'); $t->uuid('source_inventory_key');
            $t->unsignedBigInteger('target_item_id')->nullable(); $t->uuid('target_inventory_key')->nullable();
            $t->json('snapshot'); $t->unsignedInteger('quantity');
            $t->string('status',20)->default('pending'); $t->unsignedBigInteger('version')->default(1);
            $t->uuid('request_key'); $t->char('request_hash',64); $t->char('receipt_hash',64)->nullable();
            $t->unsignedBigInteger('created_by'); $t->unsignedBigInteger('resolved_by')->nullable();
            $t->string('note',500)->nullable(); $t->timestamp('resolved_at')->nullable(); $t->timestamps();
            $t->unique(['group_id','request_key']);
            $t->index(['source_owner_id','status']); $t->index(['target_owner_id','status']);
        });
    }
    public function down()
    {
        // No implicit reversal of stock movements when uninstalling the feature.
        Schema::dropIfExists('branch_stock_transfers');
        Schema::dropIfExists('branch_activity_logs');
        Schema::dropIfExists('branch_staff_access');
    }
};
