<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MobilePushWork extends Command
{
    protected $signature='mobile:push-work';
    protected $description='Yetkili cihazların bekleyen randevu bildirimlerini gönderir.';
    public function handle(): int {
        if (!config('mobile_push.enabled')) { $this->info('Cihaz bildirimi gönderimi kapalı.'); return self::SUCCESS; }
        foreach(DB::table('mobile_push_outbox')->where('status','pending')->orderBy('id')->limit(100)->pluck('id') as $id) {
            app(\App\Services\MobilePushDelivery::class)->deliver($id);
        }
        return self::SUCCESS;
    }
}
