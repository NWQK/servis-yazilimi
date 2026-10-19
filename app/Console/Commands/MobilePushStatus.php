<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB,Schema};

/** Read-only diagnostics: never sends a message or prints credentials/device identifiers. */
class MobilePushStatus extends Command
{
    protected $signature='mobile:push-status';
    protected $description='Mobil push yapılandırması ve gönderim kuyruğunu sırları göstermeden kontrol eder.';

    public function handle(): int
    {
        $enabled=(bool)config('mobile_push.enabled');
        $this->line('Gönderim: '.($enabled ? 'açık' : 'kapalı'));
        $validCredentials=false;
        try {
            $path=config('mobile_push.credentials_file');
            if (is_string($path) && is_file($path) && is_readable($path)) {
                $credentials=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
                $validCredentials=($credentials['project_id']??null)===config('mobile_push.project_id')
                    && !empty($credentials['client_email']) && !empty($credentials['private_key'])
                    && openssl_pkey_get_private($credentials['private_key'])!==false;
            }
        } catch (\Throwable $e) { /* Do not expose paths, JSON, keys or provider responses. */ }
        $this->line('Sunucu kimliği: '.($validCredentials ? 'yerel doğrulama geçti' : 'eksik veya geçersiz'));
        $ready=true;
        foreach (['mobile_push_devices','mobile_push_outbox'] as $table) {
            if (!Schema::hasTable($table)) { $this->warn($table.' tablosu eksik; migration kontrol edilmeli.'); $ready=false; }
        }
        if ($ready) {
            $this->line('Kayıtlı cihaz: '.DB::table('mobile_push_devices')->count());
            foreach (['pending','sending','sent','failed','uncertain','cancelled'] as $status) {
                $this->line($status.': '.DB::table('mobile_push_outbox')->where('status',$status)->count());
            }
        }
        foreach (['scheduler','mobile_push_worker'] as $component) {
            $health=Schema::hasTable('system_health') ? DB::table('system_health')->where('component',$component)->first() : null;
            $fresh=$health && $health->checked_at && \Illuminate\Support\Carbon::parse($health->checked_at)->gte(now()->subMinutes(3));
            $this->line($component.': '.($fresh ? 'son 3 dakikada çalıştı' : 'güncel çalışma kaydı yok'));
        }
        $transport=Schema::hasTable('system_health') ? DB::table('system_health')->where('component','mobile_push_transport')->first() : null;
        if ($transport) {
            $safeCode=preg_match('/^(http_[0-9]{3}|send_exception)$/',$transport->code??'') ? $transport->code : 'unknown';
            $this->line('Son FCM denemesi: '.$safeCode.' ('.$transport->checked_at.')');
        }
        $this->comment('Bu kontrol FCM bağlantısını veya cihaz teslimini doğrulamaz; hiçbir bildirim göndermez.');
        $this->comment('pending artıyorsa zamanlayıcıyı; failed/uncertain varsa Firebase yetkilerini ve sunucu bağlantısını kontrol edin.');
        return $enabled && $validCredentials && $ready ? self::SUCCESS : self::FAILURE;
    }
}
