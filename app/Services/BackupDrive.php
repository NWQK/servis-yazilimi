<?php
namespace App\Services;

use Symfony\Component\Process\Process;

class BackupDrive
{
    protected function command(array $arguments): string
    {
        $process = new Process(array_merge([config('backups.rclone'), '--config', config('backups.rclone_config'), '--retries', '2', '--contimeout', '30s', '--timeout', '5m'], $arguments));
        $process->setTimeout(3600);
        if ($process->run() !== 0) throw new BackupFailure('Google Drive işlemi tamamlanamadı. rclone bağlantısını, izinleri ve depolama alanını kontrol edin.');
        return $process->getOutput();
    }
    public function upload(string $local, string $remote): void
    {
        $this->command(['copyto', $local, $remote]);
        $info = json_decode($this->command(['lsjson', $remote, '--stat', '--hash']), true);
        $hash = $info['Hashes']['md5'] ?? $info['Hashes']['MD5'] ?? '';
        if (($info['Size'] ?? -1) !== filesize($local) || !hash_equals(md5_file($local), strtolower($hash))) throw new BackupFailure('Google Drive kopyasının boyut veya bütünlük doğrulaması başarısız. Önceki yedekler korundu.');
    }
    public function delete(string $remote): void
    {
        $this->command(['deletefile', $remote]);
    }
}
