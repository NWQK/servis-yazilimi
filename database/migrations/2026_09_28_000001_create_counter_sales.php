<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('counter_sales')) Schema::create('counter_sales', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('parent_id'); $t->unsignedBigInteger('created_by');
            $t->uuid('request_key'); $t->uuid('inventory_key'); $t->unsignedBigInteger('item_id');
            $t->string('item_title'); $t->unsignedInteger('quantity'); $t->decimal('unit_price', 12, 2);
            $t->decimal('amount', 18, 2); $t->date('sale_date'); $t->unsignedBigInteger('stock_movement_id')->unique(); $t->timestamps();
            $t->unique(['parent_id','request_key']); $t->index(['parent_id','sale_date']);
        });
        foreach (['Kaporta ve boya','Diğer hizmetler'] as $name) {
            if (!DB::table('booking_services')->where('name',$name)->exists()) DB::table('booking_services')->insert([
                'name'=>$name, 'vehicle_types'=>json_encode(['otomobil','motosiklet','agir-vasita']), 'is_active'=>true, 'created_at'=>now(), 'updated_at'=>now(),
            ]);
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('counter_sales');
        // Preserve catalog choices and business offerings when rolling back sales.
    }
};
