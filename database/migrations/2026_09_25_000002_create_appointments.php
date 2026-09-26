<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (!Schema::hasTable('appointment_profiles')) {
        Schema::create('appointment_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('display_name', 150);
            $table->boolean('is_active')->default(false);
            $table->json('weekly_hours');
            $table->timestamps();
        });
        }
        if (!Schema::hasTable('appointments')) {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_profile_id')->constrained()->cascadeOnDelete();
            $table->string('public_token', 64)->unique();
            $table->uuid('request_key');
            $table->string('customer_name', 150);
            $table->string('phone', 20);
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('license_plate', 20)->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->dateTime('occupied_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['appointment_profile_id', 'request_key'], 'appointment_request_unique');
            $table->unique(['appointment_profile_id', 'occupied_at'], 'appointment_slot_unique');
            $table->index(['appointment_profile_id', 'status', 'starts_at'], 'appointment_list_index');
        });
        }

        // MySQL DDL is not transactional. A failed run can leave a table behind
        // before Laravel finishes adding its indexes or foreign keys.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $this->completeMysqlConstraints();
        }
    }

    private function completeMysqlConstraints(): void
    {
        $connection = Schema::getConnection();
        $definitions = [
            'appointment_profiles' => [
                'appointment_profiles_owner_id_unique' => [['owner_id'], true],
                'appointment_profiles_public_id_unique' => [['public_id'], true],
            ],
            'appointments' => [
                'appointments_public_token_unique' => [['public_token'], true],
                'appointment_request_unique' => [['appointment_profile_id', 'request_key'], true],
                'appointment_slot_unique' => [['appointment_profile_id', 'occupied_at'], true],
                'appointment_list_index' => [['appointment_profile_id', 'status', 'starts_at'], false],
            ],
        ];
        foreach ($definitions as $tableName => $indexes) {
            $existing = collect($connection->select('SHOW INDEX FROM `'.$connection->getTablePrefix().$tableName.'`'))->pluck('Key_name')->all();
            foreach ($indexes as $name => [$columns, $unique]) {
                if (!in_array($name, $existing, true)) {
                    Schema::table($tableName, function (Blueprint $table) use ($name, $columns, $unique) {
                        if ($unique) $table->unique($columns, $name);
                        else $table->index($columns, $name);
                    });
                }
            }
        }
        foreach (['appointment_profiles' => ['owner_id', 'users'], 'appointments' => ['appointment_profile_id', 'appointment_profiles']] as $tableName => [$column, $parent]) {
            $exists = $connection->selectOne('SELECT COUNT(*) AS total FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME = ?', [
                $connection->getDatabaseName(), $connection->getTablePrefix().$tableName, $column, $connection->getTablePrefix().$parent,
            ]);
            if (!$exists->total) {
                Schema::table($tableName, fn (Blueprint $table) => $table->foreign($column)->references('id')->on($parent)->cascadeOnDelete());
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('appointment_profiles');
    }
};
