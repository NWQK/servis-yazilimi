<?php
namespace App\Services;

use App\Mail\Common;
use App\Models\{Notification, User};
use Illuminate\Support\Facades\Mail;

class SecurityEmail
{
    public const LOGIN = 'platform_owner_login';
    public const RESET = 'platform_password_reset';

    public static function definitions(): array
    {
        return [self::LOGIN => ['module'=>self::LOGIN, 'name'=>'İşletme hesabına giriş bildirimi',
            'subject'=>'Hesabınıza giriş yapıldı — sanayirandevu.com',
            'templete'=>'<p>Merhaba {user_name},</p><p>Hesabınıza <strong>{login_time}</strong> tarihinde giriş yapıldı.</p><p>IP adresi: <strong>{ip_address}</strong><br>Cihaz / tarayıcı: {device}</p><p>Bu giriş size ait değilse şifrenizi hemen güncelleyin.</p><p><a href="{reset_link}">Şifremi güncelle</a></p>',
            'short_code'=>['{user_name}','{login_time}','{ip_address}','{device}','{reset_link}']],
            self::RESET => ['module'=>self::RESET, 'name'=>'Şifre sıfırlama',
            'subject'=>'Şifrenizi sıfırlayın — sanayirandevu.com',
            'templete'=>'<p>Merhaba {user_name},</p><p>Sanayi Randevu hesabınız için şifre sıfırlama talebi alındı.</p><p><a href="{reset_link}" style="display:inline-block;padding:12px 20px;background:#e32228;color:#fff;text-decoration:none;border-radius:8px">Şifremi sıfırla</a></p><p>Bu bağlantı {expire_minutes} dakika geçerlidir. Talebi siz oluşturmadıysanız bu e-postayı dikkate almayabilirsiniz; şifreniz değişmez.</p>',
            'short_code'=>['{user_name}','{reset_link}','{expire_minutes}']]];
    }

    public static function template(User $admin, string $module): Notification
    {
        $definition=self::definitions()[$module];
        return Notification::firstOrCreate(['parent_id'=>$admin->id,'module'=>$module],[
            'name'=>$definition['name'],'subject'=>$definition['subject'],'message'=>$definition['templete'],
            'short_code'=>json_encode($definition['short_code']),'enabled_email'=>1,'enabled_sms'=>0,'sms_message'=>'',
        ]);
    }

    public function send(User $user, string $module, array $values): void
    {
        if (!CentralEmail::enabled()) return;
        $admin=User::where('type','super admin')->orderBy('id')->firstOrFail();
        $template=self::template($admin,$module);
        // Password recovery must remain available even if login notifications are switched off.
        if ($module === self::LOGIN && !$template->enabled_email) return;
        $smtp=app(TenantMailSettings::class)->apply($admin->id);
        $smtp['FROM_NAME']='sanayirandevu.com';
        $values['{user_name}']=$user->name;
        Mail::to($user->email)->send(new Common(['module'=>$module,
            'subject'=>str_replace(["\r","\n"],'',strtr($template->subject,$values)),
            'message'=>strtr($template->message,array_map(fn($value)=>e($value),$values)),
            'settings'=>$smtp+['company_name'=>'sanayirandevu.com',
                'brand_logo'=>rtrim(config('app.url'),'/').'/images/brand/sanayirandevu-email-logo.png']]));
    }

    public function passwordReset(User $user, string $token): void
    {
        $url=rtrim(config('app.url'),'/').'/reset-password/'.rawurlencode($token).'?'.http_build_query(['email'=>$user->email]);
        $this->send($user,self::RESET,['{reset_link}'=>$url,
            '{expire_minutes}'=>(string)config('auth.passwords.'.config('auth.defaults.passwords').'.expire',60)]);
    }
}
