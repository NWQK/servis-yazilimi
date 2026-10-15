<?php
namespace App\Services\Recovery;

/** Coordinate HTTP writers without changing their existing database transaction boundaries. */
class RecoveryWriteLock
{
    public function shared(int $owner,callable $work){return $this->run($owner,LOCK_SH,$work);}
    public function exclusive(int $owner,callable $work){return $this->run($owner,LOCK_EX,$work);}
    private function run(int $owner,int $mode,callable $work)
    {
        if($owner<1)throw new RecoveryFailure('İşletme kilit kimliği geçersiz.');
        $root=config('recovery.write_lock_path',storage_path('framework/cache/recovery-locks'));
        if(is_link($root))throw new RecoveryFailure('İşletme kilit dizini geçersiz.');
        if(!is_dir($root)&&!mkdir($root,0700,true)&&!is_dir($root))throw new RecoveryFailure('İşletme yazma kilidi oluşturulamadı.');
        chmod($root,0700);$path=$root.'/owner-'.$owner.'.lock';
        if(is_link($path))throw new RecoveryFailure('İşletme kilit dosyası geçersiz.');
        $handle=fopen($path,'c');if(!$handle)throw new RecoveryFailure('İşletme yazma kilidi açılamadı.');
        chmod($path,0600);
        try{if(!flock($handle,$mode))throw new RecoveryFailure('İşletme yazma kilidi alınamadı.');return $work();}
        finally{flock($handle,LOCK_UN);fclose($handle);}
    }
}
