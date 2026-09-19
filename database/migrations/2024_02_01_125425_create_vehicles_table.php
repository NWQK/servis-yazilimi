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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->integer('client')->default(0);
            $table->integer('vehicle_id')->default(0);
            $table->integer('type')->default(0);
            $table->integer('brand')->default(0);
            $table->string('model')->nullable();
            $table->string('color')->nullable();
            $table->string('license_plate')->nullable();
            $table->string('engine_type')->nullable();
            $table->string('fuel_type')->nullable();
            $table->string('engine_no')->nullable();
            $table->string('chassis_no')->nullable();
            $table->integer('mileage')->default(0);
            $table->date('last_service_date')->nullable();
            $table->date('next_service_due_date')->nullable();
            $table->text('insurance_details')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->nullable();
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
        Schema::dropIfExists('vehicles');
    }
};
