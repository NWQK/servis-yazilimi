<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('business_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->foreignId('manager_user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('business_branches', function (Blueprint $table) {
            // The existing owner ID remains the tenant key everywhere else.
            $table->foreignId('owner_id')->primary()->constrained('users')->restrictOnDelete();
            $table->foreignId('group_id')->constrained('business_groups')->restrictOnDelete();
            $table->timestamps();
        });
    }
    public function down()
    {
        Schema::dropIfExists('business_branches');
        Schema::dropIfExists('business_groups');
    }
};
