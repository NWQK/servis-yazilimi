<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
class PlatformHeartbeat extends Command
{
    protected $signature='platform:heartbeat';
    protected $description='Zamanlayıcının son çalışma zamanını tek kayıtta günceller.';
    public function handle(): int { \App\Services\SystemHealth::record('scheduler',true,'running');return 0; }
}
