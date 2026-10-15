<?php
namespace App\Services\Recovery;
use App\Models\{BusinessRecovery,User};
use App\Services\{PlatformBackups,AdminAudit};
use Illuminate\Support\Facades\{DB,Schema};

class BusinessRecoveryService
{
    public function __construct(private RecoveryFiles $files,private RecoveryPlan $plan){}
    public function work(): void
    {
        if(!Schema::hasTable('business_recoveries'))return;
        $lock=fopen(app(PlatformBackups::class)->root().'/run.lock','c');
        if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){if($lock)fclose($lock);return;}
        try {
            $this->prune();
            // A process interrupted during application must repair files before accepting further work.
            foreach(BusinessRecovery::where('status','applying')->get() as $interrupted){
                try{app(RecoveryWriteLock::class)->exclusive($interrupted->owner_id,function()use($interrupted){
                    $this->files->undoFiles($interrupted);
                    $this->release($interrupted,'failed','Önceki işlem kesildi; veritabanı işlemi tamamlanmadı ve dosyalar geri alındı.');
                });}
                catch(\Throwable $e){$interrupted->update(['status'=>'needs_attention','error'=>'Kesilen işlemin dosyaları otomatik geri alınamadı. İşletme kilitli tutuluyor; işlem öncesi kopyayı koruyun.']);}
            }
            $job=BusinessRecovery::whereIn('status',['queued_prepare','preparing','queued_apply'])->oldest()->first();
            if(!$job)return;
            try {
                if(!User::where('id',$job->actor_id)->where('type','super admin')->where('is_active',1)->exists())throw new RecoveryFailure('İşlemi isteyen yönetici hesabı artık yetkili değil.');
                if(in_array($job->status,['queued_prepare','preparing'],true))$this->prepare($job);
                else app(RecoveryWriteLock::class)->exclusive($job->owner_id,fn()=>$this->execute($job));
            }catch(\Throwable $e){
                $message=$e instanceof RecoveryFailure?$e->getMessage():'Kurtarma tamamlanamadı. Şema, kayıt/dosya çakışmaları ve sunucu izinleri kontrol edilmelidir.';
                if($job->fresh()?->status==='completed')return;
                try{$this->files->undoFiles($job);$this->release($job,'failed',$message);}
                catch(\Throwable $repair){$job->update(['status'=>'needs_attention','error'=>'Dosya geri alma tamamlanamadı. İşletme kilitli; işlem öncesi kopya korunuyor.']);}
            }
        }finally{flock($lock,LOCK_UN);fclose($lock);}
    }
    public function prune(): void
    {
        foreach(BusinessRecovery::whereNotIn('status',['queued_prepare','preparing','queued_apply','applying','needs_attention'])
            ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('business_recovery_locks')->whereColumn('recovery_id','business_recoveries.id'))
            ->cursor() as $job){
            try{
                $terminal=in_array($job->status,['completed','undone','failed','cancelled','expired'],true);
                if(!$terminal&&(!$job->expires_at||$job->expires_at->isFuture()))continue;
                if(!$terminal)$job->update(['status'=>'expired','finished_at'=>now()]);
                $keep=$job->checkpoint_hash&&$job->checkpoint_expires_at?->isFuture();
                if(!$keep&&BusinessRecovery::where('source_recovery_id',$job->id)->whereIn('status',['queued_prepare','preparing'])->exists())continue;
                $this->files->clean($job,(bool)$keep);
                if(!$keep&&$job->checkpoint_hash)$job->update(['checkpoint_hash'=>null,'checkpoint_expires_at'=>null]);
                if($job->updated_at->lt(now()->subDays(90))&&!BusinessRecovery::where('source_recovery_id',$job->id)->exists())$job->delete();
            }catch(\Throwable $e){
                \Illuminate\Support\Facades\Log::warning('Kurtarma çalışma alanı temizlenemedi.',['operation_id'=>$job->id]);
            }
        }
    }
    public function prepare(BusinessRecovery $job): void
    {
        if(!BusinessRecovery::whereKey($job->id)->whereIn('status',['queued_prepare','preparing'])
            ->update(['status'=>'preparing','started_at'=>now(),'error'=>null]))return;
        $job->refresh();
        $dir=$this->files->directory($job);
        foreach(['database.sql','full.sqlite','tenant.sqlite','current.sqlite','selected.sqlite','files-plan.json'] as $name)
            if(is_file($dir.'/'.$name)&&!unlink($dir.'/'.$name))throw new RecoveryFailure('Önceki hazırlık alanı temizlenemedi.');
        $archive=$this->files->source($job);
        if($job->source_recovery_id){
            $zip=$this->files->zip($archive);
            try {
                $input=$zip->getStream('tenant.sqlite');$out=fopen($dir.'/tenant.sqlite','wb');
                if(!$input||!$out)throw new RecoveryFailure('İşlem öncesi işletme verileri açılamadı.');
                try{stream_copy_to_stream($input,$out);}finally{fclose($input);fclose($out);}
                chmod($dir.'/tenant.sqlite',0600);
            }finally{$zip->close();}
            $source=new RecoveryStore($dir.'/tenant.sqlite');
        }else{
            $this->files->extractSql($archive,$dir.'/database.sql');
            $raw=new RecoveryStore($dir.'/full.sqlite');$raw->import($dir.'/database.sql');
            $source=$raw->tenant($job->owner_id,$dir.'/tenant.sqlite');unset($raw);
            unlink($dir.'/full.sqlite');unlink($dir.'/database.sql');
        }
        $live=app(RecoveryWriteLock::class)->exclusive($job->owner_id,fn()=>DB::transaction(function()use($job,$dir){
            User::where('type','owner')->whereKey($job->owner_id)->lockForUpdate()->firstOrFail();
            return RecoveryStore::live($job->owner_id,$dir.'/current.sqlite');
        }));
        $summary=$this->plan->build($source,$live,$job->owner_id,$job->mode);
        $wanted=$this->plan->selectedFiles($source,$live,$job->mode,$dir.'/selected.sqlite');
        try{$files=$this->files->plan($wanted,$archive,$job->owner_id,$job->mode);}
        catch(RecoveryFailure $e){$summary['conflicts'][]=$e->getMessage();$files=[];}
        $summary['files']=count($files);$summary['archive_hash']=hash_file('sha256',$archive);
        $json=json_encode($files,JSON_THROW_ON_ERROR);
        if(file_put_contents($dir.'/files-plan.json',$json,LOCK_EX)===false)throw new RecoveryFailure('Dosya kurtarma planı yazılamadı.');
        chmod($dir.'/files-plan.json',0600);$summary['files_hash']=hash('sha256',$json);
        $job->update(['status'=>$summary['conflicts']?'blocked':'preview','summary'=>$summary,
            'live_hash'=>$live->fingerprint($job->owner_id),'source_hash'=>hash_file('sha256',$source->path),
            'expires_at'=>now()->addHours(24),'finished_at'=>now()]);
        $this->audit($job,'recovery.prepared',['mode'=>$job->mode,'status'=>$job->status]);
    }
    public function execute(BusinessRecovery $job): void
    {
        if(DB::getDriverName()==='mysql'){
            $tables=array_merge(RecoveryTables::existing(),['business_recoveries','business_recovery_locks','model_has_roles','model_has_permissions','vehicle_history_index','admin_audit_logs']);
            $unsafe=DB::table('information_schema.tables')->where('table_schema',DB::getDatabaseName())
                ->whereIn('table_name',$tables)->whereRaw('UPPER(engine) <> ?', ['INNODB'])->exists();
            if($unsafe)throw new RecoveryFailure('Güvenli geri yükleme için ilgili MySQL tabloları InnoDB olmalıdır.');
        }elseif(DB::getDriverName()!=='sqlite')throw new RecoveryFailure('Bu veritabanı sürücüsü kurtarma için desteklenmiyor.');
        if(!$job->expires_at||$job->expires_at->isPast()||!empty($job->summary['conflicts']))throw new RecoveryFailure('Ön izleme geçersiz veya süresi dolmuş. Yeni hazırlık oluşturun.');
        $dir=$this->files->directory($job);$archive=$dir.'/source.zip';
        foreach(['tenant.sqlite'=>$job->source_hash,'source.zip'=>$job->summary['archive_hash'],'files-plan.json'=>$job->summary['files_hash']] as $name=>$hash)
            if(!is_file($dir.'/'.$name)||!hash_equals($hash,hash_file('sha256',$dir.'/'.$name)))throw new RecoveryFailure('Hazırlanan kurtarma verisinin bütünlüğü doğrulanamadı.');
        $source=new RecoveryStore($dir.'/tenant.sqlite');
        // Commit a durable per-business pause, then acquire the same owner lock used by every panel write.
        DB::transaction(function()use($job){
            User::where('type','owner')->whereKey($job->owner_id)->lockForUpdate()->firstOrFail();
            $fresh=BusinessRecovery::whereKey($job->id)->lockForUpdate()->firstOrFail();
            if($fresh->status!=='queued_apply')throw new RecoveryFailure('Kurtarma işlemi artık uygulama beklemiyor.');
            $existing=DB::table('business_recovery_locks')->where('owner_id',$job->owner_id)->first();
            if($existing&&$existing->recovery_id!==$job->id&&$existing->recovery_id!==$job->source_recovery_id)throw new RecoveryFailure('İşletmede başka kurtarma işlemi çalışıyor.');
            DB::table('business_recovery_locks')->updateOrInsert(['owner_id'=>$job->owner_id],['recovery_id'=>$job->id,'created_at'=>now()]);
            $job->update(['status'=>'applying','started_at'=>now(),'error'=>null]);
        });
        DB::transaction(function()use($job,$dir,$source,$archive){
            User::where('type','owner')->whereKey($job->owner_id)->lockForUpdate()->firstOrFail();
            if(!User::whereKey($job->actor_id)->where('type','super admin')->where('is_active',1)->lockForUpdate()->first())
                throw new RecoveryFailure('Yönetici hesabının yetkisi değişti; işlem uygulanmadı.');
            if(is_file($dir.'/apply-current.sqlite'))unlink($dir.'/apply-current.sqlite');
            $current=RecoveryStore::live($job->owner_id,$dir.'/apply-current.sqlite');
            if(!hash_equals($job->live_hash,$current->fingerprint($job->owner_id)))throw new RecoveryFailure('Ön izlemeden sonra işletme verileri değişti. Güncel kayıtları korumak için işlem durduruldu; yeni ön izleme hazırlayın.');
            $summary=$this->plan->build($source,$current,$job->owner_id,$job->mode);
            if($summary['conflicts'])throw new RecoveryFailure('Kayıt ilişkileri değişti; yeni ön izleme hazırlayın.');
            if(is_file($dir.'/apply-selected.sqlite'))unlink($dir.'/apply-selected.sqlite');
            $wanted=$this->plan->selectedFiles($source,$current,$job->mode,$dir.'/apply-selected.sqlite');
            $freshFiles=$this->files->plan($wanted,$archive,$job->owner_id,$job->mode);
            if(!hash_equals($job->summary['files_hash'],hash('sha256',json_encode($freshFiles,JSON_THROW_ON_ERROR))))throw new RecoveryFailure('Dosyalar ön izlemeden sonra değişti. Yeni ön izleme hazırlayın.');
            if(is_file($dir.'/checkpoint.zip'))throw new RecoveryFailure('Bu işlem için kurtarma kopyası zaten var. Tekrarlanan uygulama reddedildi.');
            $checkpoint=$this->files->checkpoint($job,$current,$freshFiles);
            // This metadata must survive a database rollback or worker interruption.
            if(file_put_contents($dir.'/checkpoint-sha256',$checkpoint,LOCK_EX)===false)throw new RecoveryFailure('Kurtarma kopyasının doğrulama bilgisi yazılamadı.');
            chmod($dir.'/checkpoint-sha256',0600);
            $job->checkpoint_hash=$checkpoint;$job->checkpoint_expires_at=now()->addDays(7);
            $filePlan=json_decode(file_get_contents($dir.'/files-plan.json'),true,512,JSON_THROW_ON_ERROR);
            $this->files->apply($job,$archive,$filePlan);
            $this->plan->apply($source,$current,$job->owner_id,$job->mode);
            DB::table('users')->where('id',$job->owner_id)->increment('recovery_version');
            $job->status='completed';$job->finished_at=now();$job->save();
            if($job->source_recovery_id)BusinessRecovery::whereKey($job->source_recovery_id)->update(['status'=>'undone']);
            DB::table('business_recovery_locks')->where('owner_id',$job->owner_id)->where('recovery_id',$job->id)->delete();
            $this->audit($job,'recovery.completed',['mode'=>$job->mode,'insert'=>$summary['insert'],'delete'=>$summary['delete']]);
        },1);
    }
    private function release(BusinessRecovery $job,string $status,string $message): void
    {
        $dir=$this->files->directory($job);
        $checkpoint=is_file($dir.'/checkpoint-sha256')?trim(file_get_contents($dir.'/checkpoint-sha256')):null;
        DB::transaction(function()use($job,$status,$message,$checkpoint){
            User::whereKey($job->owner_id)->lockForUpdate()->first();
            $job->update(['status'=>$status,'error'=>$message,'finished_at'=>now(),'checkpoint_hash'=>$checkpoint,
                'checkpoint_expires_at'=>$checkpoint?now()->addDays(7):null]);
            if($job->source_recovery_id&&$job->sourceRecovery?->status==='needs_attention'){
                DB::table('business_recovery_locks')->where('owner_id',$job->owner_id)->where('recovery_id',$job->id)->update(['recovery_id'=>$job->source_recovery_id]);
            }else DB::table('business_recovery_locks')->where('owner_id',$job->owner_id)->where('recovery_id',$job->id)->delete();
        });
    }
    private function audit(BusinessRecovery $job,string $action,array $data): void
    {
        if(!Schema::hasTable('admin_audit_logs'))return;
        DB::table('admin_audit_logs')->insert(['actor_id'=>$job->actor_id,'actor_name'=>User::whereKey($job->actor_id)->value('name')??'Yönetici',
            'owner_id'=>$job->owner_id,'action'=>$action,'before'=>null,'after'=>json_encode(['operation_id'=>$job->id]+$data,JSON_THROW_ON_ERROR),'created_at'=>now()]);
    }
}
