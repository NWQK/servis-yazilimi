<?php
use Illuminate\Database\Migrations\Migration;
return new class extends Migration {
    public function up(): void
    {
        app(\App\Services\OwnerLoginSecurity::class)->purge();
        foreach (\App\Models\User::where('type','super admin')->get() as $admin) {
            foreach (array_keys(\App\Services\SecurityEmail::definitions()) as $module) {
                \App\Services\SecurityEmail::template($admin,$module);
            }
        }
    }
    public function down(): void { /* Preserve templates customized by the administrator. */ }
};
