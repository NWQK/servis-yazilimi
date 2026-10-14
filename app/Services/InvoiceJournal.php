<?php
namespace App\Services;
use App\Models\Invoice;
use Illuminate\Support\Facades\{Crypt,DB};
use Illuminate\Support\Str;
class InvoiceJournal
{
    public static function display(string $field,$value): string
    {
        if($value===null || $value==='') return '—';
        if($field==='status') return ['Ödenmedi','Kısmi ödendi','Ödendi'][(int)$value] ?? (string)$value;
        if(str_ends_with($field,'.payment_status')) return ['pending'=>'Onay bekliyor','success'=>'Onaylı','succeeded'=>'Onaylı','reject'=>'Reddedildi'][$value] ?? (string)$value;
        return (string)$value;
    }
    public static function label(string $field): string
    {
        $labels=['status'=>'Ödeme durumu','client'=>'Müşteri','service'=>'Servis','invoice_date'=>'Fatura tarihi','discount_amount'=>'İndirim','external_labor_amount'=>'Harici işçilik','external_labor_tax_rate'=>'İşçilik vergisi','customer_details'=>'Müşteri bilgileri','items'=>'Ürün','types'=>'Hizmet','payments'=>'Ödeme','item'=>'Ürün kimliği','item_title'=>'Ürün adı','quantity'=>'Adet','amount'=>'Tutar / birim fiyat','tax'=>'Vergi','description'=>'Açıklama','payment_date'=>'Ödeme tarihi','payment_status'=>'Ödeme sonucu','rate'=>'Hizmet tutarı','note'=>'Not','name'=>'Ad / unvan','phone'=>'Telefon','email'=>'E-posta','address'=>'Adres','service_type'=>'Hizmet türü','tax_number'=>'Vergi / kimlik numarası','tax_office'=>'Vergi dairesi','city'=>'İl','state'=>'İlçe','country'=>'Ülke','zip_code'=>'Posta kodu'];
        return implode(' · ',array_map(fn($part)=>$labels[$part]??$part,explode('.',$field)));
    }
    public static function token(): string
    {
        return Crypt::encryptString(json_encode(['key'=>(string)Str::uuid(),'actor'=>auth()->id(),'tenant'=>parentId(),'expires'=>now()->addDays(7)->timestamp]));
    }
    public static function snapshot(?Invoice $invoice): array
    {
        if (!$invoice) return [];
        $data=$invoice->only(['status','client','service','invoice_date','discount_amount','external_labor_amount','external_labor_tax_rate','customer_details']);
        foreach(['items'=>['item','item_title','quantity','amount','tax','description'],'types'=>['service_type','rate','tax','note'],'payments'=>['amount','payment_date','description','payment_status']] as $relation=>$columns) {
            $data[$relation]=$invoice->$relation()->orderBy('id')->get()->mapWithKeys(fn($row)=>[$row->id=>$row->only($columns)])->all();
        }
        return $data;
    }
    public static function difference(array $before,array $after,string $prefix=''): array
    {
        $changes=[];
        foreach(array_unique(array_merge(array_keys($before),array_keys($after))) as $key) {
            $path=$prefix.$key; $a=$before[$key]??null; $b=$after[$key]??null;
            if (is_array($a)||is_array($b)) $changes+=self::difference(is_array($a)?$a:[],is_array($b)?$b:[],$path.'.');
            elseif ((string)$a !== (string)$b) $changes[$path]=['before'=>$a,'after'=>$b];
        }
        return $changes;
    }
    public static function record(Invoice $invoice,array $changes,string $action,?string $key=null,?string $hash=null,?string $redirect=null): void
    {
        if (!$changes) return;
        DB::table('invoice_changes')->insert(['parent_id'=>$invoice->parent_id,'invoice_id'=>$invoice->id,'invoice_number'=>$invoice->invoice_id,
            'actor_id'=>auth()->id(),'actor_name'=>auth()->user()?->name,'action'=>$action,'changes'=>json_encode($changes,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),
            'operation_key'=>$key,'request_hash'=>$hash,'redirect_to'=>$redirect,'created_at'=>now()]);
    }
}
