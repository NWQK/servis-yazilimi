<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('items', function (Blueprint $table) {
            $table->uuid('inventory_key')->nullable()->unique();
        });
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->json('item_snapshot')->nullable();
            // Existing invoices have never deducted stock. Do not retroactively
            // deduct it, or create fictitious returns for those historical lines.
            $table->unsignedInteger('stock_quantity')->default(0);
        });
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->index();
            $table->uuid('inventory_key')->index();
            $table->unsignedBigInteger('invoice_item_id')->nullable()->index();
            $table->string('kind');
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->timestamps();
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->unsignedBigInteger('stock_movement_id')->nullable()->unique();
        });
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE expenses MODIFY amount DECIMAL(15,2) NOT NULL DEFAULT 0');
        }
    }

    public function down()
    {
        Schema::table('expenses', fn (Blueprint $table) => $table->dropColumn('stock_movement_id'));
        Schema::dropIfExists('stock_movements');
        Schema::table('invoice_items', fn (Blueprint $table) => $table->dropColumn(['item_snapshot', 'stock_quantity']));
        Schema::table('items', fn (Blueprint $table) => $table->dropColumn('inventory_key'));
    }
};
