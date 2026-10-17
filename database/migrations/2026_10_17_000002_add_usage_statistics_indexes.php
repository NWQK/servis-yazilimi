<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $indexes = [
        'invoices' => ['parent_id', 'invoice_date', 'id'],
        'invoice_items' => ['parent_id', 'invoice_id', 'item'],
        'services' => ['parent_id', 'status', 'service_date', 'id'],
        'service_items' => ['parent_id', 'service_id', 'type_id'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $columns) {
            if (!Schema::hasIndex($table, 'usage_lookup_'.$table)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, 'usage_lookup_'.$table));
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $columns) {
            if (Schema::hasIndex($table, 'usage_lookup_'.$table)) {
                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex('usage_lookup_'.$table));
            }
        }
    }
};
