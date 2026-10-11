<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('business_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->index();
            $table->unsignedBigInteger('customer_id')->index();
            $table->string('identity_hash', 64);
            $table->string('token', 64)->unique();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->timestamp('rated_at')->nullable()->index();
            foreach (['sms','email'] as $channel) {
                $table->string($channel.'_status', 20)->default('pending');
                $table->string($channel.'_error', 80)->nullable();
                $table->timestamp($channel.'_sent_at')->nullable();
            }
            $table->string('provider_sid')->nullable();
            $table->timestamps();
            $table->unique(['owner_id','identity_hash']);
            $table->unique(['owner_id','customer_id']);
            $table->index(['owner_id','rating']);
        });
        Schema::table('appointment_sms_settings', function (Blueprint $table) {
            $table->boolean('review_sms_enabled')->default(true);
            $table->string('review_template',700)->default('{isletme} işletmesini değerlendirmek için: {link} {marka}');
        });
    }
    public function down(): void {
        Schema::table('appointment_sms_settings', fn (Blueprint $table) => $table->dropColumn(['review_sms_enabled','review_template']));
        Schema::dropIfExists('business_reviews');
    }
};
