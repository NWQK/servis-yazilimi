<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('service_photos',function(Blueprint $table){
            $table->id();
            $table->unsignedBigInteger('parent_id');
            $table->unsignedBigInteger('service_id');
            $table->string('filename',120)->unique();
            $table->unsignedInteger('bytes');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->timestamps();
            $table->index(['parent_id','service_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('service_photos'); }
};
