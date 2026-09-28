<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            $services = DB::table('booking_services')->whereIn('name', ['Anahtar hizmeti', 'Oto cam ve kilit'])->pluck('id');
            if ($services->isEmpty()) {
                $services->push(DB::table('booking_services')->insertGetId([
                    'name' => 'Oto cam ve kilit',
                    'vehicle_types' => json_encode(['otomobil', 'agir-vasita']),
                    'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]));
            }
            // Preserve IDs and car/truck offerings while removing motorcycle availability.
            DB::table('booking_services')->whereIn('id', $services)->update([
                'name' => 'Oto cam ve kilit',
                'vehicle_types' => json_encode(['otomobil', 'agir-vasita']),
                'updated_at' => now(),
            ]);
            DB::table('booking_offerings')->whereIn('booking_service_id', $services)
                ->where('vehicle_type', 'motosiklet')->delete();

            if (!DB::table('booking_services')->where('name', 'Arıza tespiti ve ekspertiz')->exists()) {
                DB::table('booking_services')->insert([
                    'name' => 'Arıza tespiti ve ekspertiz',
                    'vehicle_types' => json_encode(['otomobil', 'motosiklet', 'agir-vasita']),
                    'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Keep catalog records and selections made after this data update.
    }
};
