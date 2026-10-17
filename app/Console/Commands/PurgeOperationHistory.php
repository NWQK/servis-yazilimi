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
        foreach(['invoice_changes'=>['created_at',now()->subYears(2)],'stock_movements'=>['created_at',now()->subYears(2)],'branch_activity_logs'=>['created_at',now()->subDays(180)],'system_health_errors'=>['day',now()->subDays(30)->toDateString()]] as $table=>[$column,$cutoff]){
            if(!Schema::hasTable($table)) continue;
            $deadline=microtime(true)+30;
            while(microtime(true)<$deadline){
                $ids=DB::table($table)->where($column,'<',$cutoff)
                    ->when($table==='stock_movements' && Schema::hasTable('inventory_checkpoints'),fn($q)=>$q->where(fn($q)=>$q
                        ->whereExists(fn($b)=>$b->selectRaw('1')->from('inventory_checkpoints')->whereColumn('inventory_checkpoints.parent_id','stock_movements.parent_id')->whereColumn('inventory_checkpoints.inventory_key','stock_movements.inventory_key')->whereColumn('inventory_checkpoints.movement_id','>=','stock_movements.id'))
                        ->orWhereNotExists(fn($b)=>$b->selectRaw('1')->from('items')->whereColumn('items.parent_id','stock_movements.parent_id')->whereColumn('items.inventory_key','stock_movements.inventory_key'))))
                    ->when(in_array($table,['invoice_changes','stock_movements'],true)&&Schema::hasTable('business_recovery_locks'),fn($q)=>$q->whereNotIn('parent_id',DB::table('business_recovery_locks')->select('owner_id')))
                    ->orderBy('id')->limit(500)->pluck('id');
                if($ids->isEmpty()) break;
                DB::table($table)->whereIn('id',$ids)->delete();
            }
        }
        $this->info('Geçmiş temizliği tamamlandı. Asıl faturalar, ödemeler, giderler ve stok miktarları korunur.');
        return 0;
    }
}
