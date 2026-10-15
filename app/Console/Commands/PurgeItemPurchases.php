<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\ItemPurchase;
class PurgeItemPurchases extends Command
{
    protected $signature = 'inventory:purge-purchases';
    protected $description = 'İki yıllık saklama süresi dolan satın alım geçmişini siler; finans ve stok hareketlerini korur';
    public function handle(): int
    {
        $count = ItemPurchase::when(\Illuminate\Support\Facades\Schema::hasTable('business_recovery_locks'),fn($q)=>$q->whereNotIn('parent_id',\Illuminate\Support\Facades\DB::table('business_recovery_locks')->select('owner_id')))->where('created_at', '<', now()->subYears(2))->delete();
        $this->info($count.' eski satın alım geçmişi kaydı silindi.');
        return self::SUCCESS;
    }
}
