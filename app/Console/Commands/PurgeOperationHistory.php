<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB,Schema};
class PurgeOperationHistory extends Command
{
    protected $signature='platform:purge-history';
    protected $description='Yalnızca eski işlem geçmişini ve teknik hata özetlerini küçük gruplarla temizler.';
    public function handle(): int
    {
        foreach(['invoice_changes'=>['created_at',now()->subYears(2)],'stock_movements'=>['created_at',now()->subYears(2)],'system_health_errors'=>['day',now()->subDays(30)->toDateString()]] as $table=>[$column,$cutoff]){
            if(!Schema::hasTable($table)) continue;
            $deadline=microtime(true)+30;
            while(microtime(true)<$deadline){
                $ids=DB::table($table)->where($column,'<',$cutoff)
                    ->when($table!=='system_health_errors'&&Schema::hasTable('business_recovery_locks'),fn($q)=>$q->whereNotIn('parent_id',DB::table('business_recovery_locks')->select('owner_id')))
                    ->orderBy('id')->limit(500)->pluck('id');
                if($ids->isEmpty()) break;
                DB::table($table)->whereIn('id',$ids)->delete();
            }
        }
        $this->info('Geçmiş temizliği tamamlandı. Asıl faturalar, ödemeler, giderler ve stok miktarları korunur.');
        return 0;
    }
}
