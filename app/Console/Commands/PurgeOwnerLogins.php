<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
class PurgeOwnerLogins extends Command
{
    protected $signature='security:purge-owner-logins';
    protected $description='Yedi günlük saklama süresi dolan işletme giriş kayıtlarını siler';
    public function handle(): int
    {
        $count=app(\App\Services\OwnerLoginSecurity::class)->purge();
        $this->info($count.' eski giriş kaydı silindi.');
        return self::SUCCESS;
    }
}
