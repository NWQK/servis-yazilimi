<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::create('vehicle_catalog_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->index();
            $table->string('category', 30);
            $table->boolean('active')->default(true);
            $table->json('entries');
            $table->timestamps();
            $table->index(['parent_id','category','active']);
        });
    }
    public function down() { Schema::dropIfExists('vehicle_catalog_imports'); }
};
