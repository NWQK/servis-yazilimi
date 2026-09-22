<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\VehicleQrPool;
use Illuminate\Console\Command;

class ReplenishVehicleQrCodes extends Command
{
    protected $signature = 'vehicle-qr:replenish';
    protected $description = 'Her işletmenin boş QR stokunu 10 adede tamamlar';

    public function handle(VehicleQrPool $pool)
    {
        User::whereIn('type', ['owner', 'super admin'])->each(function ($owner) use ($pool) {
            $pool->replenish($owner->id);
        });
        $this->info('Boş QR stokları tamamlandı. Yeni kodları QR Etiketleri ekranından basabilirsiniz.');
        return self::SUCCESS;
    }
}
