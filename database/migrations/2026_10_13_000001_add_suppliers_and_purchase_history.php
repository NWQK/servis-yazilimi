<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up()
    {
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('parent_id')->index();
            $t->string('company_name', 150); $t->string('contact_name', 150)->nullable();
            $t->string('phone', 20)->nullable(); $t->string('email', 254)->nullable(); $t->timestamps();
        });
        Schema::table('items', function (Blueprint $t) {
            $t->unsignedBigInteger('supplier_id')->nullable()->index();
            $t->unsignedInteger('stock_alert_quantity')->nullable();
        });
        Schema::create('item_purchases', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('parent_id'); $t->unsignedBigInteger('item_id')->nullable()->index();
            $t->uuid('inventory_key')->index(); $t->unsignedBigInteger('supplier_id')->nullable()->index();
            $t->string('item_title'); $t->string('supplier_name', 150)->nullable();
            $t->string('unit_name')->nullable(); $t->unsignedInteger('quantity'); $t->decimal('unit_price', 15, 2);
            $t->decimal('amount', 20, 2); $t->date('purchase_date'); $t->text('notes')->nullable();
            $t->unsignedBigInteger('stock_movement_id')->unique(); $t->uuid('request_key')->nullable(); $t->timestamps();
            $t->unique(['parent_id', 'request_key']); $t->index(['parent_id', 'created_at']);
        });
    }
    public function down()
    {
        Schema::dropIfExists('item_purchases');
        Schema::table('items', fn (Blueprint $t) => $t->dropColumn(['supplier_id', 'stock_alert_quantity']));
        Schema::dropIfExists('suppliers');
    }
};
