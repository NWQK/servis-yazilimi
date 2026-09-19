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
        Schema::table('notifications', function (Blueprint $table) {
            $table->text('sms_message')->nullable();
            $table->integer('enabled_sms')->default(0);
        });

        Schema::table('service_types', function (Blueprint $table) {
            $table->integer('rate')->default(0);
            $table->text('note')->nullable();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('service_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn('sms_message');
            $table->dropColumn('enabled_sms');
        });

        Schema::table('service_types', function (Blueprint $table) {
            $table->dropColumn('rate');
            $table->dropColumn('note');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->integer('service_type')->default(0);
        });
    }
};
