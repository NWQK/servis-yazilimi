<?php
namespace App\Console\Commands;

use App\Services\PlatformBackups;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class RunPlatformBackup extends Command
{
    protected $signature = 'platform:backup {--requested : Sadece panelden bekleyen talebi çalıştır}';
    protected $description = 'Şifreli veritabanı ve yükleme yedeğini VDS ve Google Drive üzerinde saklar.';
    public function handle(PlatformBackups $backups): int
    {
        if (!Schema::hasTable('platform_backup_settings')) { $this->error('Önce migrate komutunu çalıştırın.'); return 1; }
        $setting = \App\Models\PlatformBackupSetting::central();
        if (!$setting->enabled || ($this->option('requested') && !$setting->requested_at)) return 0;
        if (!$backups->run((bool)$this->option('requested'))) {
            $this->error('Yedekleme tamamlanamadı veya başka bir yedekleme çalışıyor. Süper admin > Yedekler ekranını kontrol edin.');
            return 1;
        }
        $this->info('VDS ve Google Drive yedekleri doğrulandı.');
        return 0;
    }
}
