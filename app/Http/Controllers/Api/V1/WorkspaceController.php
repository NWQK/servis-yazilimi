<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Invoice,Quotation};
use App\Services\{MobileWorkspace,InvoiceBilling,UsageStatistics,CashRegister};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkspaceController extends Controller
{
    private function json(array $data) { return response()->json($data)->header('Cache-Control','private, no-store'); }
    private function owner(Request $r): int { return (int)$r->attributes->get('mobile_owner'); }
    private function authorizeModule(Request $r,string $module): void { abort_unless(MobileWorkspace::allowed($r->user(),$module),403); }
    public function menu(Request $r) { return $this->json(['data'=>MobileWorkspace::menu($r->user())]); }
    private function query(Request $r,string $module) {
        abort_unless(isset(MobileWorkspace::CATALOGS[$module]),404);
        $this->authorizeModule($r,$module);
        $spec=MobileWorkspace::CATALOGS[$module];
        $q=DB::table($spec[3])->where('parent_id',$this->owner($r));
        if ($module==='employees') $q->where('type','employee')->whereNull('client_archived_at');
        if ($module==='purchases') $q->where('created_at','>=',now()->subYears(2));
        return $q;
    }
    private function card(string $module,object $row,bool $detail=false): array {
        $spec=MobileWorkspace::CATALOGS[$module]; $fields=[];
        foreach (($detail?$spec[5]:array_slice($spec[5],0,3,true)) as $key=>$label) {
            $value=$row->$key??null;
            if ($key==='status' && $module==='invoices') $value=[0=>'Ödenmedi',1=>'Kısmi ödeme',2=>'Ödendi'][(int)$value]??'Belirtilmedi';
            if ($key==='is_active') $value=(int)$value===1?'Evet':'Hayır';
            if ($key==='kind') $value=['purchase'=>'Satın alım','adjustment'=>'Düzeltme','sale'=>'Satış','return'=>'İade','counter_sale'=>'Elden satış','transfer_in'=>'Şubeden giriş','transfer_out'=>'Şubeye çıkış'][$value]??$value;
            if ($key==='status' && $module==='quotations') $value=['draft'=>'Taslak','sent'=>'Gönderildi','accepted'=>'Kabul edildi','rejected'=>'Reddedildi','cancelled'=>'İptal edildi'][$value]??$value;
            if ($key==='cash_channel') $value=CashRegister::CHANNELS[$value]??'Belirtilmemiş';
            $fields[]=['label'=>$label,'value'=>$value===null || $value===''?'—':(string)$value];
        }
        $title=(string)($row->{$spec[4]}??$spec[0]);
        if ($module==='invoices') $title='Fatura #'.$title;
        if ($module==='quotations') $title='Teklif #'.$title;
        if ($module==='stock-movements') $title=$fields[0]['value'];
        return ['id'=>(int)$row->id,'title'=>$title,'subtitle'=>$fields[1]['value']??'','fields'=>$fields];
    }
    public function index(Request $r,string $module) {
        $q=$this->query($r,$module);$spec=MobileWorkspace::CATALOGS[$module];
        $v=$r->validate(['q'=>'nullable|string|max:100','page'=>'sometimes|integer|min:1','per_page'=>'sometimes|integer|min:1|max:50']);
        if (!empty($v['q'])) {
            $search=$v['q'];$column=$spec[4];
            $q->where(function($q)use($search,$column,$module){
                $q->where($column,'like','%'.$search.'%');
                if ($module==='suppliers') $q->orWhere('contact_name','like','%'.$search.'%')->orWhere('phone','like','%'.$search.'%');
                if ($module==='products') $q->orWhere('item_code','like','%'.$search.'%');
            });
        }
        $page=$q->select(array_merge(['id'],array_slice(array_keys($spec[5]),0,3)))->orderByDesc('id')->simplePaginate($v['per_page']??20);
        return $this->json(['data'=>collect($page->items())->map(fn($row)=>$this->card($module,$row)+['can_open'=>MobileWorkspace::canShow($r->user(),$module)])->values(),
            'meta'=>['page'=>$page->currentPage(),'has_more'=>$page->hasMorePages(),'read_only'=>true]]);
    }
    public function show(Request $r,string $module,int $id) {
        $spec=MobileWorkspace::CATALOGS[$module]??null;abort_unless($spec,404);
        abort_unless(MobileWorkspace::canShow($r->user(),$module),403);
        $row=$this->query($r,$module)->where('id',$id)->first(array_merge(['id'],array_keys($spec[5])));abort_unless($row,404);
        $card=$this->card($module,$row,true);
        if (in_array($module,['invoices','quotations'],true)) {
            $owner=$this->owner($r);$model=$module==='invoices'?Invoice::class:Quotation::class;
            $relations=['items'=>fn($q)=>$q->where('parent_id',$owner),'types'=>fn($q)=>$q->where('parent_id',$owner)];
            if ($module==='invoices') $relations['payments']=fn($q)=>$q->where('parent_id',$owner);
            $document=$model::where('parent_id',$owner)->with($relations)->findOrFail($id);
            $clientId=$module==='invoices'?$document->client:$document->client_id;
            $customer=DB::table('users')->where('parent_id',$owner)->where('id',$clientId)->value('name');
            $card['fields'][]=['label'=>'Müşteri','value'=>$customer??'—'];
            $total=$module==='invoices'?$document->getInvoiceAllTotalAmount():$document->getQuotationAllTotalAmount();
            $card['fields'][]=['label'=>'Toplam (₺)','value'=>number_format($total,2,',','.')];
            if ($module==='invoices') {
                $due=$document->getInvoiceTotalDueAmount();
                $card['fields'][]=['label'=>'Kalan borç (₺)','value'=>number_format($due,2,',','.')];
                $card['fields'][]=['label'=>'Fatura sürümü','value'=>(string)$document->revision];
                $card['sections']=[['title'=>'Ödemeler (ilk 100)','rows'=>$document->payments->take(100)->map(fn($p)=>[
                    'title'=>(string)$p->payment_date,'value'=>number_format((float)$p->amount,2,',','.').' ₺',
                    'subtitle'=>(string)($p->payment_status??'success')])->values()]];
            }
            $card['sections'][]=['title'=>'Ürün satırları (ilk 100)','rows'=>$document->items->take(100)->map(fn($line)=>[
                'title'=>(string)($line->description?:'Ürün satırı'),'value'=>(string)$line->quantity,
                'subtitle'=>number_format((float)$line->amount,2,',','.').' ₺ / birim'])->values()];
            $card['sections'][]=['title'=>'Servis satırları (ilk 100)','rows'=>$document->types->take(100)->map(fn($line)=>[
                'title'=>(string)($line->note?:'Servis işlemi'),'value'=>number_format((float)$line->rate,2,',','.').' ₺','subtitle'=>''])->values()];
        }
        return $this->json(['data'=>$card]);
    }
    public function account(Request $r) {
        return $this->json(['data'=>['title'=>'Hesabım','fields'=>[
            ['label'=>'Ad soyad','value'=>$r->user()->name],['label'=>'E-posta','value'=>$r->user()->email],
            ['label'=>'Hesap türü','value'=>$r->user()->type==='owner'?'İşletme sahibi':'Personel'],
            ['label'=>'İki aşamalı doğrulama','value'=>$r->user()->twofa_secret?'Etkin':'Etkin değil'],
        ]]]);
    }
    private function businessData(int $owner,?array $stored=null): array {
        $fields=InvoiceBilling::fields(true);$details=array_intersect_key($stored??InvoiceBilling::business($owner),$fields);
        $values=[];foreach ($fields as $key=>$label) $values[$key]=(string)($details[$key]??'');
        return ['values'=>$values,'labels'=>$fields,'version'=>hash('sha256',json_encode($values))];
    }
    public function business(Request $r) {
        $this->authorizeModule($r,'business-settings');$owner=$this->owner($r);
        if ($r->isMethod('PUT')) {
            $v=$r->validate(['expected_version'=>'required|string|size:64','billing'=>'required|array']);
            $billing=InvoiceBilling::validated($r,true);
            $locked=DB::table('invoice_business_profiles')->where('parent_id',$owner)->lockForUpdate()->first(['details']);
            $current=$this->businessData($owner,$locked?json_decode($locked->details,true):null);
            $next=[];foreach ($current['labels'] as $key=>$label) $next[$key]=(string)($billing[$key]??'');
            // Identical retry is safe; an older form cannot replace a newer profile.
            if ($next!==$current['values']) {
                abort_unless(hash_equals($current['version'],$v['expected_version']),409,'İşletme bilgileri değişti. Güncel bilgileri yükleyin.');
                DB::table('invoice_business_profiles')->updateOrInsert(['parent_id'=>$owner],['details'=>json_encode($next)]);
            }
        }
        return $this->json(['data'=>$this->businessData($owner)]);
    }
    public function report(Request $r,string $report) {
        $this->authorizeModule($r,$report);
        $v=$r->validate(['start_date'=>'nullable|date_format:Y-m-d','end_date'=>'nullable|date_format:Y-m-d','date'=>'nullable|date_format:Y-m-d']);
        if ($report==='usage') {
            $start=$v['start_date']??now()->startOfMonth()->toDateString();$end=$v['end_date']??now()->toDateString();
            abort_if($start>$end || \Carbon\CarbonImmutable::parse($start)->diffInDays($end)>366,422,'En fazla bir yıllık geçerli tarih aralığı seçin.');
            $data=app(UsageStatistics::class)->report($this->owner($r),$start,$end);
            $cards=[['id'=>0,'title'=>'Tamamlanan servisler','subtitle'=>(string)$data['completed_services'],'fields'=>[]]];
            foreach ($data['products'] as $row) $cards[]=['id'=>0,'title'=>$row->title,'subtitle'=>'Ürün kullanımı','fields'=>[
                ['label'=>'Miktar','value'=>(string)$row->quantity.' '.$row->unit_name]]];
            foreach ($data['services'] as $row) $cards[]=['id'=>0,'title'=>$row->title??'Silinmiş servis türü','subtitle'=>'Servis türü','fields'=>[
                ['label'=>'Tamamlanan iş sayısı','value'=>(string)$row->uses]]];
        } else {
            abort_unless($report==='cash',404);$date=$v['date']??now()->toDateString();
            $summary=app(CashRegister::class)->summary($this->owner($r),$date);$cards=[];
            foreach (CashRegister::CHANNELS as $channel=>$label) $cards[]=['id'=>0,'title'=>$label,'subtitle'=>$date,'fields'=>[
                ['label'=>'Gelir (₺)','value'=>CashRegister::money($summary['income'][$channel])],
                ['label'=>'Gider (₺)','value'=>CashRegister::money($summary['expense'][$channel])]]];
        }
        return $this->json(['data'=>$cards,'meta'=>['page'=>1,'has_more'=>false,'read_only'=>true]]);
    }
}
