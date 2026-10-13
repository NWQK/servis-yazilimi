<?php
namespace App\Services;

use Symfony\Component\Process\Process;

class BackupDatabase
{
    public function dump(string $target): void
    {
        $db = config('database.connections.'.config('database.default'));
        if (($db['driver'] ?? '') !== 'mysql') throw new BackupFailure('Otomatik yedekleme MySQL bağlantısı gerektirir.');
        $credentials = dirname($target).'/mysql.cnf';
        $escape = static fn ($s) => '"'.str_replace(["\\", '"', "\n", "\r"], ["\\\\", '\\"', '\\n', '\\r'], (string)$s).'"';
        $contents = "[client]\n";
        foreach (['user'=>$db['username'], 'password'=>$db['password'], 'host'=>$db['host'], 'port'=>$db['port']] as $key=>$value) $contents .= $key.'='.$escape($value)."\n";
        if (!empty($db['unix_socket'])) $contents .= 'socket='.$escape($db['unix_socket'])."\n";
        foreach (($db['options'] ?? []) as $key=>$value) {
            if ($key === \PDO::MYSQL_ATTR_SSL_CA) $contents .= 'ssl-ca='.$escape($value)."\n";
        }
        try {
            if (file_put_contents($credentials, $contents) === false) throw new BackupFailure('Geçici veritabanı ayarları yazılamadı.');
            chmod($credentials, 0600);
            $process = new Process([config('backups.mysqldump'), '--defaults-extra-file='.$credentials, '--single-transaction', '--quick', '--hex-blob', '--no-tablespaces', '--set-gtid-purged=OFF', '--result-file='.$target, '--databases', $db['database']]);
            $process->setTimeout(3600);
            $process->disableOutput();
            if ($process->run() !== 0 || !is_file($target) || filesize($target) === 0) throw new BackupFailure('MySQL yedeği alınamadı. Veritabanı yetkilerini ve mysqldump kurulumunu kontrol edin.');
        } finally {
            if (is_file($credentials)) unlink($credentials);
        }
    }
}
