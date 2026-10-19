<?php
namespace App\Services;

use Illuminate\Support\Facades\{Cache,Crypt,Http};
use RuntimeException;

class FirebasePush
{
    private function accessToken(): string {
        $path=config('mobile_push.credentials_file');
        if (!$path || !is_file($path)) throw new RuntimeException('Firebase sunucu kimliği yapılandırılmamış.');
        $credentials=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
        if (($credentials['project_id']??null)!==config('mobile_push.project_id') || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new RuntimeException('Firebase sunucu kimliği proje ile eşleşmiyor.');
        }
        $cacheKey='mobile-fcm-oauth:'.hash('sha256',json_encode($credentials));
        if ($cached=Cache::get($cacheKey)) return Crypt::decryptString($cached);
        $encode=fn($data)=>rtrim(strtr(base64_encode(json_encode($data,JSON_UNESCAPED_SLASHES)),'+/','-_'),'=');
        $jwt=$encode(['alg'=>'RS256','typ'=>'JWT']).'.'.$encode([
            'iss'=>$credentials['client_email'],'scope'=>'https://www.googleapis.com/auth/firebase.messaging',
            'aud'=>'https://oauth2.googleapis.com/token','iat'=>time(),'exp'=>time()+300,
        ]);
        if (!openssl_sign($jwt,$signature,$credentials['private_key'],OPENSSL_ALGO_SHA256)) throw new RuntimeException('Firebase imzası oluşturulamadı.');
        $jwt.='.'.rtrim(strtr(base64_encode($signature),'+/','-_'),'=');
        $response=Http::asForm()->connectTimeout(5)->timeout(10)->post('https://oauth2.googleapis.com/token',[
            'grant_type'=>'urn:ietf:params:oauth:grant-type:jwt-bearer','assertion'=>$jwt,
        ]);
        if (!$response->successful() || !is_string($response->json('access_token'))) throw new RuntimeException('Firebase sunucu yetkilendirmesi başarısız.');
        $token=$response->json('access_token');
        Cache::put($cacheKey,Crypt::encryptString($token),min(3000,max(1,(int)$response->json('expires_in')-60)));
        return $token;
    }
    public function send(string $deviceToken,int $appointment,string $event): string {
        if (!config('mobile_push.enabled')) throw new RuntimeException('Cihaz bildirimi gönderimi kapalı.');
        $project=config('mobile_push.project_id');
        if (!preg_match('/^[a-z][a-z0-9-]{4,62}$/',$project)) throw new RuntimeException('Firebase proje kimliği geçersiz.');
        $tag='appointment-'.$appointment.'-'.$event;
        $response=Http::withToken($this->accessToken())->connectTimeout(5)->timeout(10)
            ->post('https://fcm.googleapis.com/v1/projects/'.$project.'/messages:send',['message'=>[
                'fid'=>$deviceToken,
                // No customer names, phones, plates or tenant data on the lock screen.
                'notification'=>['title'=>'Sanayirandevu','body'=>'Randevu bildiriminiz var. Ayrıntıları uygulamada görüntüleyin.'],
                'data'=>['appointment_id'=>(string)$appointment,'event'=>$event],
                'android'=>['priority'=>'HIGH','ttl'=>'300s','collapse_key'=>$tag,'notification'=>[
                    'tag'=>$tag,'channel_id'=>'appointments_v1','icon'=>'ic_stat_sanayirandevu',
                    'color'=>'#E51B24','default_sound'=>true,'default_vibrate_timings'=>true,
                    'notification_priority'=>'PRIORITY_HIGH',
                ]],
                'apns'=>['headers'=>['apns-collapse-id'=>$tag,'apns-expiration'=>(string)(time()+300)]],
            ]]);
        SystemHealth::record('mobile_push_transport',$response->successful(),'http_'.$response->status());
        if ($response->successful()) return 'sent';
        foreach($response->json('error.details',[]) as $detail) if (($detail['errorCode']??'')==='UNREGISTERED') return 'unregistered';
        // No automatic retry: a timeout/server error may have accepted the message.
        return $response->serverError() ? 'uncertain' : 'failed';
    }
}
