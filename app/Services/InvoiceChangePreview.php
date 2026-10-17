<?php
namespace App\Services;
use App\Models\{Invoice,Item};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Crypt};
use Illuminate\Validation\ValidationException;
class InvoiceChangePreview {
    public static function bodyHash(Request $request): string {
        $data=$request->except(['_token','_method','operation_token','preview_token','_branch']);
        $sort=function(&$array)use(&$sort){ksort($array);foreach($array as &$value)if(is_array($value))$sort($value);};$sort($data);
        return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));
    }
    public static function state(Invoice $invoice,Request $request): string {
        $ids=array_values(array_unique(array_merge($invoice->items()->pluck('item')->all(),(array)$request->input('item',[]))));
        $items=DB::table('items')->where('parent_id',$invoice->parent_id)->whereIn('id',$ids)->orderBy('id')->get(['id','inventory_key','quantity','sales_price','taxs','units'])->all();
        $taxes=DB::table('taxes')->where('parent_id',$invoice->parent_id)->orderBy('id')->get(['id','rate'])->all();
        $services=DB::table('services')->where('parent_id',$invoice->parent_id)->whereIn('id',[$invoice->service,$request->input('service')])->get(['id','external_labor_amount','external_labor_tax_rate'])->all();
        return hash('sha256',json_encode([InvoiceJournal::snapshot($invoice->fresh()),$items,$taxes,$services],JSON_THROW_ON_ERROR));
    }
    private function stock(int $owner,array $ids,array $keys): array {
        return Item::where('parent_id',$owner)->where(fn($q)=>$q->whereIn('id',$ids)->orWhereIn('inventory_key',$keys))
            ->get(['id','inventory_key','title','quantity'])->mapWithKeys(fn($item)=>[($item->inventory_key?:'id:'.$item->id)=>['id'=>$item->id,'quantity'=>(int)$item->quantity,'title'=>$item->title]])->all();
    }
    public function build(Request $request,int $id,string $action): array {
        abort_unless(in_array($action,['update','delete'],true) && auth()->user()->can($action==='update'?'edit invoice':'delete invoice'),403);
        return app(InventoryAccounting::class)->transaction(parentId(),function()use($request,$id,$action){
            $invoice=Invoice::where('parent_id',parentId())->lockForUpdate()->findOrFail($id);
            $request->validate(['invoice_revision'=>'required|integer','item'=>'sometimes|nullable|array|max:1000','item.*'=>'nullable|integer|min:1']);
            if((int)$request->invoice_revision!==(int)$invoice->revision)throw ValidationException::withMessages(['invoice_revision'=>'Fatura değişmiş. Sayfayı yenileyin.']);
            $beforeTotal=$invoice->getInvoiceAllTotalAmount();$beforePaid=$invoice->payments()->accounted()->sum('amount');
            $lines=$invoice->items()->get();
            $stockIds=array_values(array_unique(array_merge($lines->pluck('item')->all(),(array)$request->input('item',[]))));
            $stockKeys=$lines->map(fn($line)=>$line->item_snapshot['inventory_key']??null)->filter()->all();
            $fingerprint=self::state($invoice,$request);$beforeStock=$this->stock(parentId(),$stockIds,$stockKeys);$session=$request->session()->all();$level=DB::transactionLevel();
            $oldGuard=$request->attributes->get('invoice_operation_guard');$request->attributes->set('invoice_operation_guard',true);
            DB::beginTransaction();
            try {
                $request->session()->forget('error');
                $controller=app(\App\Http\Controllers\InvoiceController::class);
                $response=$action==='update'?$controller->update($request,encrypt($id)):$controller->destroy($request,$id);
                if($request->session()->has('error'))throw ValidationException::withMessages(['preview'=>$request->session()->get('error')]);
                abort_if($response->getStatusCode()>=400,422);
                $after=Invoice::where('parent_id',parentId())->find($id);$afterTotal=$after?->getInvoiceAllTotalAmount()??0;
                $afterPaid=$after?->payments()->accounted()->sum('amount')??0;$afterStock=$this->stock(parentId(),$stockIds,$stockKeys);$stocks=[];
                FinancialAmount::check($afterTotal);
                foreach($afterStock as $key=>$entry)if(isset($beforeStock['id:'.$entry['id']]) && $key!=='id:'.$entry['id']){unset($afterStock[$key]);$afterStock['id:'.$entry['id']]=$entry;}
                foreach(array_unique(array_merge(array_keys($beforeStock),array_keys($afterStock))) as $key){
                    $delta=($afterStock[$key]['quantity']??0)-($beforeStock[$key]['quantity']??0);
                    if($delta)$stocks[]=['title'=>$afterStock[$key]['title']??$beforeStock[$key]['title'],'delta'=>$delta];
                }
                $summary=['before_total'=>round($beforeTotal,2),'after_total'=>round($afterTotal,2),'before_paid'=>round($beforePaid,2),'after_paid'=>round($afterPaid,2),
                    'payment_adjustment'=>$action==='delete'?0:round($afterPaid-$beforePaid,2),'stocks'=>$stocks,'deleting'=>$action==='delete'];
            } finally {
                DB::rollBack($level);$request->session()->flush();$request->session()->put($session);
                if($oldGuard===null)$request->attributes->remove('invoice_operation_guard');else $request->attributes->set('invoice_operation_guard',$oldGuard);
            }
            $summary['token']=Crypt::encryptString(json_encode(['actor'=>auth()->id(),'owner'=>parentId(),'invoice'=>$id,'revision'=>(int)$invoice->revision,
                'action'=>$action,'body'=>self::bodyHash($request),'state'=>$fingerprint,'expires'=>now()->addMinutes(10)->timestamp]));return $summary;
        });
    }
    public static function verify(Request $request,Invoice $invoice,string $action): void {
        if(!config('invoice_changes.require_preview',true) || !in_array($action,['invoice.update','invoice.destroy'],true))return;
        try{$token=json_decode(Crypt::decryptString((string)$request->input('preview_token')),true,32,JSON_THROW_ON_ERROR);}catch(\Throwable $e){$token=[];}
        $valid=($token['actor']??null)===auth()->id() && ($token['owner']??null)===parentId() && ($token['invoice']??null)===$invoice->id
            && ($token['revision']??null)===(int)$invoice->revision && ($token['expires']??0)>=now()->timestamp && ($token['action']??null)===($action==='invoice.update'?'update':'delete')
            && hash_equals($token['body']??'',self::bodyHash($request)) && hash_equals($token['state']??'',self::state($invoice,$request));
        if(!$valid)throw ValidationException::withMessages(['preview_token'=>'İşlem özeti eksik veya kayıtlar değişti. Güncel özeti görüntüleyip tekrar onaylayın.']);
    }
}
