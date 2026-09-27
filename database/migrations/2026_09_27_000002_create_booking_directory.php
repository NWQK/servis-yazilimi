<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('booking_services')) {
            Schema::create('booking_services', function (Blueprint $t) {
                $t->id(); $t->string('name', 100); $t->json('vehicle_types'); $t->boolean('is_active')->default(true); $t->timestamps();
            });
            foreach (['Yağ değişimi', 'Fren sistemleri', 'Mekanik onarım', 'Genel bakım', 'Elektrik ve elektronik', 'Lastik ve jant'] as $name) {
                DB::table('booking_services')->insert(['name'=>$name, 'vehicle_types'=>json_encode(['otomobil','motosiklet','agir-vasita']), 'is_active'=>true, 'created_at'=>now(), 'updated_at'=>now()]);
            }
        }
        if (!Schema::hasColumn('appointment_profiles', 'directory_region')) Schema::table('appointment_profiles', function (Blueprint $t) { $t->string('directory_region', 16)->nullable()->index(); });
        if (!Schema::hasColumn('appointment_profiles', 'directory_visible')) Schema::table('appointment_profiles', function (Blueprint $t) { $t->boolean('directory_visible')->default(false); });
        if (!Schema::hasColumn('appointment_profiles', 'public_address')) Schema::table('appointment_profiles', function (Blueprint $t) { $t->string('public_address', 500)->nullable(); });
        if (!Schema::hasTable('booking_offerings')) Schema::create('booking_offerings', function (Blueprint $t) {
            $t->id(); $t->foreignId('appointment_profile_id')->constrained()->cascadeOnDelete();
            $t->foreignId('booking_service_id')->constrained()->cascadeOnDelete();
            $t->string('vehicle_type', 20);
            $t->unique(['appointment_profile_id','booking_service_id','vehicle_type'], 'booking_offering_unique');
            $t->index(['vehicle_type','booking_service_id'], 'booking_offering_filter');
        });
        foreach (['requested_vehicle'=>40, 'requested_service'=>100] as $column=>$length) {
            if (!Schema::hasColumn('appointments', $column)) Schema::table('appointments', fn (Blueprint $t) => $t->string($column, $length)->nullable());
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('booking_offerings'); Schema::dropIfExists('booking_services');
        Schema::table('appointment_profiles', fn (Blueprint $t) => $t->dropColumn(['directory_region','directory_visible','public_address']));
        Schema::table('appointments', fn (Blueprint $t) => $t->dropColumn(['requested_vehicle','requested_service']));
    }
};
