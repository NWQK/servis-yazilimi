<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\{User,Vehicle,Client,Invoice};
use App\Services\{MobileDetailOperation,InvoiceEditing,InvoiceBilling,InvoiceChangePreview,InvoiceJournal,ManualInvoicePayment,VehicleRegistration,VehicleEditing,ChassisNumber,CustomerContact};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Validation\Rule;

class DetailActionController extends Controller {
    public const CUSTOMER=['name'=>'Ad soyad','email'=>'E-posta','phone_number'=>'Telefon','city'=>'İl','state'=>'İlçe','zip_code'=>'Posta kodu','address'=>'Adres','notes'=>'Notlar'];
    public const VEHICLE=['license_plate'=>'Plaka','chassis_no'=>'Şasi numarası','color'=>'Renk','fuel_type'=>'Yakıt','mileage'=>'Kilometre','engine_type'=>'Motor tipi','engine_no'=>'Motor numarası','last_service_date'=>'Son bakım tarihi','next_service_due_date'=>'Sonraki bakım tarihi','insurance_details'=>'Sigorta bilgileri','notes'=>'Notlar'];
    private function owner(Request $r): int { return (int)$r->attributes->get('mobile_owner'); }
    private function permission(Request $r,string $kind,bool $payment=false): void {
        $permission=$payment?'create invoice payment':match($kind){'customers'=>'edit client','vehicles'=>'edit vehicle','invoices'=>'edit invoice',default=>abort(404)};
        abort_unless($r->user()->can($permission),403);
    }
    private function record(Request $r,string $kind,int $id) {
        $q=match($kind){'customers'=>User::where('type','client')->whereNull('client_archived_at'),'vehicles'=>Vehicle::query(),'invoices'=>Invoice::query(),default=>abort(404)};
        return $q->where('parent_id',$this->owner($r))->lockForUpdate()->findOrFail($id);
    }
    private function values($record,string $kind): array {
        if($kind==='customers') {
            $profile=Client::where('parent_id',$record->parent_id)->where('user_id',$record->id)->first();
            return $record->only(['name','email','phone_number'])+($profile?->only(['city','state','zip_code','address','notes'])??[]);
        }
        return $record->only(array_merge(array_keys(self::VEHICLE),['client','type','brand']));
    }
    private function version($record,string $kind): string { return hash('sha256',json_encode($this->values($record,$kind),JSON_THROW_ON_ERROR)); }
    private function json(array $data) { return response()->json($data)->header('Cache-Control','private, no-store'); }
    public function form(Request $r,string $kind,int $id) {
        $this->permission($r,$kind);$record=$this->record($r,$kind,$id);$owner=$this->owner($r);
        if($kind==='invoices') {
            abort_if($record->items()->count()>1000 || $record->types()->count()>1000,422,'Bu fatura mobil düzenleme sınırını aşıyor.');
            $values=$record->only(['client','service','invoice_date','discount_amount']);
            $values['billing']=array_intersect_key($record->customer_details??[],InvoiceBilling::fields());
            $values['invoice_revision']=(int)$record->revision;
            $values['types']=$record->types()->where('parent_id',$owner)->get()->map(fn($line)=>[
                'id'=>$line->id,'service_type'=>$line->service_type,'rate'=>(string)$line->rate,'tax'=>array_values(array_filter(explode(',',$line->tax??''))),'note'=>$line->note])->all();
            $lines=$record->items()->where('parent_id',$owner)->get();
            foreach(['item'=>'item','quantity'=>'quantity','item_id'=>'id','description'=>'description'] as $key=>$column) $values[$key]=$lines->pluck($column)->all();
            $values['tax']=$lines->map(fn($line)=>array_values(array_filter(explode(',',$line->tax??''))))->all();
            return $this->json(['data'=>['values'=>$values,'labels'=>['invoice_date'=>'Fatura tarihi','discount_amount'=>'İndirim (₺)'],
                'billing_labels'=>InvoiceBilling::fields(),'product_sale'=>$record->invoice_kind==='product',
                'item_titles'=>$lines->map(fn($line)=>$line->item_title?:$line->description?:'Ürün #'.$line->item)->all(),
                'choices'=>['client'=>DB::table('users')->where('parent_id',$owner)->where('id',$record->client)->value('name'), 'service'=>'Servis #'.$record->service]]]);
        }
        $choices=[];
        if($kind==='vehicles') {
            $choices=['client'=>DB::table('users')->where('parent_id',$owner)->where('id',$record->client)->value('name'),
                'type'=>DB::table('vehicle_types')->where('parent_id',$owner)->where('id',$record->type)->value('type'),
                'brand'=>DB::table('vehicle_brands')->where('parent_id',$owner)->where('id',$record->brand)->value('name')];
        }
        return $this->json(['data'=>['values'=>$this->values($record,$kind)+['expected_version'=>$this->version($record,$kind)],
            'labels'=>$kind==='customers'?self::CUSTOMER:self::VEHICLE,'choices'=>$choices]]);
    }
    public function paymentForm(Request $r,int $id) {
        $this->permission($r,'invoices',true);$invoice=$this->record($r,'invoices',$id);
        abort_unless(Schema::hasColumn('invoice_payments','payment_status'),503,'Ödeme veritabanı güncellemesi gerekiyor.');
        return $this->json(['data'=>['values'=>['invoice_revision'=>(int)$invoice->revision,'amount'=>'','payment_date'=>now()->toDateString(),'cash_channel'=>'cash','description'=>''],
            'labels'=>['amount'=>'Ödeme tutarı (₺)','payment_date'=>'Ödeme tarihi','description'=>'Açıklama'],
            'due'=>number_format($invoice->getInvoiceTotalDueAmount(),2,',','.'),'can_submit'=>$invoice->getInvoiceTotalDueAmount()>0]]);
    }
    private function validateInvoice(Request $r,Invoice $invoice): void {
        $owner=$this->owner($r);$r->validate(['invoice_revision'=>'required|integer|min:0','invoice_date'=>'required|date_format:Y-m-d',
            'types'=>'sometimes|array|max:1000','item'=>'sometimes|array|max:1000','quantity'=>'sometimes|array|max:1000',
            'types.*.id'=>['nullable','integer','distinct',Rule::exists('invoice_services','id')->where('parent_id',$owner)->where('invoice_id',$invoice->id)]]);
        $r->validate(app(InvoiceEditing::class)->rules($owner,$r,$invoice->invoice_kind==='product'));
    }
    public function preview(Request $r,int $id) {
        $this->permission($r,'invoices');$invoice=$this->record($r,'invoices',$id);$this->validateInvoice($r,$invoice);
        abort_unless((int)$r->invoice_revision===(int)$invoice->revision,409,'Fatura değişti. Güncel kaydı yükleyin.');
        $data=app(InvoiceChangePreview::class)->build($r,$id,'update',function($request,$invoiceId){
            app(InvoiceEditing::class)->update($this->owner($request),$invoiceId,$request,InvoiceBilling::validated($request));
            return response()->json(['ok'=>true]);
        });
        return $this->json(['data'=>$data]);
    }
    public function update(Request $r,string $kind,int $id) {
        $this->permission($r,$kind);
        return $this->json(app(MobileDetailOperation::class)->run($r,$kind,$id,'update',function()use($r,$kind,$id){
            $record=$this->record($r,$kind,$id);$owner=$this->owner($r);
            if($kind==='invoices') {
                $this->validateInvoice($r,$record);abort_unless((int)$r->invoice_revision===(int)$record->revision,409,'Fatura değişti. Güncel kaydı yükleyin.');
                InvoiceChangePreview::verify($r,$record,'invoice.update',true);
                return $this->invoiceWrite($r,$record,'invoice.update',fn()=>app(InvoiceEditing::class)->update($owner,$id,$r,InvoiceBilling::validated($r)));
            }
            $r->validate(['expected_version'=>'required|string|size:64']);
            abort_unless(hash_equals($this->version($record,$kind),$r->expected_version),409,'Kayıt değişti. Güncel bilgileri yükleyin.');
            if($kind==='customers') {
                $r->merge(['phone_number'=>\App\Support\TurkishPhone::national($r->phone_number)]);
                $data=$r->validate(['name'=>'required|string|max:255','email'=>'nullable|email|max:255','phone_number'=>'required|regex:/^5[0-9]{9}$/',
                    'city'=>'nullable|string|max:255','state'=>'nullable|string|max:255','zip_code'=>'nullable|string|max:255','address'=>'nullable|string|max:1000','notes'=>'nullable|string|max:5000']);
                app(CustomerContact::class)->update($record,$data);
            } else {
                $data=$r->only(array_merge(array_keys(self::VEHICLE),['client','type','brand','chassis_change_confirm']));
                $r->replace($data);$r->merge(['status'=>$record->status]);
                ChassisNumber::validateForm($r,$record);
                $r->validate(app(VehicleRegistration::class)->rules($owner,$r));
                $r->validate(['license_plate'=>'required|string|max:20','notes'=>'nullable|string|max:5000']);
                app(VehicleEditing::class)->update($owner,$record,$r);
            }
            return ['id'=>$id];
        }));
    }
    private function invoiceWrite(Request $r,Invoice $invoice,string $action,\Closure $write): array {
        $before=InvoiceJournal::snapshot($invoice);$prior=$r->attributes->get('invoice_operation_guard');$r->attributes->set('invoice_operation_guard',true);
        try { $write(); } finally { if($prior===null)$r->attributes->remove('invoice_operation_guard');else $r->attributes->set('invoice_operation_guard',$prior); }
        $fresh=$invoice->fresh();\App\Services\FinancialAmount::check($fresh->getInvoiceAllTotalAmount());
        $changes=InvoiceJournal::difference($before,InvoiceJournal::snapshot($fresh));
        if($changes) { DB::table('invoices')->where('id',$invoice->id)->increment('revision');InvoiceJournal::record($invoice,$changes,$action,(string)\Illuminate\Support\Str::uuid()); }
        return ['id'=>$invoice->id,'revision'=>(int)$invoice->fresh()->revision];
    }
    public function payment(Request $r,int $id) {
        $this->permission($r,'invoices',true);
        abort_unless(Schema::hasColumn('invoice_payments','payment_status'),503,'Ödeme veritabanı güncellemesi gerekiyor.');
        return $this->json(app(MobileDetailOperation::class)->run($r,'invoices',$id,'payment',function()use($r,$id){
            $invoice=$this->record($r,'invoices',$id);$r->validate(['invoice_revision'=>'required|integer|min:0','payment_date'=>'required|date_format:Y-m-d']);
            abort_unless((int)$r->invoice_revision===(int)$invoice->revision,409,'Fatura değişti. Güncel borcu kontrol edin.');
            $result=$this->invoiceWrite($r,$invoice,'invoice.payment.store',fn()=>app(ManualInvoicePayment::class)->create($invoice,$r));
            DB::afterCommit(fn()=>app(ManualInvoicePayment::class)->notify($invoice->fresh()));
            return $result;
        }));
    }
    public function lookup(Request $r,string $kind) {
        $vehicle=str_starts_with($kind,'vehicle-');$this->permission($r,$vehicle?'vehicles':'invoices');$owner=$this->owner($r);
        $v=$r->validate(['q'=>'nullable|string|max:100','page'=>'sometimes|integer|min:1','type'=>'sometimes|integer','client'=>'sometimes|integer']);
        [$table,$column]=match($kind){'vehicle-customers','invoice-customers'=>['users','name'],'vehicle-types'=>['vehicle_types','type'],
            'vehicle-brands'=>['vehicle_brands','name'],'invoice-products'=>['items','title'],'invoice-types'=>['service_types','type'],
            'invoice-taxes'=>['taxes','title'],'invoice-services'=>['services','service_id'],default=>abort(404)};
        $q=DB::table($table)->where('parent_id',$owner);
        if($table==='users')$q->where('type','client')->whereNull('client_archived_at');
        if($table==='vehicle_brands')$q->where('type',$v['type']??0);
        if($table==='services')$q->where('client',$v['client']??0);
        if(!empty($v['q']))$q->where($column,'like','%'.$v['q'].'%');
        $page=$q->orderBy($column)->orderBy('id')->simplePaginate(20,['id',$column]);
        return $this->json(['data'=>collect($page->items())->map(fn($row)=>['id'=>$row->id,'label'=>(string)$row->$column])->values(),
            'meta'=>['page'=>$page->currentPage(),'has_more'=>$page->hasMorePages()]]);
    }
}
