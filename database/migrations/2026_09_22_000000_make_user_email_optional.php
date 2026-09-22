<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        // Existing installations use MySQL/MariaDB. Keep the unique index:
        // multiple NULL emails are allowed, duplicate supplied emails are not.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE `users` MODIFY `email` VARCHAR(255) NULL');
            return;
        }

        // Fresh SQLite test databases already use the nullable base schema.
        if (DB::getDriverName() === 'sqlite') {
            foreach (DB::select('PRAGMA table_info(users)') as $column) {
                if ($column->name === 'email' && !$column->notnull) {
                    return;
                }
            }
        }

        throw new RuntimeException('Email nullability upgrade requires MySQL/MariaDB.');
    }

    public function down()
    {
        // Keep the nullable column on rollback: existing customers may have no
        // email, and the fresh-install schema also permits NULL. No data is lost.
    }
};
