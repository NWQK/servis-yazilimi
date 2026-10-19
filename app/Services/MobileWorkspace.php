<?php
namespace App\Services;

/** Fixed native destinations; never exposes arbitrary tables, URLs or setting keys. */
class MobileWorkspace
{
    public const CATALOGS = [
        'products'=>['Ürün ve stok listesi','Ürünler ve stok','manage item','items','title',['title'=>'Ürün','item_code'=>'Ürün kodu','quantity'=>'Stok miktarı','sales_price'=>'Satış fiyatı','purchase_price'=>'Alış fiyatı','notes'=>'Notlar']],
        'suppliers'=>['Tedarikçiler','Ürünler ve stok','manage item','suppliers','company_name',['company_name'=>'Firma','contact_name'=>'Yetkili','phone'=>'Telefon','email'=>'E-posta']],
        'purchases'=>['Satın alım geçmişi','Ürünler ve stok','manage item','item_purchases','item_title',['item_title'=>'Ürün','supplier_name'=>'Tedarikçi','quantity'=>'Miktar','unit_name'=>'Birim','unit_price'=>'Birim fiyat','amount'=>'Tutar','purchase_date'=>'Tarih','notes'=>'Notlar']],
        'stock-movements'=>['Stok hareket geçmişi','Ürünler ve stok','manage item','stock_movements','kind',['kind'=>'Hareket','quantity'=>'Miktar','created_at'=>'Tarih','unit_price'=>'Birim fiyat']],
        'categories'=>['Ürün kategorileri','Ürünler ve stok','manage item','item_categories','name',['name'=>'Kategori']],
        'units'=>['Ürün birimleri','Ürünler ve stok','manage unit','units','unit',['unit'=>'Birim']],
        'service-types'=>['Servis türleri','Servisler','manage service type','service_types','type',['type'=>'Servis türü','rate'=>'Ücret','note'=>'Not']],
        'vehicle-types'=>['Araç markaları','Araçlar','manage vehicle type','vehicle_types','type',['type'=>'Marka']],
        'vehicle-brands'=>['Araç modelleri','Araçlar','manage vehicle brand','vehicle_brands','name',['name'=>'Model']],
        'expenses'=>['Gider kayıtları','Finans','manage expense','expenses','title',['title'=>'Gider','amount'=>'Tutar','date'=>'Tarih','cash_channel'=>'Ödeme kanalı','notes'=>'Notlar']],
        'taxes'=>['Vergi oranları','Finans','manage tax','taxes','title',['title'=>'Vergi','rate'=>'Oran (%)']],
        'employees'=>['Personel listesi','İşletme yönetimi','manage employee','users','name',['name'=>'Ad soyad','email'=>'E-posta','phone_number'=>'Telefon','is_active'=>'Aktif']],
        'invoices'=>['Fatura listesi','Faturalar','manage invoice','invoices','invoice_id',['invoice_id'=>'Fatura no','invoice_date'=>'Tarih','status'=>'Durum']],
        'quotations'=>['Teklifler','Teklifler','manage quotation','quotations','quotation_id',['quotation_id'=>'Teklif no','quotation_date'=>'Tarih','status'=>'Durum','notes'=>'Notlar']],
    ];

    public static function allowed($user,string $id): bool
    {
        if (isset(self::CATALOGS[$id])) return $user->can(self::CATALOGS[$id][2]);
        return match($id) {
            'usage'=>UsageStatistics::accessible(),
            'cash'=>$user->can('manage expense'),
            'business-settings'=>$user->can('manage company settings'),
            'account','devices'=>true,
            default=>false,
        };
    }
    public static function canShow($user,string $id): bool {
        return match($id) {
            'invoices'=>$user->can('show invoice'),
            'products'=>$user->can('show item'),
            'employees'=>$user->can('show employee'),
            default=>self::allowed($user,$id),
        };
    }

    public static function menu($user): array
    {
        $entries=[];
        foreach (self::CATALOGS as $id=>$spec) if (self::allowed($user,$id)) {
            $entries[]=['id'=>$id,'label'=>$spec[0],'group'=>$spec[1],'kind'=>'catalog'];
        }
        foreach ([['usage','Ürün ve servis istatistikleri','Raporlar','report'],['cash','Gün sonu kasa','Raporlar','report'],
            ['business-settings','İşletme fatura bilgileri','Ayarlar','form'],['account','Hesabım','Ayarlar','account'],
            ['devices','Cihaz oturumları','Ayarlar','devices']] as [$id,$label,$group,$kind]) {
            if (self::allowed($user,$id)) $entries[] = compact('id','label','group','kind');
        }
        return $entries;
    }
}
