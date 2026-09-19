<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->integer('quotation_id')->default(0);
            $table->integer('client_id')->default(0);
            $table->integer('vehicle_id')->default(0);
            $table->date('quotation_date')->nullable();
            $table->string('status')->nullable();
            $table->text('notes')->nullable();
            $table->integer('convert_service')->default(0);
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
        Schema::dropIfExists('quotations');
    }
};
