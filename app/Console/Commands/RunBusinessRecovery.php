<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Services\Recovery\BusinessRecoveryService;
class RunBusinessRecovery extends Command
{
    protected $signature='platform:recover';
    protected $description='Süper admin işletme kurtarma ön izlemelerini ve onaylı işlemleri çalıştırır.';
    public function handle(BusinessRecoveryService $recovery): int{$recovery->work();return self::SUCCESS;}
}
