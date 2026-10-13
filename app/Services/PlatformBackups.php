<?php
namespace App\Services;

use App\Models\{PlatformBackup, PlatformBackupSetting};
use Illuminate\Support\Str;
use ZipArchive;

class PlatformBackups
{
    public function __construct(private BackupDatabase $database, private BackupDrive $drive) {}

    public function problems(): array
    {
        $problems = [];
        if (strlen(config('backups.password', '')) < 32) $problems[] = 'BACKUP_ARCHIVE_PASSWORD en az 32 karakter olmalıdır.';
        if (!class_exists(ZipArchive::class) || !ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256)) $problems[] = 'PHP ZIP / AES-256 desteği gereklidir.';
        if (!is_file(config('backups.mysqldump'))) $problems[] = 'mysqldump kurulumu veya dosya yolu eksik.';
        $driveConfig = @fopen(config('backups.rclone_config'), 'rb');
        if (!is_file(config('backups.rclone')) || !$driveConfig) $problems[] = 'Google Drive rclone bağlantısı yapılandırılmamış.';
        if ($driveConfig) fclose($driveConfig);
        if (!preg_match('/^[a-zA-Z0-9_-]+:[^\r\n]*$/', config('backups.remote', ''))) $problems[] = 'Google Drive hedefi geçersiz.';
        return $problems;
    }

    public function root(): string
    {
        $root = config('backups.path');
        if (!is_dir($root) && !mkdir($root, 0700, true) && !is_dir($root)) throw new BackupFailure('Özel yedek klasörü oluşturulamadı.');
        chmod($root, 0700);
        if (is_link($root)) throw new BackupFailure('Yedek klasörü sembolik bağlantı olamaz.');
        return $root;
    }

    public function path(PlatformBackup $backup): string
    {
        if (!preg_match('/^(daily|monthly)-\d{4}-\d{2}-\d{2}-[a-f0-9-]{36}\.zip$/D', $backup->filename)) throw new BackupFailure('Geçersiz yedek dosyası.');
        return $this->root().DIRECTORY_SEPARATOR.$backup->filename;
    }

    public function run(bool $requestedOnly = false): bool
    {
        try {
            $lock = @fopen($this->root().'/run.lock', 'c');
            if (!$lock) throw new BackupFailure('Özel yedek klasörüne yazılamıyor. Zamanlayıcı kullanıcısının dosya izinlerini kontrol edin.');
        } catch (\Throwable $e) {
            PlatformBackupSetting::central()->update(['last_finished_at'=>now(), 'last_error'=>'Özel yedek klasörüne yazılamıyor. Disk alanını ve zamanlayıcı kullanıcısının dosya izinlerini kontrol edin.']);
            return false;
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) { fclose($lock); return false; }
        $work = null;
        try {
            $setting = PlatformBackupSetting::central();
            if (!$setting->enabled || ($requestedOnly && !$setting->requested_at)) return false;
            // Clear only this request; a request arriving during the run remains pending.
            $setting->update(['requested_at'=>null, 'last_started_at'=>now(), 'last_error'=>null]);
            $problems = $this->problems();
            if ($problems) throw new BackupFailure(implode(' ', $problems));
            $date = now('Europe/Istanbul')->toDateString();
            $daily = PlatformBackup::where('kind', 'daily')->whereDate('backup_date', $date)->first();
            if (!$daily) {
                $work = $this->root().'/work-'.Str::uuid();
                mkdir($work, 0700);
                $this->database->dump($work.'/database.sql');
                $filename = 'daily-'.$date.'-'.Str::uuid().'.zip';
                $archive = $this->root().'/'.$filename;
                try {
                    $this->archive($work.'/database.sql', $archive, $date);
                    $daily = PlatformBackup::create(['kind'=>'daily', 'backup_date'=>$date, 'filename'=>$filename, 'bytes'=>filesize($archive), 'sha256'=>hash_file('sha256', $archive)]);
                } catch (\Throwable $e) { if (is_file($archive)) unlink($archive); throw $e; }
            }
            if (substr($date, -2) === '01' && !PlatformBackup::where('kind','monthly')->whereDate('backup_date',$date)->exists()) {
                $filename = 'monthly-'.$date.'-'.Str::uuid().'.zip';
                $target = $this->root().'/'.$filename;
                if (!copy($this->path($daily), $target)) throw new BackupFailure('Aylık yedek kopyalanamadı.');
                chmod($target, 0600);
                if (!hash_equals($daily->sha256, hash_file('sha256', $target))) { unlink($target); throw new BackupFailure('Aylık yedek doğrulanamadı.'); }
                try {
                    PlatformBackup::create(['kind'=>'monthly','backup_date'=>$date,'filename'=>$filename,'bytes'=>$daily->bytes,'sha256'=>$daily->sha256]);
                } catch (\Throwable $e) { unlink($target); throw $e; }
            }
            $failed = false;
            foreach (PlatformBackup::where('local_available',true)->whereNull('drive_verified_at')->orderByDesc('backup_date')->get() as $backup) {
                try {
                    $local = $this->path($backup);
                    if (!is_file($local) || !hash_equals($backup->sha256, hash_file('sha256',$local))) throw new BackupFailure('Yerel yedek bütünlüğü doğrulanamadı.');
                    $remote = $backup->drive_path ?: rtrim(config('backups.remote'),'/').'/'.$setting->installation_id.'/'.$backup->kind.'/'.$backup->filename;
                    $backup->update(['drive_path'=>$remote]);
                    $this->drive->upload($local, $remote);
                    $backup->update(['drive_verified_at'=>now(), 'drive_error'=>null]);
                } catch (\Throwable $e) {
                    $failed = true;
                    $backup->update(['drive_error'=>$e instanceof BackupFailure ? $e->getMessage() : 'Google Drive aktarımı veya bütünlük doğrulaması başarısız. Bağlantı ve izinleri kontrol edin.']);
                }
            }
            $this->prune();
            $setting->update(['last_finished_at'=>now(), 'last_error'=>$failed ? 'Bazı yedekler Drive’a aktarılamadı. Yerel kopyalar ve önceki Drive yedekleri korunuyor.' : null]);
            return !$failed;
        } catch (\Throwable $e) {
            PlatformBackupSetting::central()->update(['last_finished_at'=>now(), 'last_error'=>Str::limit($e instanceof BackupFailure ? $e->getMessage() : 'Yedekleme tamamlanamadı. Kurulum gereksinimlerini, disk alanını ve dosya/veritabanı izinlerini kontrol edin.', 240)]);
            return false;
        } finally {
            if ($work) {
                foreach (['database.sql', 'mysql.cnf'] as $file) if (is_file($work.'/'.$file)) unlink($work.'/'.$file);
                rmdir($work);
            }
            flock($lock, LOCK_UN); fclose($lock);
        }
    }

    public function archive(string $sql, string $target, string $date): void
    {
        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new BackupFailure('Yedek arşivi açılamadı.');
        $password = config('backups.password');
        $zip->setPassword($password);
        $hashes = [];
        $add = function ($file, $name) use ($zip, $password, &$hashes) {
            if (!$zip->addFile($file, $name) || !$zip->setEncryptionName($name, ZipArchive::EM_AES_256, $password)) throw new BackupFailure('Yedek arşivi şifrelenemedi.');
            $hashes[$name] = hash_file('sha256', $file);
        };
        try {
            $add($sql, 'database.sql');
            foreach (config('backups.uploads', []) as $index=>$folder) {
                if (!is_dir($folder)) continue;
                if (is_link($folder)) throw new BackupFailure('Yükleme klasörü sembolik bağlantı olamaz.');
                $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($folder, \FilesystemIterator::SKIP_DOTS));
                foreach ($iterator as $file) {
                    if ($file->isLink()) throw new BackupFailure('Yüklemelerde sembolik bağlantı bulundu.');
                    if ($file->isFile()) $add($file->getPathname(), 'uploads/'.$index.'/'.str_replace('\\','/',substr($file->getPathname(), strlen($folder)+1)));
                }
            }
            if (!$zip->addFromString('manifest.json', json_encode(['date'=>$date,'timezone'=>'Europe/Istanbul','files'=>$hashes], JSON_THROW_ON_ERROR)) || !$zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256, $password)) throw new BackupFailure('Yedek özeti şifrelenemedi.');
        } finally { if (!$zip->close()) throw new BackupFailure('Arşiv yazılamadı. Disk alanını kontrol edin.'); }
        chmod($target, 0600);
        $this->verifyArchive($target);
    }

    public function verifyArchive(string $target): void
    {
        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CHECKCONS) !== true) throw new BackupFailure('Arşiv açılamadı.');
        try {
            $zip->setPassword(config('backups.password'));
            $manifest = json_decode($zip->getFromName('manifest.json') ?: '', true, 512, JSON_THROW_ON_ERROR);
            if (empty($manifest['files']['database.sql'])) throw new BackupFailure('Veritabanı yedeği eksik.');
            foreach ($manifest['files'] as $name=>$expected) {
                $stream = $zip->getStream($name);
                if (!$stream) throw new BackupFailure('Şifreli yedek okunamadı.');
                $context = hash_init('sha256'); hash_update_stream($context, $stream); fclose($stream);
                if (!hash_equals($expected, hash_final($context))) throw new BackupFailure('Arşiv bütünlüğü doğrulanamadı.');
            }
        } finally { $zip->close(); }
    }

    public function prune(): void
    {
        // Each destination rotates independently, and only after verified replacements exist.
        foreach (['daily'=>3, 'monthly'=>1] as $kind=>$keep) {
            $local = PlatformBackup::where('kind',$kind)->where('local_available',true)->orderByDesc('backup_date')->get();
            foreach ($local->take($keep) as $replacement) {
                $file = $this->path($replacement);
                if (!is_file($file) || !hash_equals($replacement->sha256, hash_file('sha256', $file))) throw new BackupFailure('Yeni yerel kopyalar doğrulanamadı; eski yedekler korundu.');
            }
            foreach ($local->slice($keep) as $backup) {
                $file = $this->path($backup);
                if (is_file($file) && !unlink($file)) throw new BackupFailure('Eski yerel yedek silinemedi.');
                $backup->update(['local_available'=>false]);
            }
            $remote = PlatformBackup::where('kind',$kind)->whereNotNull('drive_verified_at')->orderByDesc('backup_date')->get();
            foreach ($remote->slice($keep) as $backup) {
                try {
                    $this->drive->delete($backup->drive_path);
                    $backup->update(['drive_verified_at'=>null, 'drive_path'=>null, 'drive_error'=>null]);
                } catch (\Throwable $e) { throw new BackupFailure('Eski Drive yedeği silinemedi. Mevcut kopyalar korundu.'); }
            }
        }
        PlatformBackup::where('local_available',false)->whereNull('drive_path')->where('created_at','<',now()->subDays(90))->delete();
    }
}
