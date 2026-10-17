<?php
namespace App\Services;
use App\Models\{BusinessGroup,Invoice,InvoicePayment,User};
use Illuminate\Support\Facades\DB;

class BranchReports
{
    public function compare(BusinessGroup $group, string $start, string $end): array
    {
        return DB::transaction(function()use($group,$start,$end){
            $group=BusinessGroup::whereKey($group->id)->sharedLock()->firstOrFail();
            abort_unless(auth()->user()?->type==='super admin' || (auth()->user()?->type==='branch_manager'
                && $group->is_active && $group->manager_user_id===auth()->id()),403);
            $owners=$group->owners()->orderBy('name')->get(['users.id','users.name']); $ids=$owners->pluck('id')->all();
            $sums=function($table,$date,$column='amount')use($ids,$start,$end){
                return DB::table($table)->whereIn('parent_id',$ids)->whereBetween($date,[$start,$end])
                    ->select('parent_id')->selectRaw('SUM('.$column.') AS aggregate')->groupBy('parent_id')->pluck('aggregate','parent_id');
            };
            $paid=InvoicePayment::accounted()->join('invoices',fn($j)=>$j->on('invoices.id','=','invoice_payments.invoice_id')->on('invoices.parent_id','=','invoice_payments.parent_id'))
                ->whereIn('invoice_payments.parent_id',$ids)->whereBetween('payment_date',[$start,$end])->select('invoice_payments.parent_id')
                ->selectRaw('SUM(invoice_payments.amount) AS aggregate')->groupBy('invoice_payments.parent_id')->pluck('aggregate','parent_id');
            $sales=$sums('counter_sales','sale_date'); $expenses=$sums('expenses','date');
            $services=DB::table('services')->whereIn('parent_id',$ids)->whereBetween('created_at',[$start.' 00:00:00',$end.' 23:59:59'])
                ->select('parent_id')->selectRaw('COUNT(*) AS aggregate')->groupBy('parent_id')->pluck('aggregate','parent_id');
            $counts=DB::table('invoices')->whereIn('parent_id',$ids)->whereBetween(DB::raw('COALESCE(invoice_date,DATE(created_at))'),[$start,$end])
                ->select('parent_id')->selectRaw('COUNT(*) AS aggregate')->groupBy('parent_id')->pluck('aggregate','parent_id');
            $dues=[]; $billed=[];
            // Reuse the invoice screen's calculation; do not maintain a second financial formula.
            Invoice::whereIn('parent_id',$ids)->with(['items','types','payments'])->chunkById(200,function($invoices)use(&$dues,&$billed,$start,$end){
                foreach($invoices as $invoice) {
                    foreach(['items','types','payments'] as $relation) $invoice->setRelation($relation,$invoice->$relation->where('parent_id',$invoice->parent_id)->values());
                    $total=(int)round($invoice->getInvoiceAllTotalAmount()*100);
                    $paid=(int)round($invoice->payments->filter(fn($payment)=>$payment->payment_status===null || in_array($payment->payment_status,['success','succeeded'],true))->sum('amount')*100);
                    $dues[$invoice->parent_id]=($dues[$invoice->parent_id] ?? 0)+max(0,$total-$paid);
                    $date=$invoice->invoice_date ?: substr((string)$invoice->created_at,0,10);
                    if ($date >= $start && $date <= $end) $billed[$invoice->parent_id]=($billed[$invoice->parent_id] ?? 0)+$total;
                }
            });
            return $owners->map(fn($owner)=>[
                'owner_id'=>$owner->id,'name'=>$owner->name,'services'=>(int)($services[$owner->id] ?? 0),'invoices'=>(int)($counts[$owner->id] ?? 0),
                'billed'=>($billed[$owner->id] ?? 0)/100,
                'collections'=>round((float)($paid[$owner->id] ?? 0)+(float)($sales[$owner->id] ?? 0),2),
                'expenses'=>round((float)($expenses[$owner->id] ?? 0),2),
                'cash_difference'=>round((float)($paid[$owner->id] ?? 0)+(float)($sales[$owner->id] ?? 0)-(float)($expenses[$owner->id] ?? 0),2),
                'outstanding'=>($dues[$owner->id] ?? 0)/100,
            ])->all();
        });
    }
}
