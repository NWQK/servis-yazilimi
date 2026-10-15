<?php
namespace App\Services;

use App\Models\{Service,ServicePhoto,User};
use App\Services\Recovery\RecoveryWriteLock;
use Illuminate\Support\Facades\{DB,Storage};
use Carbon\Carbon;

class PhotoRetention
{
    public static function days(): int
    {
        $days=(int)DB::table('settings')->where('parent_id',0)->where('type','platform')->where('name','photo_retention_days')->value('value');
        return $days>=1 && $days<=365?$days:90;
    }
    public static function expiredService(Service $service): bool
    {
        $completed=$service->completed_at??$service->updated_at??$service->created_at;
        return ($service->getAttributes()['status']??null)==='completed' && $completed && Carbon::parse($completed)->lte(now()->subDays(self::days()));
    }
    public static function expiredQuery($query,int $days): void
    {
        $query->where('status','completed')->whereRaw('COALESCE(services.completed_at, services.updated_at, services.created_at) <= ?',[now()->subDays($days)]);
    }
    private static function remember(ServicePhoto $photo): void
    {
        DB::table('service_photo_deletions')->insertOrIgnore(['filename'=>$photo->filename,'parent_id'=>$photo->parent_id,'purged_at'=>now()]);
    }
    /** This small irreversible ledger is outside the tenant restore boundary. */
    public static function markExpired(?int $owner=null,?int $service=null): void
    {
        $days=self::days();
        ServicePhoto::when($owner,fn($q)=>$q->where('parent_id',$owner))
            ->when($service,fn($q)=>$q->where('service_id',$service))
            ->whereHas('service',function($q)use($days){$q->whereColumn('services.parent_id','service_photos.parent_id');self::expiredQuery($q,$days);})
            ->chunkById(200,function($photos){foreach($photos as $photo)self::remember($photo);});
    }
    public static function setDays(int $days): void
    {
        DB::transaction(function()use($days){
            self::lockPolicy();
            // Changing the policy must not bring already expired images back into view.
            self::markExpired();
            DB::table('settings')->updateOrInsert(['parent_id'=>0,'type'=>'platform','name'=>'photo_retention_days'],['value'=>(string)$days]);
        });
    }
    public static function lockPolicy(): void
    {
        DB::table('settings')->where('parent_id',0)->where('type','platform')->where('name','photo_retention_days')->lockForUpdate()->first();
    }
    public static function protectRestore(\App\Services\Recovery\RecoveryStore $source,\App\Services\Recovery\RecoveryStore $live,int $owner): void
    {
        self::markExpired($owner);
        // An old backup can contain photos already missing from the current photo table.
        foreach($source->rows('service_photos') as $row){
            if((int)$row['parent_id']!==$owner)continue;
            $prior=$live->get('services',$row['service_id']);
            if(!$prior || (int)$prior['parent_id']!==$owner)continue;
            $service=(new Service())->setRawAttributes($prior,true);
            if(self::expiredService($service))self::remember((new ServicePhoto())->forceFill($row));
        }
    }
    private function paused(int $owner): bool
    {
        return DB::table('business_recovery_locks')->where('owner_id',$owner)->exists();
    }
    public function purge(): array
    {
        $stats=['photos'=>0,'orphans'=>0,'bytes'=>0];$days=self::days();
        $query=ServicePhoto::where(function($q)use($days){
            $q->whereExists(fn($q)=>$q->selectRaw('1')->from('service_photo_deletions')->whereColumn('filename','service_photos.filename'))
                ->orWhereNotExists(fn($q)=>$q->selectRaw('1')->from('users')->whereColumn('users.id','service_photos.parent_id')->where('users.type','owner'))
                ->orWhereDoesntHave('service',fn($q)=>$q->whereColumn('services.parent_id','service_photos.parent_id'))
                ->orWhereHas('service',function($q)use($days){$q->whereColumn('services.parent_id','service_photos.parent_id');self::expiredQuery($q,$days);});
        });
        $query->chunkById(200,function($photos)use(&$stats){
            foreach($photos as $photo) {
                if($this->paused($photo->parent_id))continue;
                app(RecoveryWriteLock::class)->shared($photo->parent_id,function()use($photo,&$stats){
                    $deleted=DB::transaction(function()use($photo){
                        $owner=User::where('type','owner')->whereKey($photo->parent_id)->lockForUpdate()->first();
                        if($this->paused($photo->parent_id))return null;
                        $fresh=ServicePhoto::lockForUpdate()->find($photo->id);
                        if(!$fresh || (int)$fresh->parent_id!==(int)$photo->parent_id)return null;
                        $service=Service::where('parent_id',$fresh->parent_id)->find($fresh->service_id);
                        $marked=DB::table('service_photo_deletions')->where('filename',$fresh->filename)->exists();
                        if($owner && $service && !$marked && !self::expiredService($service))return null;
                        self::remember($fresh);$fresh->delete();return $fresh;
                    });
                    if($deleted){$stats['photos']++;$this->deleteFile($deleted->filename,$stats);}
                });
            }
        });
        $this->orphans($stats);
        DB::table('settings')->updateOrInsert(['parent_id'=>0,'type'=>'platform','name'=>'photo_cleanup_last'],['value'=>json_encode($stats+['at'=>now()->toIso8601String()],JSON_THROW_ON_ERROR)]);
        return $stats;
    }
    private function deleteFile(string $filename,array &$stats): void
    {
        if(!preg_match('/\A[0-9]+_[0-9]+_[a-f0-9-]{36}\.jpg\z/',$filename))return;
        $disk=Storage::disk('service_photos');$path='photo/'.$filename;
        if(!$disk->exists($path))return;
        $bytes=$disk->size($path);
        $disk->delete($path);$stats['bytes']+=$bytes;
    }
    private function orphans(array &$stats): void
    {
        $disk=Storage::disk('service_photos');$root=$disk->path('photo');
        if(!is_dir($root))return;
        if(is_link($root)||is_link($disk->path('')))throw new \RuntimeException('Fotoğraf klasörü sembolik bağlantı olamaz.');
        $batch=[];
        foreach(new \DirectoryIterator($root) as $file){
            if($file->isDot()||$file->isLink()||!$file->isFile())continue;
            $name=$file->getFilename();
            if(!preg_match('/\A[0-9]+_[0-9]+_[a-f0-9-]{36}\.jpg\z/',$name))continue;
            $batch[$name]=$file->getMTime();
            if(count($batch)>=200){$this->orphanBatch($batch,$stats);$batch=[];}
        }
        if($batch)$this->orphanBatch($batch,$stats);
    }
    private function orphanBatch(array $batch,array &$stats): void
    {
        $known=ServicePhoto::whereIn('filename',array_keys($batch))->pluck('filename')->flip();
        foreach($batch as $filename=>$mtime){
            if(isset($known[$filename])||$mtime>now()->subDay()->timestamp)continue;
            $owner=(int)explode('_',$filename)[0];
            if($owner<1 || $this->paused($owner))continue;
            app(RecoveryWriteLock::class)->shared($owner,function()use($owner,$filename,&$stats){
                DB::transaction(function()use($owner,$filename,&$stats){
                    User::whereKey($owner)->lockForUpdate()->first();
                    if($this->paused($owner)||ServicePhoto::where('filename',$filename)->exists())return;
                    $disk=Storage::disk('service_photos');$path='photo/'.$filename;
                    if(!$disk->exists($path)||$disk->lastModified($path)>now()->subDay()->timestamp)return;
                    $this->deleteFile($filename,$stats);$stats['orphans']++;
                });
            });
        }
    }
}
