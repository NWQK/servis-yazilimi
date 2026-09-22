<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('vehicle_qr_codes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id');
            $table->string('token', 64)->unique();
            // A deleted vehicle must never make its old printed label reusable.
            $table->foreignId('vehicle_id')->nullable()->unique()->constrained('vehicles')->nullOnDelete();
            $table->timestamp('printed_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();
            $table->index(['parent_id', 'assigned_at']);
        });

        DB::table('users')->whereIn('type', ['owner', 'super admin'])->orderBy('id')->each(function ($owner) {
            for ($i = 0; $i < 10; $i++) {
                DB::table('vehicle_qr_codes')->insert([
                    'parent_id' => $owner->id, 'token' => bin2hex(random_bytes(32)),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }

    public function down()
    {
        Schema::dropIfExists('vehicle_qr_codes');
    }
};
