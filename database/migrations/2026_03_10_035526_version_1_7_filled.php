<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->integer('enabled_openai')->default(0)->after('enabled_logged_history');
            $table->integer('enabled_n8n')->default(0)->after('enabled_openai');
        });

        Schema::table('service_items', function (Blueprint $table) {
            $table->string('tax')->nullable()->after('rate');
        });

        Schema::table('service_types', function (Blueprint $table) {
            $table->string('tax')->nullable()->after('rate');
        });

        Schema::table('quotation_services', function (Blueprint $table) {
            $table->string('tax')->nullable()->after('rate');
        });

        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->string('transaction_id')->nullable()->after('amount');
            $table->string('receipt')->nullable()->after('transaction_id');
            $table->string('payment_type')->nullable()->after('receipt');
            $table->string('payment_status')->nullable()->after('payment_type');
        });

        DB::statement('ALTER TABLE `service_items` CHANGE `rate` `rate` DECIMAL(15,2) NOT NULL DEFAULT "0"');
        DB::statement('ALTER TABLE `service_types` CHANGE `rate` `rate` DECIMAL(15,2) NOT NULL DEFAULT "0"');
    }

    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('enabled_openai');
            $table->dropColumn('enabled_n8n');
        });

        Schema::table('service_items', function (Blueprint $table) {
            $table->dropColumn('tax');
        });

        Schema::table('service_types', function (Blueprint $table) {
            $table->dropColumn('tax');
        });

        Schema::table('quotation_services', function (Blueprint $table) {
            $table->dropColumn('tax');
        });

        DB::statement('ALTER TABLE `service_items` CHANGE `rate` `rate` DECIMAL(8,2) NOT NULL DEFAULT "0"');
        DB::statement('ALTER TABLE `service_types` CHANGE `rate` `rate` DECIMAL(8,2) NOT NULL DEFAULT "0"');
    }
};
