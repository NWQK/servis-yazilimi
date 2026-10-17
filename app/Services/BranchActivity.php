<?php
namespace App\Services;
use Illuminate\Support\Facades\{DB,Schema};

class BranchActivity
{
    public static function record(int $group, ?int $owner, string $action, array $details=[], ?string $route=null): void
    {
        if (!Schema::hasTable('branch_activity_logs')) return;
        // Deliberately no raw form, IP, e-mail, password, request token or customer details.
        $allowed=array_intersect_key($details,array_flip(['target_owner_id','user_id','modules','can_write','can_delete','transfer_id','quantity','status','active','record_id']));
        DB::table('branch_activity_logs')->insert(['group_id'=>$group,'owner_id'=>$owner,
            'actor_id'=>auth()->id(),'actor_name'=>mb_substr(auth()->user()?->name ?? 'Sistem',0,191),
            'action'=>$action,'route_name'=>$route,'details'=>json_encode($allowed,JSON_THROW_ON_ERROR),'created_at'=>now()]);
    }
    public static function labels(): array
    {
        return ['group.created'=>'Merkez oluşturuldu','branch.attached'=>'Şube bağlandı','branch.detached'=>'Şube ayrıldı',
            'group.status'=>'Merkez erişimi değişti','staff.granted'=>'Personel erişimi güncellendi','staff.revoked'=>'Personel erişimi kaldırıldı',
            'transfer.sent'=>'Stok transferi gönderildi','transfer.received'=>'Stok transferi teslim alındı','transfer.cancelled'=>'Stok transferi iptal / iade edildi',
            'staff.departed'=>'Personel ayrılışı kaydedildi','referral.sent'=>'Araç yönlendirmesi gönderildi','referral.accepted'=>'Araç yönlendirmesi kabul edildi',
            'referral.rejected'=>'Araç yönlendirmesi reddedildi','referral.cancelled'=>'Araç yönlendirmesi iptal edildi','operation'=>'Şube işlemi'];
    }
    public static function operationLabel(?string $route): string
    {
        $parts=explode('.',$route ?? '');
        $modules=['client'=>'Müşteri','vehicle'=>'Araç','vehicle-type'=>'Araç markası','vehicle-brand'=>'Araç modeli',
            'service'=>'Servis','service-type'=>'Servis türü','item'=>'Ürün / stok','supplier'=>'Tedarikçi','unit'=>'Ürün birimi',
            'tax'=>'Vergi','invoice'=>'Fatura','expense'=>'Gider','appointments'=>'Randevu','employee'=>'Personel',
            'setting'=>'İşletme ayarları','business-setup'=>'İşletme kurulumu','contact'=>'İletişim rehberi','note'=>'Not'];
        return ($modules[$parts[0] ?? ''] ?? 'Kayıt').' · '.(str_contains($route ?? '','destroy')?'silme':(str_contains($route ?? '','store')?'ekleme':'güncelleme'));
    }
}
