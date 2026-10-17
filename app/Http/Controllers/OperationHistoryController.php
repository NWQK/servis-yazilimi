<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class OperationHistoryController extends Controller
{
    public function invoices(Request $request)
    {
        abort_unless(auth()->user()->type!=='client' && auth()->user()->can('manage invoice'),403);
        $request->validate(['invoice'=>'nullable|integer|min:1']);
        $rows=DB::table('invoice_changes')->where('parent_id',parentId())->where('created_at','>=',now()->subYears(2))->when($request->filled('invoice'),fn($q)=>$q->where('invoice_id',$request->invoice))->orderByDesc('id')->paginate(25)->withQueryString();
        return view('history.invoices',compact('rows'));
    }
    public function stock(Request $request)
    {
        abort_unless(auth()->user()->type!=='client' && auth()->user()->can('manage item'),403);
        $request->validate(['kind'=>'nullable|in:purchase,adjustment,sale,return,counter_sale,transfer_out,transfer_in,transfer_return']);
        $rows=DB::table('stock_movements as m')->leftJoin('items as i',fn($j)=>$j->on('i.inventory_key','=','m.inventory_key')->on('i.parent_id','=','m.parent_id'))
            ->where('m.parent_id',parentId())->where('m.created_at','>=',now()->subYears(2))->select('m.*')->selectRaw('COALESCE(m.item_title,i.title) AS title')
            ->when($request->filled('kind'),fn($q)=>$q->where('m.kind',$request->kind))->orderByDesc('m.id')->paginate(25)->withQueryString();
        return view('history.stock',compact('rows'));
    }
}
