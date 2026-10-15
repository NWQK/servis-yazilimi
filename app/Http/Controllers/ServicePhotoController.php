<?php
namespace App\Http\Controllers;

use App\Models\{Service,ServicePhoto,User,VehicleQrCode};
use App\Services\ImageUploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ServicePhotoController extends Controller
{
    private function authorizeService(Service $service,bool $write=false): void
    {
        $user=auth()->user();
        abort_unless($user && $user->type!=='client' && (int)$service->parent_id===(int)parentId()
            && $user->can($write?'edit service':'show service'),403);
        abort_if($user->type==='employee' && (int)$service->assign!==(int)$user->id,403);
    }
    public function store(Request $request,Service $service)
    {
        $this->authorizeService($service,true);
        $request->validate(['photos'=>'required|array|min:1|max:10','photos.*'=>'required|file|mimes:jpg,jpeg,png,webp|max:8192']);
        $written=[];
        try {
            DB::transaction(function()use($request,$service,&$written){
                User::whereKey($service->parent_id)->lockForUpdate()->firstOrFail();
                $fresh=Service::where('parent_id',parentId())->lockForUpdate()->findOrFail($service->id);
                $this->authorizeService($fresh,true);
                if(\App\Services\PhotoRetention::expiredService($fresh))
                    throw ValidationException::withMessages(['photos'=>'Bu servisin fotoğraf saklama süresi doldu. Yeni işlem için yeni bir servis kaydı açın.']);
                if(ServicePhoto::where('parent_id',parentId())->where('service_id',$fresh->id)->count()+count($request->file('photos'))>10)
                    throw ValidationException::withMessages(['photos'=>'Bir servis kaydına en fazla 10 fotoğraf eklenebilir.']);
                foreach($request->file('photos') as $i=>$file) {
                    $bytes=ImageUploads::encode($file,'photos.'.$i,1600,1600,'jpeg');
                    $filename=parentId().'_'.$fresh->id.'_'.Str::uuid().'.jpg';
                    $written[]='photo/'.$filename;
                    if(!Storage::disk('service_photos')->put('photo/'.$filename,$bytes))
                        throw ValidationException::withMessages(['photos'=>'Fotoğraflar kaydedilemedi. Depolama izinlerini kontrol edin.']);
                    ServicePhoto::create(['parent_id'=>parentId(),'service_id'=>$fresh->id,'filename'=>$filename,
                        'bytes'=>strlen($bytes),'uploaded_by'=>auth()->id()]);
                }
            });
        } catch(\Throwable $e) {
            foreach($written as $path) {
                try { Storage::disk('service_photos')->delete($path); }
                catch(\Throwable $cleanup) { \Log::warning('Kabul fotoğrafı yükleme temizliği başarısız.',['owner_id'=>parentId(),'exception'=>get_class($cleanup)]); }
            }
            if($e instanceof \League\Flysystem\FilesystemException)
                throw ValidationException::withMessages(['photos'=>'Fotoğraflar kaydedilemedi. Sunucu depolama alanı veya yazma izinleri için destek alın.']);
            throw $e;
        }
        return back()->with('success','Araç kabul fotoğrafları eklendi.');
    }
    private function image(ServicePhoto $photo)
    {
        abort_unless(preg_match('/\A[0-9]+_[0-9]+_[a-f0-9-]{36}\.jpg\z/',$photo->filename),404);
        $disk=Storage::disk('service_photos'); $path='photo/'.$photo->filename;
        abort_unless($disk->exists($path),404);
        return $disk->response($path,'arac-kabul.jpg',[
            'Content-Type'=>'image/jpeg','Content-Disposition'=>'inline; filename="arac-kabul.jpg"',
            'Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff',
            'Referrer-Policy'=>'no-referrer','X-Robots-Tag'=>'noindex, nofollow',
            'Content-Security-Policy'=>"default-src 'none'; sandbox",
        ],'inline');
    }
    public function show(Service $service,int $photo)
    {
        $this->authorizeService($service);
        return $this->image(ServicePhoto::visible()->where('parent_id',parentId())->where('service_id',$service->id)->findOrFail($photo));
    }
    public function destroy(Service $service,int $photo)
    {
        $this->authorizeService($service,true);
        $filename=DB::transaction(function()use($service,$photo){
            User::whereKey(parentId())->lockForUpdate()->firstOrFail();
            $fresh=Service::where('parent_id',parentId())->lockForUpdate()->findOrFail($service->id);
            $this->authorizeService($fresh,true);
            if(\App\Services\PhotoRetention::expiredService($fresh))
                \App\Services\PhotoRetention::markExpired((int)$fresh->parent_id,(int)$fresh->id);
            $record=ServicePhoto::where('parent_id',parentId())->where('service_id',$fresh->id)->lockForUpdate()->findOrFail($photo);
            $filename=$record->filename; $record->delete(); return $filename;
        });
        if(preg_match('/\A[0-9]+_[0-9]+_[a-f0-9-]{36}\.jpg\z/',$filename))
            Storage::disk('service_photos')->delete('photo/'.$filename);
        return back()->with('success','Fotoğraf silindi.');
    }
    public function customer(string $token,int $photo)
    {
        $code=VehicleQrCode::where('token',$token)->whereNotNull('assigned_at')->firstOrFail();
        $vehicle=$code->vehicle()->where('parent_id',$code->parent_id)->firstOrFail();
        $record=ServicePhoto::visible()->where('parent_id',$code->parent_id)->whereHas('service',fn($q)=>
            $q->where('parent_id',$code->parent_id)->where('vehicle',$vehicle->id))->findOrFail($photo);
        return $this->image($record);
    }
}
