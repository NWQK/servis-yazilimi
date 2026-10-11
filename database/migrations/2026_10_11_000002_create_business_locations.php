<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void { Schema::create('business_locations',function(Blueprint $t){
  $t->id();$t->unsignedBigInteger('owner_id')->unique();$t->string('google_maps_url',2048);
  $t->decimal('latitude',10,7);$t->decimal('longitude',10,7);$t->timestamps();
 }); }
 public function down(): void { Schema::dropIfExists('business_locations'); }
};
