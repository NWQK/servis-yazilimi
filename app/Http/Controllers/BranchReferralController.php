<?php
namespace App\Http\Controllers;
use App\Models\{BranchReferral,User,Vehicle,ServiceType};
use App\Services\BranchReferrals;
use Illuminate\Http\Request;
class BranchReferralController extends Controller {
    private function group(Request $request,BranchReferrals $flow){$id=$request->integer('group')?:\DB::table('business_branches')->where('owner_id',parentId())->value('group_id');if(!$id && auth()->user()->type==='branch_manager')$id=\DB::table('business_groups')->where('manager_user_id',auth()->id())->value('id');return $flow->group((int)$id);}
    public function index(Request $request,BranchReferrals $flow){
        $group=$this->group($request,$flow);$owners=$group->owners()->get(['users.id','users.name']);$allowed=$owners->filter(fn($o)=>$flow->access($group,$o->id));
        $vehicles=Vehicle::whereIn('parent_id',$allowed->pluck('id'))->select('id','parent_id','license_plate','chassis_no')->get();
        $referrals=BranchReferral::where('group_id',$group->id)->where(fn($q)=>$q->whereIn('source_owner_id',$allowed->pluck('id'))->orWhereIn('target_owner_id',$allowed->pluck('id')))->orderByDesc('id')->paginate(20)->withQueryString();
        return response()->view('branches.referrals',compact('group','owners','allowed','vehicles','referrals','flow'))->header('Cache-Control','private, no-store');
    }
    public function send(Request $request,BranchReferrals $flow){
        $data=$request->validate(['group'=>'required|integer','source_owner_id'=>'required|integer','target_owner_id'=>'required|integer','vehicle_id'=>'required|integer','share'=>'nullable|array|max:5',
            'share.*'=>'string|in:'.implode(',',array_keys(BranchReferrals::SHARE)),'note'=>'nullable|string|max:1000','request_key'=>'required|uuid','confirm'=>'accepted']);
        $flow->send($data['group'],$data);return back()->with('success','Araç yönlendirmesi gönderildi. Kaynak kayıtlar korundu.');
    }
    public function show(int $id,BranchReferrals $flow){
        $row=BranchReferral::findOrFail($id);$group=$flow->group($row->group_id);abort_unless($flow->access($group,$row->target_owner_id),403);
        $owner=$row->target_owner_id;$customers=User::where('parent_id',$owner)->where('type','client')->whereNull('client_archived_at')->get(['id','name']);
        $vehicles=Vehicle::where('parent_id',$owner)->where('chassis_no',$row->shared_data['chassis_no'])->get(['id','client','license_plate']);
        $staff=User::where('is_active',1)->where(fn($q)=>$q->where(fn($q)=>$q->where('parent_id',$owner)->where('type','employee'))->orWhere(fn($q)=>$q->where('id',$owner)->where('type','owner')))->get(['id','name']);
        $types=ServiceType::where('parent_id',$owner)->get(['id','type']);return response()->view('branches.referral',compact('row','group','customers','vehicles','staff','types'))->header('Cache-Control','private, no-store');
    }
    public function resolve(Request $request,int $id,BranchReferrals $flow){
        $data=$request->validate(['action'=>'required|in:accept,reject,cancel','version'=>'required|integer','customer_id'=>'nullable|integer','vehicle_id'=>'nullable|integer',
            'customer_name'=>'nullable|string|max:150','assign'=>'required_if:action,accept|nullable|integer','service_type'=>'nullable|integer','confirm'=>'accepted']);
        $row=$flow->resolve($id,$data);
        if($data['action']==='accept' && auth()->user()->type!=='super admin' && \App\Models\Service::where('parent_id',$row->target_owner_id)->whereKey($row->target_service_id)->exists()){
            $params=[encrypt($row->target_service_id)];
            if(auth()->user()->type==='branch_manager')$params['_branch']=\App\Services\BranchContext::token(\App\Models\BusinessGroup::findOrFail($row->group_id),User::findOrFail($row->target_owner_id),auth()->user());
            return redirect()->route('service.show',$params)->with('success','Yönlendirme kabul edildi; alıcı şubenin servisi açıldı.');
        }
        return redirect()->route('branch-referrals.index',['group'=>$row->group_id])->with('success','Yönlendirme sonucu kaydedildi.');
    }
}
