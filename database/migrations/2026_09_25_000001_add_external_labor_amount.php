<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        foreach (['services', 'invoices'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->decimal('external_labor_amount', 12, 2)->default(0);
            });
        }
    }

    public function down()
    {
        foreach (['invoices', 'services'] as $tableName) {
            Schema::table($tableName, fn (Blueprint $table) => $table->dropColumn('external_labor_amount'));
        }
    }
};
