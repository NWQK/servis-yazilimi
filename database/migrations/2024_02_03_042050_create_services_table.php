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
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->integer('service_id')->default(0);
            $table->integer('vehicle')->default(0);
            $table->integer('service_type')->default(0);
            $table->integer('client')->default(0);
            $table->date('service_date')->nullable();
            $table->time('service_time')->nullable();
            $table->date('due_date')->nullable();
            $table->time('due_time')->nullable();
            $table->integer('assign')->default(0);
            $table->string('status')->nullable();
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
        Schema::dropIfExists('services');
    }
};
