<?php
namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/** Decode and re-encode raster uploads: client names, metadata and trailing data are discarded. */
class ImageUploads
{
    public static function encode(UploadedFile $file, string $field, int $maxWidth, int $maxHeight, string $format='png'): string
    {
        $fail=fn($message)=>throw ValidationException::withMessages([$field=>$message]);
        if(!$file->isValid() || $file->getSize()>8*1024*1024) $fail('Görsel yüklenemedi. En fazla 8 MB dosya seçin.');
        $info=@getimagesize($file->getRealPath());
        if(!$info || !in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true))
            $fail('Gerçek bir JPG, PNG veya WebP görseli seçin.');
        if($info[0]>6000 || $info[1]>6000 || $info[0]*$info[1]>24000000)
            $fail('Görsel en fazla 6000 × 6000 piksel ve toplam 24 megapiksel olabilir.');
        $limit=trim(ini_get('memory_limit'));
        if(preg_match('/\A(\d+)([KMG]?)\z/i',$limit,$match)){
            $limit=(int)$match[1]*match(strtoupper($match[2])){'G'=>1073741824,'M'=>1048576,'K'=>1024,default=>1};
            if(memory_get_usage(true)+$info[0]*$info[1]*8+16777216>$limit)
                $fail('Bu görsel sunucunun işleme sınırını aşıyor. Daha düşük çözünürlüklü bir görsel seçin.');
        }
        if(!function_exists('imagecreatefromstring')) $fail('Görsel işleme için sunucuda PHP GD eklentisi etkinleştirilmelidir.');
        $source=@imagecreatefromstring(file_get_contents($file->getRealPath()));
        if(!$source) $fail('Görsel okunamadı. Farklı bir görsel deneyin.');
        $target=null;
        try {
            if($info[2]===IMAGETYPE_JPEG && function_exists('exif_read_data')) {
                $exif=@exif_read_data($file->getRealPath());
                $orientation=(int)($exif['Orientation']??1);
                if(in_array($orientation,[2,4,5,7],true)) imageflip($source,IMG_FLIP_HORIZONTAL);
                $angle=match($orientation){3,4=>180,5,6=>-90,7,8=>90,default=>0};
                if($angle) {
                    $rotated=imagerotate($source,$angle,0);
                    if(!$rotated) $fail('Görsel yönü düzeltilemedi.');
                    imagedestroy($source); $source=$rotated;
                }
            }
            $ratio=min(1,$maxWidth/imagesx($source),$maxHeight/imagesy($source));
            $width=max(1,(int)round(imagesx($source)*$ratio)); $height=max(1,(int)round(imagesy($source)*$ratio));
            $target=imagecreatetruecolor($width,$height);
            if($format==='png') {
                imagealphablending($target,false); imagesavealpha($target,true);
                imagefill($target,0,0,imagecolorallocatealpha($target,0,0,0,127));
            } else imagefill($target,0,0,imagecolorallocate($target,255,255,255));
            imagecopyresampled($target,$source,0,0,0,0,$width,$height,imagesx($source),imagesy($source));
            ob_start();
            try {
                $ok=match($format){'jpeg'=>imagejpeg($target,null,82),'webp'=>imagewebp($target,null,82),default=>imagepng($target,null,6)};
                $bytes=ob_get_contents();
            } finally { ob_end_clean(); }
            if(!$ok || !$bytes) $fail('Görsel işlenemedi.');
            return $bytes;
        } finally { imagedestroy($source); if($target) imagedestroy($target); }
    }
}
