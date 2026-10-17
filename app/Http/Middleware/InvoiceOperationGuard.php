<?php
namespace App\Http\Middleware;
use App\Models\{Invoice,InvoiceItem,InvoicePayment,User};
use App\Services\{InvoiceJournal,InventoryAccounting};
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Crypt,DB};
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Exceptions\HttpResponseException;
class InvoiceOperationGuard
{
    private const ACTIONS=[
        'invoice.store'=>'create invoice','invoice.update'=>'edit invoice','invoice.destroy'=>'delete invoice',
        'invoice.payment.store'=>'create invoice payment','invoice.payment.destroy'=>'delete invoice payment',
        'invoice.item.store'=>'create invoice','invoice.item.line.destroy'=>'delete invoice','invoice.item.destroy'=>'edit invoice',
        'invoice.billing.update'=>'edit invoice','invoice.status.change'=>'edit invoice',
        'invoice.banktransfer.payment'=>'create invoice payment','invoice.bank.transfer.action'=>'edit invoice',
    ];
    public function handle(Request $request,Closure $next)
    {
        $action=$request->route()?->getName();
        if (!isset(self::ACTIONS[$action]) || !$request->user()) return $next($request);
        abort_unless($request->user()->type!=='client' && $request->user()->can(self::ACTIONS[$action]),403);
        if(!$request->filled('operation_token')) $this->invalid($request,'operation_token','Bu işlem formu güncel değil. Sayfayı yenileyip tekrar deneyin.');
        $request->validate(['operation_token'=>'required|string|max:2000']);
        try { $token=json_decode(Crypt::decryptString($request->operation_token),true,512,JSON_THROW_ON_ERROR); }
        catch(\Throwable $e){$this->invalid($request,'operation_token','İşlem formu geçersiz. Sayfayı yenileyin.');}
        if (($token['actor']??null)!==auth()->id() || ($token['tenant']??null)!==parentId() || ($token['expires']??0)<now()->timestamp) $this->invalid($request,'operation_token','İşlem formunun süresi doldu. Sayfayı yenileyin.');
        $payload=$request->except(['_token','operation_token','preview_token','_branch']); ksort($payload);
        foreach(\Illuminate\Support\Arr::dot($request->allFiles()) as $key=>$file) $payload['file_hash.'.$key]=$file->isValid() ? hash_file('sha256',$file->getRealPath()) : 'invalid';
        $hash=hash('sha256',json_encode([$action,array_map(fn($p)=>$p instanceof \Illuminate\Database\Eloquent\Model ? $p->getKey() : $p,$request->route()->parameters()),$payload],JSON_THROW_ON_ERROR));
        try { return app(InventoryAccounting::class)->transaction(parentId(),function() use($request,$next,$action,$token,$hash){
            $prior=DB::table('invoice_changes')->where('parent_id',parentId())->where('operation_key',$token['key'])->lockForUpdate()->first();
            if($prior){
                if(!hash_equals($prior->request_hash,$hash)) $this->invalid($request,'operation_token','Bu işlem farklı bilgilerle daha önce kaydedilmiş. Sayfayı yenileyin.');
                return redirect($prior->redirect_to ?: route('invoice.index'))->with('success','İşlem zaten kaydedilmişti; ikinci kez uygulanmadı.');
            }
            $id=$request->route('id')??$request->route('invoice');
            if($id instanceof Invoice) $id=$id->id;
            if(in_array($action,['invoice.update','invoice.banktransfer.payment'])) $id=decrypt($id);
            if($action==='invoice.item.destroy') $id=InvoiceItem::where('parent_id',parentId())->findOrFail($request->id)->invoice_id;
            if($action==='invoice.bank.transfer.action') $id=InvoicePayment::where('parent_id',parentId())->findOrFail($id)->invoice_id;
            $invoice=$action==='invoice.store' ? null : Invoice::where('parent_id',parentId())->lockForUpdate()->find($id);
            if($action!=='invoice.store' && !$invoice){
                if(DB::table('invoice_changes')->where('parent_id',parentId())->where('invoice_id',$id)->where('action','invoice.destroy')->exists()) return redirect()->route('invoice.index')->with('error','Bu fatura başka bir işlemde silinmiş. Liste güncellendi.');
                abort(404);
            }
            if($invoice){
                $request->validate(['invoice_revision'=>'required|integer|min:0']);
                if((int)$request->invoice_revision!==(int)$invoice->revision) $this->invalid($request,'invoice_revision','Bu fatura başka bir işlem veya personel tarafından değiştirildi. Güncel bilgileri görmek için sayfayı yenileyin.');
            }
            $before=InvoiceJournal::snapshot($invoice);
            if($invoice)\App\Services\InvoiceChangePreview::verify($request,$invoice,$action);
            $request->attributes->set('invoice_operation_guard',true);
            try { $response=$next($request); }
            finally { $request->attributes->remove('invoice_operation_guard'); }
            if($response->getStatusCode()>=400) throw new HttpResponseException($response);
            if(in_array('errors',$request->session()->get('_flash.new',[]),true) && $request->session()->has('errors')){
                $message=$request->session()->get('errors')->getBag('default')->first();
                $request->session()->flash('error',str_replace(['\\',"\r","\n"],['\\\\',' ',' '],e($message)));
            }
            if(!$invoice) $invoice=Invoice::where('parent_id',parentId())->find($request->attributes->get('created_invoice_id'));
            if(!$invoice) return $response;
            $fresh=Invoice::where('parent_id',parentId())->find($invoice->id);
            if($fresh)\App\Services\FinancialAmount::check($fresh->getInvoiceAllTotalAmount());
            $changes=InvoiceJournal::difference($before,InvoiceJournal::snapshot($fresh));
            if($changes){
                if($fresh) DB::table('invoices')->where('id',$fresh->id)->increment('revision');
                InvoiceJournal::record($invoice,$changes,$action,$token['key'],$hash,$response->headers->get('Location'));
            }
            return $response;
        }); } catch(HttpResponseException $e) { return $e->getResponse(); }
    }
    private function invalid(Request $request,string $field,string $message): void
    {
        $request->session()->flash('error',$message);
        throw ValidationException::withMessages([$field=>$message]);
    }
}
