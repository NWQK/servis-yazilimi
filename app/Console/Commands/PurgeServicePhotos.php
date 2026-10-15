<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Services\PhotoRetention;
class PurgeServicePhotos extends Command
{
    protected $signature='photos:purge';
    protected $description='Süresi dolan araç kabul fotoğraflarını ve eski sahipsiz dosyaları temizler.';
    public function handle(PhotoRetention $retention): int {
        $lock=null;
        try {
            $lock=@fopen(app(\App\Services\PlatformBackups::class)->root().'/run.lock','c');
            if(!$lock)throw new \RuntimeException('Fotoğraf temizlik kilidi açılamadı.');
            if(!flock($lock,LOCK_EX|LOCK_NB)){$this->info('Yedekleme veya kurtarma sürüyor; fotoğraf temizliği sonraki çalışmaya bırakıldı.');return self::SUCCESS;}
            $stats=$retention->purge();
            \DB::table('settings')->updateOrInsert(['parent_id'=>0,'type'=>'platform','name'=>'photo_cleanup_error'],['value'=>'']);
            $this->info($stats['photos'].' fotoğraf, '.$stats['orphans'].' sahipsiz dosya temizlendi; '.$stats['bytes'].' bayt boşaltıldı.');
            return self::SUCCESS;
        } catch(\Throwable $e){
            \DB::table('settings')->updateOrInsert(['parent_id'=>0,'type'=>'platform','name'=>'photo_cleanup_error'],['value'=>'Fotoğraf temizliği tamamlanamadı. Sunucu depolama izinlerini ve zamanlayıcı kayıtlarını kontrol edin.']);
            \Log::error('Fotoğraf temizliği başarısız.',['exception'=>get_class($e)]);
            $this->error('Fotoğraf temizliği tamamlanamadı.');
            return self::FAILURE;
        } finally {if(is_resource($lock)){flock($lock,LOCK_UN);fclose($lock);}}
    }
}
