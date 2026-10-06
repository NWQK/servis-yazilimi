<?php
namespace App\Services;

use App\Models\LoggedHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OwnerLoginSecurity
{
    public function purge(): int
    {
        return LoggedHistory::where('type','owner')->where('date','<=',now()->subDays(7))->delete();
    }

    public function record(Request $request): void
    {
        $user=$request->user();
        if (!$user || $user->type !== 'owner' || $request->session()->get('owner_login_recorded')) return;
        try {
            $this->purge();
            $ua=mb_substr(preg_replace('/[\x00-\x1F\x7F]/','',$request->userAgent() ?? ''),0,512);
            $parser=new \WhichBrowser\Parser($ua);
            $details=['browser'=>$parser->browser->name ?? 'Bilinmiyor','os'=>$parser->os->name ?? 'Bilinmiyor',
                'device'=>$parser->device->type ?? 'Bilinmiyor','user_agent'=>$ua];
            $history=LoggedHistory::create(['user_id'=>$user->id,'parent_id'=>$user->id,'type'=>'owner',
                'ip'=>$request->ip(),'date'=>now(),'details'=>json_encode($details,JSON_UNESCAPED_UNICODE)]);
            $request->session()->put('owner_login_recorded',true);
        } catch (\Throwable $exception) {
            Log::warning('İşletme giriş kaydı oluşturulamadı.',['user_id'=>$user->id,'exception_type'=>get_class($exception)]);
            return;
        }
        try {
            if (filter_var($user->email,FILTER_VALIDATE_EMAIL)) {
                $deviceName=['desktop'=>'Bilgisayar','mobile'=>'Telefon','tablet'=>'Tablet'][$details['device']] ?? $details['device'];
                $device=implode(' / ',[$deviceName,$details['os'],$details['browser']]);
                app(SecurityEmail::class)->send($user,SecurityEmail::LOGIN,['{ip_address}'=>$history->ip,
                    '{device}'=>$device,'{login_time}'=>now()->timezone('Europe/Istanbul')->translatedFormat('d M Y H:i'),
                    '{reset_link}'=>rtrim(config('app.url'),'/').'/forgot-password']);
            }
        } catch (\Throwable $exception) {
            Log::warning('Giriş bildirimi e-postası gönderilemedi.',['user_id'=>$user->id,'exception_type'=>get_class($exception)]);
        }
    }
}
