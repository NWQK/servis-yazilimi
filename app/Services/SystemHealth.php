<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Schema};
class SystemHealth
{
    public static function explanation(string $code): string
    {
        return ['configuration_error'=>'E-posta ayarları eksik veya geçersiz.','transport_error'=>'SMTP gönderimi tamamlanamadı. Bağlantı, kullanıcı bilgileri ve sağlayıcı kayıtlarını kontrol edin.',
            'invoice_mail_error'=>'Fatura e-postası tamamlanamadı. SMTP ayarlarını ve sağlayıcı kayıtlarını kontrol edin.','connection_error'=>'Sağlayıcı bağlantısı kurulamadı veya zaman aşımına uğradı.',
            'not_configured'=>'SMS ayarları eksik veya gönderim kapalı.','invalid_phone'=>'Telefon numarası formatı geçersiz.','not_sent'=>'E-posta gönderimi tamamlanmadı.'][$code] ?? 'Sağlayıcı hata kodu: '.$code;
    }
    public static function record(string $component,bool $success,string $code): void
    {
        try {
            if(!Schema::hasTable('system_health')) return;
            $code=substr(preg_replace('/[^a-zA-Z0-9_.-]/','_', $code),0,80);
            DB::table('system_health')->insertOrIgnore(['component'=>$component,'status'=>'unknown','checked_at'=>now()]);
            DB::table('system_health')->where('component',$component)->update([
                'status'=>$success ? 'ok' : 'error', 'code'=>$code,'checked_at'=>now(),
                'consecutive_failures'=>$success ? 0 : DB::raw('consecutive_failures + 1'),
                $success ? 'last_success_at' : 'last_failure_at'=>now(),
            ]);
            if(!$success){
                $key=['component'=>$component,'code'=>$code,'day'=>now()->toDateString()];
                DB::table('system_health_errors')->insertOrIgnore($key+['count'=>0,'last_seen_at'=>now()]);
                DB::table('system_health_errors')->where($key)->increment('count',1,['last_seen_at'=>now()]);
            }
        } catch(\Throwable $e) { \Log::warning('Sistem sağlık kaydı yazılamadı.',['exception_type'=>get_class($e)]); }
    }
}
