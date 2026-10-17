<?php
namespace App\Services;
use App\Models\{Item,Invoice};
use Illuminate\Support\Facades\DB;

/** Reports discrepancies only; never repairs financial or inventory records. */
class IntegrityCheck
{
    public static function baseline(Item $item,?int $quantity=null): void
    {
        if(!$item->inventory_key || !\Schema::hasTable('inventory_checkpoints'))return;
        DB::table('inventory_checkpoints')->insertOrIgnore(['parent_id'=>$item->parent_id,'inventory_key'=>$item->inventory_key,
            'quantity'=>$quantity??$item->quantity,'movement_id'=>DB::table('stock_movements')->where('parent_id',$item->parent_id)->where('inventory_key',$item->inventory_key)->max('id')??0,
            'created_at'=>now(),'updated_at'=>now()]);
    }
    public function run(int $owner): array
    {
        return app(\App\Services\Recovery\RecoveryWriteLock::class)->shared($owner,fn()=>app(InventoryAccounting::class)->transaction($owner,function()use($owner){
            $issues=[];$count=0;
            $add=function(string $code,int $id,array $detail=[])use(&$issues,&$count){$count++;if(count($issues)<100)$issues[]=['code'=>$code,'id'=>$id]+$detail;};
            Item::where('parent_id',$owner)->orderBy('id')->chunkById(200,function($items)use($owner,$add){foreach($items as $item){
                if($item->quantity<0)$add('negative_stock',$item->id);
                if(!$item->inventory_key){$add('untracked_stock',$item->id);continue;}
                $base=DB::table('inventory_checkpoints')->where('parent_id',$owner)->where('inventory_key',$item->inventory_key)->first();
                $movements=DB::table('stock_movements')->where('parent_id',$owner)->where('inventory_key',$item->inventory_key);
                $latest=(clone $movements)->max('id')??0;
                if(!$base){
                    $first=(clone $movements)->orderBy('id')->first();
                    if(!$first || !in_array($first->kind,['purchase','transfer_in'],true)){
                        $add('baseline_needed',$item->id);continue;
                    }
                }
                $delta=(clone $movements)->where('id','>',$base->movement_id??0)->sum('quantity');
                $expected=(int)($base->quantity??0)+(int)$delta;
                if($expected!==(int)$item->quantity){$add('stock_mismatch',$item->id,['expected'=>$expected,'actual'=>(int)$item->quantity]);continue;}
                DB::table('inventory_checkpoints')->updateOrInsert(['parent_id'=>$owner,'inventory_key'=>$item->inventory_key],
                    ['quantity'=>$item->quantity,'movement_id'=>$latest,'updated_at'=>now(),'created_at'=>$base->created_at??now()]);
            }});
            Invoice::where('parent_id',$owner)->with(['items'=>fn($q)=>$q->where('parent_id',$owner),'types'=>fn($q)=>$q->where('parent_id',$owner),
                'payments'=>fn($q)=>$q->where('parent_id',$owner)])->orderBy('id')->chunkById(100,function($invoices)use($add){foreach($invoices as $invoice){
                    $total=CashRegister::cents($invoice->getInvoiceAllTotalAmount());
                    $paid=$invoice->payments->filter(fn($p)=>$p->payment_status===null||in_array($p->payment_status,['success','succeeded'],true))->sum(fn($p)=>CashRegister::cents($p->amount));
                    if($paid>$total)$add('overpayment',$invoice->id);
                    $expected=$paid >= $total ? 2 : ($paid>0?1:0);
                    if($total>0 && (int)$invoice->status!==$expected)$add('invoice_status',$invoice->id);
                }});
            foreach(['invoice_payments','invoice_items','invoice_services'] as $table)
                DB::table($table)->where('parent_id',$owner)->whereNotExists(fn($q)=>$q->selectRaw('1')->from('invoices')->whereColumn('invoices.id',$table.'.invoice_id')->where('invoices.parent_id',$owner))
                    ->orderBy('id')->chunkById(200,function($rows)use($add,$table){foreach($rows as $row)$add('invalid_'.$table,$row->id);});
            DB::table('branch_stock_transfers')->where('status','pending')->where('created_at','<',now()->subDays(7))
                ->where(fn($q)=>$q->where('source_owner_id',$owner)->orWhere('target_owner_id',$owner))->orderBy('id')
                ->chunkById(200,function($rows)use($add){foreach($rows as $row)$add('pending_transfer',$row->id);});
            $result=['status'=>$count?'attention':'ok','issue_count'=>$count,'issues'=>json_encode($issues),'checked_at'=>now()];
            DB::table('integrity_checks')->updateOrInsert(['parent_id'=>$owner],$result);
            return $result;
        }));
    }
    public static function labels(): array {return ['negative_stock'=>'Negatif stok','untracked_stock'=>'Stok hareket kimliği eksik','baseline_needed'=>'Başlangıç stok bilgisi doğrulanamıyor',
        'stock_mismatch'=>'Stok miktarı ile hareketler uyuşmuyor','overpayment'=>'Fatura tutarını aşan ödeme','invoice_status'=>'Faturanın ödeme durumu güncel değil',
        'invalid_invoice_payments'=>'Ödemenin faturası eksik veya başka işletmede','invalid_invoice_items'=>'Ürün satırının faturası eksik veya başka işletmede',
        'invalid_invoice_services'=>'Hizmet satırının faturası eksik veya başka işletmede','pending_transfer'=>'7 gündür teslim bekleyen stok transferi'];}
}
