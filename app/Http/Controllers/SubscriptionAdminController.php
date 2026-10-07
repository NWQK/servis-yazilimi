<?php
namespace App\Http\Controllers;

use App\Models\{User,Subscription,SubscriptionChange,Vehicle,VehicleCapacityRequest};
use App\Services\VehicleCapacity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionAdminController extends Controller
{
    private function authorizeAdmin(): void { abort_unless(auth()->user()->type==='super admin',403); }
    private function owner(int $id): User { return User::where('type','owner')->findOrFail($id); }
    private function snapshot(User $owner): array
    {
        return $owner->only(['subscription','subscription_expire_date','subscription_suspended_at','subscription_suspension_reason']);
    }
    private function record(User $owner,string $action,array $before): void
    {
        $after=$this->snapshot($owner);
        if ($before!==$after) { SubscriptionChange::create(['owner_id'=>$owner->id,'actor_id'=>auth()->id(),'action'=>$action,'before'=>$before,'after'=>$after]); \App\Services\AdminAudit::record('subscription.'.$action,$owner->id,$before,$after); }
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin();
        $filters=$request->validate(['search'=>'nullable|string|max:150','status'=>'nullable|in:active,suspended,expired','plan'=>'nullable|integer']);
        $query=User::where('type','owner')->with('subscriptions')->select('users.*')
            ->selectSub(Vehicle::selectRaw('COUNT(*)')->whereColumn('parent_id','users.id'),'vehicle_count')
            ->selectSub(VehicleCapacityRequest::selectRaw('COALESCE(SUM(vehicles),0)')->whereColumn('owner_id','users.id')->where('status','approved'),'extra_vehicles');
        if (!empty($filters['search'])) {
            $term=$filters['search'];
            $query->where(fn($q)=>$q->where('name','like','%'.$term.'%')->orWhere('email','like','%'.$term.'%'));
        }
        if (!empty($filters['plan'])) { $query->where('subscription',$filters['plan']); }
        $today=now()->toDateString();
        if (($filters['status'] ?? '')==='suspended') { $query->whereNotNull('subscription_suspended_at'); }
        elseif (($filters['status'] ?? '')==='expired') { $query->whereNull('subscription_suspended_at')->whereNotNull('subscription_expire_date')->where('subscription_expire_date','<',$today); }
        elseif (($filters['status'] ?? '')==='active') { $query->whereNull('subscription_suspended_at')->where(fn($q)=>$q->whereNull('subscription_expire_date')->orWhere('subscription_expire_date','>=',$today)); }
        $owners=$query->orderByDesc('id')->paginate(25)->withQueryString();
        $plans=Subscription::orderBy('vehicle_limit')->get();
        return view('subscription.admin.index',compact('owners','plans','filters','today'));
    }

    public function edit(int $id)
    {
        $this->authorizeAdmin(); $owner=$this->owner($id);
        $plans=Subscription::orderBy('vehicle_limit')->get();
        $capacity=app(VehicleCapacity::class)->summary($owner);
        $changes=SubscriptionChange::where('owner_id',$owner->id)->with('actor')->latest('id')->limit(20)->get();
        return view('subscription.admin.edit',compact('owner','plans','capacity','changes'));
    }

    public function update(Request $request,int $id)
    {
        $this->authorizeAdmin(); $this->owner($id);
        $data=$request->validate(['subscription'=>'required|integer|exists:subscriptions,id','no_expiry'=>'nullable|boolean','expiry_date'=>'required_unless:no_expiry,1|nullable|date_format:Y-m-d']);
        DB::transaction(function() use ($id,$data) {
            $owner=User::where('type','owner')->whereKey($id)->lockForUpdate()->firstOrFail();
            $before=$this->snapshot($owner); $plan=Subscription::findOrFail($data['subscription']);
            $owner->subscription=$plan->id;
            $owner->subscription_expire_date=!empty($data['no_expiry']) ? null : $data['expiry_date'];
            $owner->save();
            if ((int)$before['subscription'] !== (int)$plan->id) { SetLimit($plan,$owner->id); }
            $this->record($owner,'update',$before);
        });
        return redirect()->route('subscription-admin.index')->with('success','İşletmenin paketi ve abonelik süresi güncellendi.');
    }

    public function status(Request $request,int $id)
    {
        $this->authorizeAdmin(); $this->owner($id);
        $data=$request->validate(['action'=>'required|in:suspend,resume','confirm'=>'accepted','reason'=>'nullable|string|max:500']);
        DB::transaction(function() use ($id,$data) {
            $owner=User::where('type','owner')->whereKey($id)->lockForUpdate()->firstOrFail();
            $before=$this->snapshot($owner);
            if ($data['action']==='suspend') {
                if ($owner->subscription_suspended_at) { return; }
                $owner->subscription_suspended_at=now()->toDateTimeString();
                $owner->subscription_suspension_reason=$data['reason'] ?? 'Süper admin tarafından askıya alındı.';
            } else {
                if (!$owner->subscription_suspended_at) { return; }
                $owner->subscription_suspended_at=null; $owner->subscription_suspension_reason=null;
            }
            $owner->save(); $this->record($owner,$data['action'],$before);
        });
        return back()->with('success',$data['action']==='suspend' ? 'Abonelik askıya alındı. İşletme ve personelinin panel erişimi durduruldu.' : 'Abonelik yeniden etkinleştirildi. Bitiş tarihi değiştirilmedi.');
    }
}
