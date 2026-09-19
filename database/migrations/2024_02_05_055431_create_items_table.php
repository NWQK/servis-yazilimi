<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('item_code')->nullable();
            $table->integer('quantity')->default(0);
            $table->integer('units')->default(0);
            $table->float('purchase_price')->default(0);
            $table->float('sales_price')->default(0);
            $table->string('manufacturer_by')->nullable();
            $table->string('taxs')->nullable();
            $table->date('purchase_date')->nullable();
            $table->text('warranty_information')->nullable();
            $table->text('notes')->nullable();
            $table->integer('parent_id')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('items');
    }
};
