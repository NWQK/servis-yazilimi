<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            // Keep service IDs so existing business offerings remain selected.
            DB::table('booking_services')->where('name', 'Mekanik onarım')
                ->update(['name' => 'Motor ve mekanik', 'updated_at' => now()]);

            foreach (['Anahtar hizmeti', 'Egzoz ve emisyon hizmeti'] as $name) {
                if (!DB::table('booking_services')->where('name', $name)->exists()) {
                    DB::table('booking_services')->insert([
                        'name' => $name,
                        'vehicle_types' => json_encode(['otomobil', 'motosiklet', 'agir-vasita']),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Retain catalog data: businesses may already have selected these services.
    }
};
