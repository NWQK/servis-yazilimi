<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `vehicles` MODIFY `mileage` INT NULL DEFAULT NULL');
            return;
        }

        // Fresh SQLite tests use the nullable base schema.
        if (DB::getDriverName() === 'sqlite') {
            foreach (DB::select('PRAGMA table_info(vehicles)') as $column) {
                if ($column->name === 'mileage' && !$column->notnull) return;
            }
        }
        throw new RuntimeException('Mileage nullability upgrade requires MySQL/MariaDB.');
    }

    public function down()
    {
        // Retain NULL values on rollback rather than inventing an odometer reading.
    }
};
