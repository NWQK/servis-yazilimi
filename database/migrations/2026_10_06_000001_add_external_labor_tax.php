<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['services', 'invoices'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('external_labor_tax_id')->nullable();
                $table->decimal('external_labor_tax_rate', 8, 4)->default(0);
                $table->string('external_labor_tax_title')->nullable();
            });
        }
    }
    public function down(): void
    {
        foreach (['services', 'invoices'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn(['external_labor_tax_id', 'external_labor_tax_rate', 'external_labor_tax_title']));
        }
    }
};
