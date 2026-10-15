<?php
namespace App\Http\Middleware;

use App\Services\ImageUploads;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SecureUploads
{
    public function handle($request,Closure $next)
    {
        if(!$request->user() || !$request->files->count()) return $next($request);
        $temporary=[]; $count=0; $total=0;
        $visit=function($files,string $prefix='')use(&$visit,&$temporary,&$count,&$total){
            foreach($files as $key=>$file){
                $field=$prefix===''?(string)$key:$prefix.'.'.$key;
                if(is_array($file)) { $files[$key]=$visit($file,$field); continue; }
                if(!$file)continue;
                $fail=fn($message)=>throw ValidationException::withMessages([$field=>$message]);
                if(!$file->isValid()) $fail('Dosya yüklenemedi. Sunucunun yükleme sınırını kontrol edin.');
                if(++$count>10 || ($total+=$file->getSize())>24*1024*1024 || $file->getSize()>8*1024*1024)
                    $fail('En fazla 10 dosya yükleyin. Dosya başına 8 MB, toplam 24 MB sınırı vardır.');
                $extension=strtolower($file->getClientOriginalExtension());
                $mime=$file->getMimeType();
                if(in_array(explode('.',$field)[0],['invoice_logo','logo','light_logo','landing_logo','favicon'],true) && $file->getSize()>2*1024*1024)
                    $fail('Logo dosyası en fazla 2 MB olabilir.');
                $images=['image/jpeg'=>['jpg','jpeg'],'image/png'=>['png'],'image/webp'=>['webp']];
                if(isset($images[$mime]) && in_array($extension,$images[$mime],true)){
                    // Photos are decoded once by their controller, after the tenant/count checks.
                    if(str_starts_with($field,'photos.')) continue;
                    [$width,$height]=match(explode('.',$field)[0]){
                        'invoice_logo'=>[600,240], 'favicon'=>[64,64], 'profile'=>[400,400],
                        'logo','light_logo','landing_logo'=>[1000,400], default=>[1600,1600],
                    };
                    $format=match($mime){'image/jpeg'=>'jpeg','image/webp'=>'webp',default=>'png'};
                    $bytes=ImageUploads::encode(UploadedFile::createFromBase($file),$field,$width,$height,$format);
                    $temp=tempnam(sys_get_temp_dir(),'sr-image-');
                    if(!$temp) $fail('Görsel için geçici alan oluşturulamadı.');
                    $temporary[]=$temp;
                    if(file_put_contents($temp,$bytes)!==strlen($bytes)) $fail('Görsel işlenemedi.');
                    $files[$key]=new UploadedFile($temp,(string)Str::uuid().'.'.$extension,$mime,null,true);
                    continue;
                }
                // Only explicit document types, with a matching detected MIME. Never trust a supplied MIME.
                $documents=[
                    'pdf'=>['application/pdf'],
                    'doc'=>['application/msword','application/x-ole-storage','application/CDFV2'],
                    'docx'=>['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                    'xls'=>['application/vnd.ms-excel','application/x-ole-storage','application/CDFV2'],
                    'xlsx'=>['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                    'txt'=>['text/plain'], 'csv'=>['text/plain','text/csv','application/csv'],
                ];
                if(!isset($documents[$extension]) || !in_array($mime,$documents[$extension],true))
                    $fail('Desteklenmeyen veya içeriği dosya türüyle eşleşmeyen dosya. JPG, PNG, WebP veya desteklenen belge türlerini kullanın.');
                // Safe basenames also protect legacy document controllers using the client filename.
                $files[$key]=new UploadedFile($file->getRealPath(),(string)Str::uuid().'.'.$extension,$mime,null,true);
            }
            return $files;
        };
        try {
            $request->files->replace($visit($request->files->all()));
            return $next($request);
        } finally { foreach($temporary as $file) if(is_file($file)) unlink($file); }
    }
}
