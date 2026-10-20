<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
    public function up(): void {
        if(!Schema::hasTable('vehicle_history_controls'))Schema::create('vehicle_history_controls',function(Blueprint $t){
            $t->unsignedBigInteger('vehicle_id')->primary();$t->boolean('hidden')->default(false);
            $t->string('reason',500)->nullable();$t->unsignedBigInteger('actor_id')->nullable();
            $t->unsignedInteger('revision')->default(0);$t->timestamp('updated_at')->nullable();
        });
        // Platform records survive business deletion and tenant restore; no cascading FKs.
        if(!Schema::hasTable('public_service_history'))Schema::create('public_service_history',function(Blueprint $t){
            $t->unsignedBigInteger('service_id')->primary();$t->unsignedBigInteger('vehicle_id')->index();
            $t->unsignedBigInteger('parent_id')->index();$t->string('vin_hash',64);
            $t->string('business_name',255);$t->string('vehicle_brand',255)->nullable();$t->string('vehicle_model',255)->nullable();
            $t->string('plate_suffix',3)->nullable();$t->date('service_date')->nullable();
            $t->unsignedInteger('mileage_at_service')->nullable();$t->text('operations');$t->timestamp('captured_at');
            $t->index(['vin_hash','service_date','service_id'],'public_history_vin_date');
        });
        \App\Models\Vehicle::withTrashed()->orderBy('id')->chunkById(100,function($vehicles){
            foreach($vehicles as $vehicle) app(\App\Services\PublicVehicleHistory::class)->sync($vehicle);
        });
        $policy='<h2 id="public-service-history">Herkese açık servis geçmişi</h2><p>Geçerli şasi numarasına bağlı tamamlanmış servis özetleri otomatik olarak sorgulanabilir. İşletme adı, araç kaydındaki marka/model, plakanın yalnız son üç karakteri, servis tarihi, işlem türü ve servis kilometresi gösterilir. Müşteri iletişim bilgileri, tam plaka, fiyatlar, borçlar ve özel notlar paylaşılmaz. Yayımlanan özetler ayrı saklanır; işletmenin kayıt değiştirmesi veya silmesi geçmiş özeti kaldırmaz. Görünürlüğü yalnız platform süper yöneticisi gerekçeli olarak değiştirebilir. Kayıtlar hatalı veya eksik olabilir; resmî hasar/ekspertiz kaydı değildir.</p>';
        foreach(DB::table('pages')->where('slug','terms_conditions')->get() as $page){
            $content=$page->content??'';
            $content=str_contains($content,'id="public-service-history"')
                ? preg_replace('/<h2 id="public-service-history">.*?<\/h2>\s*<p>.*?<\/p>/s',$policy,$content)
                : $content.$policy;
            DB::table('pages')->where('id',$page->id)->update(['content'=>$content,'updated_at'=>now()]);
        }
    }
    public function down(): void {
        throw new RuntimeException('Yayımlanmış servis geçmişi ve yönetici kararları otomatik silinemez. Kontrollü geri dönüş gerekir.');
    }
};
