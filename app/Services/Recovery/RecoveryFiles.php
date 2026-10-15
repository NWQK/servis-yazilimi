<?php
namespace App\Services\Recovery;
use App\Models\BusinessRecovery;
use App\Services\{PlatformBackups,BackupDrive};
use Illuminate\Support\Facades\{DB,Schema};
use ZipArchive;

class RecoveryFiles
{
    /** Only remove regular files inside this job's managed directory; never follow links. */
    public function clean(BusinessRecovery $job,bool $keepCheckpoint): void
    {
        $dir=$this->directory($job);
        foreach(new \DirectoryIterator($dir) as $file){
            if($file->isDot())continue;
            if($file->isLink()||!$file->isFile())throw new RecoveryFailure('Kurtarma temizliğinde beklenmeyen dosya yolu bulundu.');
            if($keepCheckpoint&&in_array($file->getFilename(),['checkpoint.zip','checkpoint-sha256'],true))continue;
            if(!unlink($file->getPathname()))throw new RecoveryFailure('Geçici kurtarma dosyası temizlenemedi.');
        }
        if(!$keepCheckpoint&&!rmdir($dir))throw new RecoveryFailure('Geçici kurtarma dizini temizlenemedi.');
    }
    public function directory(BusinessRecovery $job): string
    {
        if(!preg_match('/\A[a-f0-9-]{36}\z/',$job->id))throw new RecoveryFailure('Kurtarma kimliği geçersiz.');
        $root=app(PlatformBackups::class)->root().'/recoveries';
        foreach([$root,$root.'/'.$job->id] as $dir){
            if(is_link($dir))throw new RecoveryFailure('Kurtarma dizini sembolik bağlantı olamaz.');
            if(!is_dir($dir)&&!mkdir($dir,0700))throw new RecoveryFailure('Kurtarma alanı oluşturulamadı.');
            chmod($dir,0700);
        }
        return $root.'/'.$job->id;
    }
    public function source(BusinessRecovery $job): string
    {
        if($job->source_recovery_id){
            $previous=$job->sourceRecovery;
            if(!$previous||(int)$previous->owner_id!==(int)$job->owner_id||!$previous->checkpoint_hash
                ||!$previous->checkpoint_expires_at||($previous->checkpoint_expires_at->isPast()&&$previous->status!=='needs_attention'))
                throw new RecoveryFailure('İşlem öncesi kurtarma kopyası kullanılamıyor veya süresi doldu.');
            $original=$this->directory($previous).'/checkpoint.zip';
            if(!is_file($original)||!hash_equals($previous->checkpoint_hash,hash_file('sha256',$original)))throw new RecoveryFailure('İşlem öncesi kopya doğrulanamadı.');
            $this->verifyCheckpoint($original,$job->owner_id);
            $target=$this->directory($job).'/source.zip';
            if(!copy($original,$target))throw new RecoveryFailure('Kurtarma kopyası çalışma alanına alınamadı.');
            chmod($target,0600);return $target;
        }
        $backup=$job->backup;
        if(!$backup)throw new RecoveryFailure('Seçilen yedek kaydı bulunamadı.');
        $dir=$this->directory($job);$path=$dir.'/source.zip';
        if(is_file($path)&&hash_equals($backup->sha256,hash_file('sha256',$path))){app(PlatformBackups::class)->verifyArchive($path);return $path;}
        $local=app(PlatformBackups::class)->path($backup);
        if($backup->local_available&&is_file($local)){
            if(!copy($local,$path))throw new RecoveryFailure('Yedek çalışma alanına kopyalanamadı.');
        } elseif($backup->drive_path&&$backup->drive_verified_at){
            app(BackupDrive::class)->download($backup->drive_path,$path);
        } else throw new RecoveryFailure('Yedeğin VDS veya Drive kopyası bulunmuyor.');
        chmod($path,0600);
        if(!hash_equals($backup->sha256,hash_file('sha256',$path)))throw new RecoveryFailure('Yedek dosyasının bütünlüğü doğrulanamadı.');
        app(PlatformBackups::class)->verifyArchive($path);
        return $path;
    }
    public function zip(string $path): ZipArchive
    {
        $zip=new ZipArchive();
        if($zip->open($path,ZipArchive::CHECKCONS)!==true)throw new RecoveryFailure('Kurtarma arşivi açılamadı.');
        $zip->setPassword(config('backups.password'));return $zip;
    }
    public function extractSql(string $archive,string $target): void
    {
        $zip=$this->zip($archive);
        try {
            $stat=$zip->statName('database.sql');if(!$stat)throw new RecoveryFailure('Yedekte SQL dosyası eksik.');
            $free=disk_free_space(dirname($target));
            if($free!==false&&$free<max(104857600,(int)$stat['size']*4))throw new RecoveryFailure('Kurtarma hazırlığı için yeterli disk alanı yok.');
            $input=$zip->getStream('database.sql');$output=fopen($target,'wb');
            if(!$input||!$output)throw new RecoveryFailure('SQL yedeği özel çalışma alanına açılamadı.');
            try {if(stream_copy_to_stream($input,$output)!==(int)$stat['size'])throw new RecoveryFailure('SQL dosyası eksik okundu.');}
            finally {fclose($input);fclose($output);}
            chmod($target,0600);
        }finally{$zip->close();}
    }
    public function references(RecoveryStore $store): array
    {
        $files=[];
        foreach(['users'=>['profile'=>'profile'],'employees'=>['document'=>'document'],'vehicles'=>['document'=>'document'],
            'invoice_payments'=>['receipt'=>'receipt']] as $table=>$fields){
            foreach($store->rows($table) as $row)foreach($fields as $field=>$dir){
                if($table==='users'&&($row['type']??'')==='owner')continue;
                $value=$row[$field]??null;if(!$value)continue;
                $names=json_decode($value,true);
                if(!is_array($names))$names=[$value];
                foreach($names as $name)if(is_string($name))$this->reference($files,$dir,$name);
            }
        }
        foreach($store->rows('settings') as $row)if(in_array($row['name'],['company_logo','company_light_logo','company_favicon','invoice_logo'],true))
            $this->reference($files,'logo',$row['value']??'');
        foreach($store->rows('service_photos') as $row){
            if(!preg_match('/\A'.(int)$row['parent_id'].'_'.(int)$row['service_id'].'_[a-f0-9-]{36}\.jpg\z/',$row['filename']??''))
                throw new RecoveryFailure('Kabul fotoğrafının işletme veya servis bilgisi geçersiz.');
            $this->reference($files,'photo',$row['filename']);
        }
        return array_keys($files);
    }
    private function reference(array &$files,string $directory,string $name): void
    {
        if($name===''||in_array($name,['avatar.png','logo.png','light_logo.png','favicon.png','invoice_logo.png'],true))return;
        if(!preg_match('/\A[\pL\pN._ -]+\z/u',$name)||str_contains($name,'..'))throw new RecoveryFailure('Yedekte desteklenmeyen dosya yolu var.');
        if(!in_array(strtolower(pathinfo($name,PATHINFO_EXTENSION)),['png','jpg','jpeg','webp','gif','avif','svg','pdf','doc','docx','xls','xlsx','ppt','pptx','txt','csv','zip'],true))
            throw new RecoveryFailure('Çalıştırılabilir veya desteklenmeyen dosya kurtarılamaz.');
        $files[$directory.'/'.$name]=true;
    }
    public function destination(int $root,string $relative): string
    {
        $roots=config('backups.uploads');
        $pattern=$root===2?'/\Aphoto\/[0-9]+_[0-9]+_[a-f0-9-]{36}\.jpg\z/':'/\A(?:upload\/)?(?:profile|logo|document|receipt)\/[\pL\pN._ -]+\z/u';
        if(!isset($roots[$root])||!preg_match($pattern,$relative)||str_contains($relative,'..'))
            throw new RecoveryFailure('Kurtarma hedef dosyası geçersiz.');
        $base=rtrim($roots[$root],'/\\');
        if(is_link($base))throw new RecoveryFailure('Yükleme kökü sembolik bağlantı olamaz.');
        if(!is_dir($base)&&!mkdir($base,0750,true))throw new RecoveryFailure('Yükleme kökü oluşturulamadı.');
        $parts=explode('/',$relative);$path=$base;
        foreach($parts as $i=>$part){
            $path.='/'.$part;if(is_link($path))throw new RecoveryFailure('Dosya yolunda sembolik bağlantı var.');
            if($i<count($parts)-1&&!is_dir($path)&&!mkdir($path,0750))throw new RecoveryFailure('Dosya dizini oluşturulamadı.');
        }
        return $path;
    }
    public function plan(RecoveryStore $source,string $archive,int $owner,string $mode): array
    {
        $zip=$this->zip($archive);$plan=[];$targets=[];
        try {
            foreach($this->references($source) as $relative){
                $found=false;
                foreach($this->rootsFor($relative) as $index=>$path){
                    $entry='uploads/'.$index.'/'.$path;
                    $exists=(bool)$zip->statName($entry);$found=$found||$exists;
                    if($exists||$mode==='rollback')$targets[$entry]=['root'=>$index,'path'=>$path,'exists'=>$exists];
                }
                if(!$found&&$mode!=='rollback')throw new RecoveryFailure('Yedekte ilişkili dosya eksik: '.$relative);
            }
            if($mode==='rollback'){
                $manifest=json_decode($zip->getFromName('manifest.json')?:'',true,512,JSON_THROW_ON_ERROR);
                // Include touched files no longer referenced by the pre-recovery database, including absence.
                foreach($manifest['file_states']??[] as $state){
                    if(!is_int($state['root']??null)||!is_string($state['path']??null)||!is_bool($state['exists']??null))
                        throw new RecoveryFailure('İşlem öncesi dosya durumu geçersiz.');
                    $this->destination($state['root'],$state['path']);
                    $targets['uploads/'.$state['root'].'/'.$state['path']]=$state;
                }
            }
            ksort($targets);
            foreach($targets as $entry=>$state){
                $index=$state['root'];$path=$state['path'];$dest=$this->destination($index,$path);
                $relative=preg_replace('#^upload/#','',$path);$before=is_file($dest)?hash_file('sha256',$dest):null;
                if(!$state['exists']){
                    if($before===null)continue;
                    if($this->shared($relative,$owner))throw new RecoveryFailure('Geri alma dosyası başka işletme tarafından kullanılıyor: '.$relative);
                    $plan[]=['root'=>$index,'path'=>$path,'remove'=>true,'expected_hash'=>$before];continue;
                }
                $stream=$zip->getStream($entry);if(!$stream)throw new RecoveryFailure('Dosya yedekten okunamıyor.');
                $ctx=hash_init('sha256');hash_update_stream($ctx,$stream);fclose($stream);$hash=hash_final($ctx);
                if($before!==null&&hash_equals($hash,$before))continue;
                if(($before!==null&&$mode==='missing')||$this->shared($relative,$owner))throw new RecoveryFailure('Dosya çakışması: '.$relative.'. Mevcut dosya korunuyor.');
                $plan[]=['root'=>$index,'path'=>$path,'entry'=>$entry,'hash'=>$hash,'expected_hash'=>$before];
            }
        }finally{$zip->close();}
        return $plan;
    }
    private function shared(string $relative,int $owner): bool
    {
        $name=basename($relative);
        foreach(['users'=>['profile'],'employees'=>['document'],'vehicles'=>['document'],'invoice_payments'=>['receipt'],'settings'=>['value'],'service_photos'=>['filename']] as $table=>$fields){
            foreach($fields as $field)if(Schema::hasColumn($table,$field)&&DB::table($table)->where('parent_id','!=',$owner)->where($field,'like','%'.addcslashes($name,'\\%_').'%')->exists())return true;
        }
        return false;
    }
    public function checkpoint(BusinessRecovery $job,RecoveryStore $current,array $filePlan=[]): string
    {
        $dir=$this->directory($job);$target=$dir.'/checkpoint.zip';$zip=new ZipArchive();
        if($zip->open($target,ZipArchive::CREATE|ZipArchive::EXCL)!==true)throw new RecoveryFailure('İşlem öncesi kurtarma kopyası oluşturulamadı.');
        $zip->setPassword(config('backups.password'));$hashes=[];$states=[];
        $add=function($path,$name)use($zip,&$hashes){
            if(isset($hashes[$name]))return;
            if(!$zip->addFile($path,$name)||!$zip->setEncryptionName($name,ZipArchive::EM_AES_256,config('backups.password')))throw new RecoveryFailure('Kurtarma kopyası şifrelenemedi.');
            $hashes[$name]=hash_file('sha256',$path);
        };
        try {
            $add($current->path,'tenant.sqlite');
            foreach($this->references($current) as $relative)foreach($this->rootsFor($relative) as $index=>$path){
                $file=$this->destination($index,$path);if(is_file($file))$add($file,'uploads/'.$index.'/'.$path);
            }
            foreach($filePlan as $planned){
                $file=$this->destination($planned['root'],$planned['path']);$exists=is_file($file);
                $states[]=['root'=>$planned['root'],'path'=>$planned['path'],'exists'=>$exists];
                if($exists)$add($file,'uploads/'.$planned['root'].'/'.$planned['path']);
            }
            if(!$zip->addFromString('manifest.json',json_encode(['owner_id'=>$job->owner_id,'files'=>$hashes,'file_states'=>$states],JSON_THROW_ON_ERROR))
                ||!$zip->setEncryptionName('manifest.json',ZipArchive::EM_AES_256,config('backups.password')))
                throw new RecoveryFailure('Kurtarma kopyasının dosya özeti şifrelenemedi.');
        }finally{if(!$zip->close())throw new RecoveryFailure('İşlem öncesi kopya yazılamadı.');}
        chmod($target,0600);$this->verifyCheckpoint($target,$job->owner_id);
        return hash_file('sha256',$target);
    }
    public function verifyCheckpoint(string $path,int $owner): void
    {
        $zip=$this->zip($path);
        try {
            $m=json_decode($zip->getFromName('manifest.json')?:'',true,512,JSON_THROW_ON_ERROR);
            if((int)($m['owner_id']??0)!==$owner||empty($m['files']['tenant.sqlite']))throw new RecoveryFailure('Kurtarma kopyasının işletmesi uyuşmuyor.');
            foreach($m['files'] as $name=>$hash){$s=$zip->getStream($name);if(!$s)throw new RecoveryFailure('Kurtarma kopyası eksik.');
                $ctx=hash_init('sha256');hash_update_stream($ctx,$s);fclose($s);if(!hash_equals($hash,hash_final($ctx)))throw new RecoveryFailure('Kurtarma kopyasının bütünlüğü doğrulanamadı.');}
        }finally{$zip->close();}
    }
    private function rootsFor(string $relative): array
    {
        return str_starts_with($relative,'photo/')?[2=>$relative]:[0=>$relative,1=>'upload/'.$relative];
    }
    public function apply(BusinessRecovery $job,string $archive,array $plan): void
    {
        $dir=$this->directory($job);$journal=[];$zip=$this->zip($archive);
        try {
            foreach($plan as $i=>$file){
                $dest=$this->destination($file['root'],$file['path']);$before=$dir.'/before-'.$i;
                $existed=is_file($dest);
                $currentHash=$existed?hash_file('sha256',$dest):null;
                if(array_key_exists('expected_hash',$file)&&$file['expected_hash']!==$currentHash)
                    throw new RecoveryFailure('Dosya ön izlemeden sonra değişti. Yeni ön izleme hazırlayın.');
                if($existed&&!copy($dest,$before))throw new RecoveryFailure('Dosyanın geri alma kopyası alınamadı.');
                if($existed)chmod($before,0600);
                $journal[]=$file+['existed'=>$existed,'before'=>'before-'.$i,'before_hash'=>$existed?hash_file('sha256',$before):null];
                if(file_put_contents($dir.'/files-journal.tmp',json_encode($journal,JSON_THROW_ON_ERROR),LOCK_EX)===false)throw new RecoveryFailure('Dosya işlem günlüğü yazılamadı.');
                chmod($dir.'/files-journal.tmp',0600);
                if(!rename($dir.'/files-journal.tmp',$dir.'/files-journal.json'))throw new RecoveryFailure('Dosya işlem günlüğü yazılamadı.');
                if(!empty($file['remove'])){if(is_file($dest)&&!unlink($dest))throw new RecoveryFailure('Dosya önceki durumuna döndürülemedi.');continue;}
                $input=$zip->getStream($file['entry']);$temp=$dest.'.recovery-'.$job->id;
                if(!$input)throw new RecoveryFailure('Kurtarma dosyası arşivden açılamadı.');
                if(is_link($temp)){fclose($input);throw new RecoveryFailure('Geçici kurtarma dosyası geçersiz.');}
                $out=fopen($temp,'xb');if(!$out){fclose($input);throw new RecoveryFailure('Dosya kurtarma hedefine yazılamadı.');}
                try {stream_copy_to_stream($input,$out);}finally{fclose($input);fclose($out);}
                chmod($temp,0640);
                if(!hash_equals($file['hash'],hash_file('sha256',$temp))||!rename($temp,$dest))throw new RecoveryFailure('Kurtarılan dosya doğrulanamadı.');
            }
        }finally{$zip->close();}
    }
    public function undoFiles(BusinessRecovery $job): void
    {
        $dir=$this->directory($job);$path=$dir.'/files-journal.json';if(!is_file($path))return;
        $rows=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        foreach(array_reverse($rows) as $file){
            $dest=$this->destination($file['root'],$file['path']);
            $temp=$dest.'.recovery-'.$job->id;
            if(is_link($temp))throw new RecoveryFailure('Geçici kurtarma dosyası geçersiz.');
            if(is_file($temp)&&!unlink($temp))throw new RecoveryFailure('Yarım kalan dosya temizlenemedi.');
            if(!preg_match('/\Abefore-[0-9]+\z/',$file['before']))throw new RecoveryFailure('Dosya geri alma günlüğü geçersiz.');
            if($file['existed']){
                if(!is_file($dir.'/'.$file['before'])||!hash_equals($file['before_hash'],hash_file('sha256',$dir.'/'.$file['before'])))throw new RecoveryFailure('Dosya geri alma kopyası doğrulanamadı.');
                if(!copy($dir.'/'.$file['before'],$dest))throw new RecoveryFailure('Dosya geri alınamadı.');
            }
            elseif(is_file($dest)&&!unlink($dest))throw new RecoveryFailure('Yeni kurtarma dosyası geri alınamadı.');
        }
        unlink($path);
    }
}
