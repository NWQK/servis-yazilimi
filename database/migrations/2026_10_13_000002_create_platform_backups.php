<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('platform_backup_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('installation_id');
            $table->boolean('enabled')->default(false);
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_finished_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });
        Schema::create('platform_backups', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);
            $table->date('backup_date');
            $table->string('filename');
            $table->unsignedBigInteger('bytes');
            $table->string('sha256', 64);
            $table->boolean('local_available')->default(true);
            $table->string('drive_path', 500)->nullable();
            $table->timestamp('drive_verified_at')->nullable();
            $table->string('drive_error')->nullable();
            $table->timestamps();
            $table->unique(['kind', 'backup_date']);
        });
    }
    public function down()
    {
        Schema::dropIfExists('platform_backups');
        Schema::dropIfExists('platform_backup_settings');
    }
};
