<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up()
    {
        if (!Schema::hasColumn('vehicles','deleted_at')) { Schema::table('vehicles',fn(Blueprint $table)=>$table->softDeletes()); }
        if (!Schema::hasColumn('users','client_archived_at')) { Schema::table('users',fn(Blueprint $table)=>$table->timestamp('client_archived_at')->nullable()); }
        if (!Schema::hasColumn('users','client_archive_was_active')) { Schema::table('users',fn(Blueprint $table)=>$table->boolean('client_archive_was_active')->nullable()); }
    }
    public function down() { /* Keep recoverable records and invoice relationships. */ }
};
