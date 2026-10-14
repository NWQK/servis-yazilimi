<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
use App\Services\ChassisNumber;
return new class extends Migration {
    public function up(): void
    {
        // Vehicle ownership and the original vehicle schema remain unchanged.
        Schema::create('vehicle_history_index',function(Blueprint $t){
            $t->unsignedBigInteger('vehicle_id')->primary();$t->unsignedBigInteger('parent_id')->index();
            $t->string('vin_hash',64)->nullable();$t->boolean('is_public')->default(false);$t->timestamp('updated_at')->nullable();
            $t->index(['vin_hash','is_public']);$t->index(['parent_id','vin_hash']);
        });
        Schema::table('services',function(Blueprint $t){
            $t->unsignedInteger('mileage_at_service')->nullable();
            $t->index(['vehicle','parent_id','status','service_date'],'public_service_history_lookup');
        });
        $policy='<h2 id="public-service-history">Herkese açık servis geçmişi</h2><p>İşletmenin araç kaydında paylaşımı açması halinde tamamlanmış servislerin işletme adı, tarihi, işlem türü ve servis kilometresi şasi numarasıyla herkese açık sorgulanabilir. Müşteri iletişim bilgileri, fiyatlar, borçlar ve özel notlar bu sorguda paylaşılmaz. Paylaşım kapatılabilir; şasi değişikliğinde kapanır. İşletme girişleri hatalı/eksik olabilir; sonuçlar resmî hasar kaydı, ekspertiz raporu veya doğrulanmış eksiksiz araç geçmişi değildir.</p>';
        foreach(DB::table('pages')->where('slug','terms_conditions')->get() as $page){
            if (!str_contains($page->content ?? '', 'public-service-history')) DB::table('pages')->where('id',$page->id)->update(['content'=>$page->content.$policy,'updated_at'=>now()]);
        }
        // Existing records stay private. Do not invent historical mileage from today's vehicle mileage.
        DB::table('vehicles')->whereNull('deleted_at')->orderBy('id')->chunkById(500,function($vehicles){
            $rows=[];
            foreach($vehicles as $v){$vin=ChassisNumber::normalize($v->chassis_no);$rows[]=['vehicle_id'=>$v->id,'parent_id'=>$v->parent_id,
                'vin_hash'=>ChassisNumber::valid($vin) ? hash('sha256',$vin) : null,'is_public'=>false,'updated_at'=>now()];}
            if ($rows) DB::table('vehicle_history_index')->insert($rows);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('vehicle_history_index');
        Schema::table('services',function(Blueprint $t){$t->dropIndex('public_service_history_lookup');$t->dropColumn('mileage_at_service');});
    }
};
