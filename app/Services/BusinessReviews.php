<?php
namespace App\Services;

use App\Mail\Common;
use App\Models\{BusinessReview, User, Notification, AppointmentSmsSetting};
use App\Support\TurkishPhone;
use Illuminate\Support\Facades\{DB, Log, Mail, Schema};

class BusinessReviews
{
    public const MODULE = 'platform_review_invitation';
    public static function definition(): array {
        return ['module'=>self::MODULE, 'name'=>'Müşteriye işletme değerlendirme bağlantısı',
            'subject'=>'{company_name} işletmesini değerlendirin — sanayirandevu.com',
            'templete'=>'<p>Merhaba {client_name},</p><p><strong>{company_name}</strong> işletmesindeki müşteri kaydınız oluşturuldu.</p><p>Deneyiminizi 1–5 yıldız arasında puanlayabilirsiniz. Henüz hizmet almadıysanız hizmetiniz tamamlandıktan sonra değerlendirme yapabilirsiniz.</p><p><a href="{review_link}" style="display:inline-block;padding:14px 22px;background:#e32228;color:#fff;text-decoration:none;border-radius:10px">İşletmeyi değerlendir</a></p><p>Her müşteri bir işletmeyi bir kez değerlendirebilir. Bu bağlantı size özeldir; paylaşmayın.</p>',
            'short_code'=>['{client_name}','{company_name}','{review_link}']];
    }
    public static function template(User $admin): Notification {
        $d=self::definition();
        return Notification::firstOrCreate(['parent_id'=>$admin->id,'module'=>self::MODULE], [
            'name'=>$d['name'],'subject'=>$d['subject'],'message'=>$d['templete'],'short_code'=>json_encode($d['short_code']),
            'enabled_email'=>1,'enabled_sms'=>0,'sms_message'=>'']);
    }
    public function invitation(User $customer): ?BusinessReview {
        if ($customer->type !== 'client' || !User::where('type','owner')->whereKey($customer->parent_id)->exists()) return null;
        $existing=BusinessReview::where('owner_id',$customer->parent_id)->where('customer_id',$customer->id)->first();
        if ($existing) return $existing;
        $phone=TurkishPhone::mobile($customer->phone_number);
        $identity=$phone ? 'phone:'.$phone : (filter_var($customer->email,FILTER_VALIDATE_EMAIL) ? 'email:'.mb_strtolower(trim($customer->email)) : 'user:'.$customer->id);
        $hash=hash('sha256',$identity);
        // The unique business/identity key also covers duplicate customer records and multiple vehicles.
        DB::table('business_reviews')->insertOrIgnore(['owner_id'=>$customer->parent_id,'customer_id'=>$customer->id,
            'identity_hash'=>$hash,'token'=>bin2hex(random_bytes(32)),'created_at'=>now(),'updated_at'=>now()]);
        return BusinessReview::where('owner_id',$customer->parent_id)->where('identity_hash',$hash)->firstOrFail();
    }
    public static function company(int $ownerId): string {
        return (DB::table('settings')->where('parent_id',$ownerId)->where('name','company_name')->value('value')) ?: (User::find($ownerId)?->name ?? 'İşletme');
    }
    public function registered(int $customerId): void {
        try {
            if (!Schema::hasTable('business_reviews')) return;
            $customer=User::where('type','client')->whereNull('client_archived_at')->find($customerId);
            if (!$customer || !($review=$this->invitation($customer)) || $review->rating) return;
            // An existing identity must not receive another invitation through a duplicate record.
            if ((int)$review->customer_id !== (int)$customer->id) return;
            $values=['{client_name}'=>$customer->name,'{company_name}'=>self::company($review->owner_id),'{review_link}'=>$review->publicUrl()];
            $this->sms($review,$customer,$values);
            $this->email($review,$customer,$values);
        } catch (\Throwable $e) {
            Log::warning('Değerlendirme bağlantısı hazırlanamadı.', ['customer_id'=>$customerId,'exception_type'=>get_class($e)]);
        }
    }
    private function claim(BusinessReview $review, string $channel): bool {
        return (bool) BusinessReview::whereKey($review->id)->where($channel.'_status','pending')->update([$channel.'_status'=>'sending']);
    }
    private function sms(BusinessReview $review, User $customer, array $values): void {
        try {
            $settings=AppointmentSmsSetting::central();
            if (!$settings->enabled || !$settings->review_sms_enabled) return;
            $phone=TurkishPhone::mobile($customer->phone_number);
            if (!$phone || !$this->claim($review,'sms')) return;
            $body=$settings->renderMessage($settings->review_template,['{isletme}'=>$values['{company_name}'],'{link}'=>$review->publicUrl()]);
            $result=app(AppointmentSmsGateway::class)->send($phone,$body);
            $review->update(['sms_status'=>$result['status'],'sms_error'=>$result['error_code'] ?? null,
                'provider_sid'=>$result['provider_sid'] ?? null,'sms_sent_at'=>$result['status']==='accepted' ? now() : null]);
        } catch (\Throwable $e) {
            BusinessReview::whereKey($review->id)->where('sms_status','sending')->update(['sms_status'=>'unknown','sms_error'=>'delivery_unknown']);
            Log::warning('Değerlendirme SMS gönderimi belirsiz.', ['review_id'=>$review->id,'exception_type'=>get_class($e)]);
        }
    }
    private function email(BusinessReview $review, User $customer, array $values): void {
        try {
            if (!CentralEmail::enabled() || !filter_var($customer->email,FILTER_VALIDATE_EMAIL) || !($admin=CentralEmail::administrator())) return;
            $template=self::template($admin);
            if (!$template->enabled_email || !$this->claim($review,'email')) return;
            $smtp=app(TenantMailSettings::class)->apply($admin->id);
            $smtp['FROM_NAME']='sanayirandevu.com';
            Mail::to($customer->email)->send(new Common(['module'=>self::MODULE,
                'subject'=>str_replace(["\r","\n"],'',strtr($template->subject,$values)),
                'message'=>strtr($template->message,array_map(fn($v)=>e($v),$values)),
                'settings'=>$smtp+['company_name'=>'sanayirandevu.com','brand_logo'=>rtrim(config('app.url'),'/').'/images/brand/sanayirandevu-email-logo.png']]));
            $review->update(['email_status'=>'accepted','email_sent_at'=>now(),'email_error'=>null]);
        } catch (\Throwable $e) {
            BusinessReview::whereKey($review->id)->where('email_status','sending')->update(['email_status'=>'unknown','email_error'=>'delivery_unknown']);
            Log::warning('Değerlendirme e-postası gönderilemedi.', ['review_id'=>$review->id,'exception_type'=>get_class($e)]);
        }
    }
}
