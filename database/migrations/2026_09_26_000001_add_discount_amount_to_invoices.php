<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasColumn('invoices', 'discount_amount')) {
            Schema::table('invoices', fn (Blueprint $table) => $table->decimal('discount_amount', 12, 2)->default(0));
        }
    }

    public function down()
    {
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('discount_amount'));
    }
};
