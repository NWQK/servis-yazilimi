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
        $count = ItemPurchase::where('created_at', '<', now()->subYears(2))->delete();
        $this->info($count.' eski satın alım geçmişi kaydı silindi.');
        return self::SUCCESS;
    }
}
