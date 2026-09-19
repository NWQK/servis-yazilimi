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
        Schema::create('n8ns', function (Blueprint $table) {
            $table->id();
            $table->integer('parent_id')->default(0);
            $table->string('module')->nullable();
            $table->string('method')->nullable();
            $table->mediumText('url')->nullable();
            $table->integer('status')->default(0);
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
        Schema::dropIfExists('n8ns');
    }
};
