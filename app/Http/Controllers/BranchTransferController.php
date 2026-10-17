<?php
namespace App\Http\Controllers;
use App\Models\{BusinessGroup,BranchStockTransfer,Item,Unit};
use App\Services\BranchTransfers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class BranchTransferController extends Controller
{
    private function groupId(Request $request): int
    {
        if(auth()->user()->type==='super admin') return (int)$request->validate(['group'=>'required|integer|min:1'])['group'];
        if(auth()->user()->type==='branch_manager') return (int)BusinessGroup::where('manager_user_id',auth()->id())->value('id');
        return (int)DB::table('business_branches')->where('owner_id',auth()->user()->type==='owner'?auth()->id():auth()->user()->parent_id)->value('group_id');
    }
    public function index(Request $request, BranchTransfers $service)
    {
        $group=$service->group($this->groupId($request));
        $owners=$group->owners()->orderBy('name')->get(['users.id','users.name']);
        $visible=$owners->filter(fn($owner)=>$service->access($group,$owner->id))->pluck('id');
        $sources=$owners->filter(fn($owner)=>$service->access($group,$owner->id,true));
        $selected=(int)$request->query('source', $sources->first()?->id ?? 0);
        abort_if($selected && !$sources->contains('id',$selected),403);
        $items=$selected ? Item::where('parent_id',$selected)->where('quantity','>',0)->select(['id','parent_id','title','quantity','units'])->with('unit')->orderBy('title')->get() : collect();
        $rows=BranchStockTransfer::where('group_id',$group->id)->where(fn($q)=>$q->whereIn('source_owner_id',$visible)->orWhereIn('target_owner_id',$visible))->orderByDesc('id')->paginate(25)->withQueryString();
        $canReceive=$owners->mapWithKeys(fn($owner)=>[$owner->id=>$service->access($group,$owner->id,true)]);
        return response()->view('branches.transfers',compact('group','owners','sources','selected','items','rows','canReceive'))->header('Cache-Control','private, no-store');
    }
    public function send(Request $request, BranchTransfers $service)
    {
        $data=$request->validate(['group'=>'required|integer|min:1','source_owner_id'=>'required|integer|min:1','target_owner_id'=>'required|integer|min:1',
            'source_item_id'=>'required|integer|min:1','quantity'=>'required|integer|min:1|max:100000000','request_key'=>'required|uuid','note'=>'nullable|string|max:500','confirm'=>'accepted']);
        $service->send($data['group'],$data);
        return redirect()->route('branch-transfers.index',['group'=>$data['group']])->with('success','Transfer gönderildi. Ürünler gönderenin kullanılabilir stoğundan ayrıldı; alan şubeye teslim onayıyla eklenecek.');
    }
    public function receipt(int $transfer, BranchTransfers $service)
    {
        $transfer=BranchStockTransfer::findOrFail($transfer);
        $group=$service->group($transfer->group_id);
        abort_unless($service->access($group,$transfer->target_owner_id,true),403);
        $items=Item::where('parent_id',$transfer->target_owner_id)->select(['id','parent_id','title','quantity','units'])->with('unit')->orderBy('title')->get();
        $units=Unit::where('parent_id',$transfer->target_owner_id)->orderBy('unit')->get();
        return response()->view('branches.receipt',compact('group','transfer','items','units'))->header('Cache-Control','private, no-store');
    }
    public function resolve(Request $request,int $transfer,BranchTransfers $service)
    {
        $data=$request->validate(['version'=>'required|integer|min:1','action'=>'required|in:receive,cancel',
            'target_item_id'=>'nullable|integer|min:1','target_unit_id'=>'nullable|integer|min:1','confirm'=>'accepted']);
        $row=$service->resolve($transfer,$data,$data['action']==='receive');
        return redirect()->route('branch-transfers.index',['group'=>$row->group_id])->with('success',$row->status==='received'?'Ürünler teslim alındı; alan şubenin stoğuna eklendi.':'Transfer iptal edildi; ürünler gönderen şubenin stoğuna iade edildi.');
    }
}
