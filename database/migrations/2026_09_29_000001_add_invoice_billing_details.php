<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('invoice_business_profiles')) {
            Schema::create('invoice_business_profiles', function (Blueprint $table) {
                $table->unsignedBigInteger('parent_id')->primary();
                $table->json('details');
            });
        }
        foreach (['business_details', 'customer_details'] as $column) {
            if (!Schema::hasColumn('invoices', $column)) {
                Schema::table('invoices', fn (Blueprint $table) => $table->json($column)->nullable());
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_business_profiles');
        foreach (['business_details', 'customer_details'] as $column) {
            if (Schema::hasColumn('invoices', $column)) Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn($column));
        }
    }
};
