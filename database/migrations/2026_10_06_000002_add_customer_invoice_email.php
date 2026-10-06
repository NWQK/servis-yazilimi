<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('invoices', 'customer_email_sent_at')) {
            Schema::table('invoices', fn (Blueprint $table) => $table->timestamp('customer_email_sent_at')->nullable());
        }
        foreach (\App\Models\User::where('type', 'super admin')->get() as $admin) {
            \App\Services\InvoiceCustomerEmail::template($admin);
        }
    }
    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $table) => $table->dropColumn('customer_email_sent_at'));
    }
};
