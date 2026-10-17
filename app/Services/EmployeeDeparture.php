<?php
namespace App\Services;
use App\Models\{User,Service,BusinessGroup};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class EmployeeDeparture {
    public function allowed(User $employee): void {
        $actor=auth()->user();abort_unless($actor && $actor->is_active && $employee->type==='employee',403);
        if($actor->type==='super admin')return;
        if($actor->type==='branch_manager'){
            abort_unless(DB::table('business_branches')->join('business_groups','business_groups.id','=','business_branches.group_id')->where('business_branches.owner_id',$employee->parent_id)->where('manager_user_id',$actor->id)->where('is_active',1)->exists(),403);return;
        }
        abort_unless($actor->type==='owner' && $actor->id===(int)$employee->parent_id && $actor->can('delete employee'),403);
    }
    public function open(User $employee){return Service::where('assign',$employee->id)->whereNotIn('status',['completed','cancelled']);}
    public function depart(int $id,array $assignments): void {
        $employee=User::where('type','employee')->findOrFail($id);$this->allowed($employee);
        $groupId=DB::table('business_branches')->where('owner_id',$employee->parent_id)->value('group_id');
        $owners=$this->open($employee)->pluck('parent_id')->push($employee->parent_id)->unique()->sort()->values()->all();
        foreach($owners as $owner)if((int)$owner!==(int)$employee->parent_id && auth()->user()->type!=='super admin')
            abort_unless(auth()->user()->type==='branch_manager' && $groupId && DB::table('business_branches')->where('owner_id',$owner)->where('group_id',$groupId)->exists(),403,'Diğer şubelerdeki açık işleri merkez yöneticisi devretmelidir.');
        $fileLock=function($offset)use(&$fileLock,$owners,$employee,$groupId,$assignments){
            if(isset($owners[$offset]))return app(\App\Services\Recovery\RecoveryWriteLock::class)->shared($owners[$offset],fn()=>$fileLock($offset+1));
            return BranchTransactions::run(function()use($owners,$employee,$groupId,$assignments){
                if($groupId)DB::table('business_groups')->where('id',$groupId)->lockForUpdate()->first();
                abort_unless((int)(DB::table('business_branches')->where('owner_id',$employee->parent_id)->value('group_id')??0)===(int)($groupId??0),409,'Şube bağlantısı değişti. Ayrılış ekranını yenileyin.');
                foreach($owners as $owner)User::whereKey($owner)->lockForUpdate()->firstOrFail();
                $staff=User::where('type','employee')->lockForUpdate()->findOrFail($employee->id);$this->allowed($staff);
                if(!(int)$staff->is_active && $staff->departed_at)return;
                $services=$this->open($staff)->orderBy('id')->lockForUpdate()->get();
                foreach($services as $service){
                    if(!in_array($service->parent_id,$owners))throw ValidationException::withMessages(['assignments'=>'Personelin işleri değişti. Ekranı yenileyin.']);
                    if(auth()->user()->type!=='super admin' && (int)$service->parent_id!==(int)$staff->parent_id){
                        abort_unless(auth()->user()->type==='branch_manager' && $groupId && DB::table('business_branches')->where('group_id',$groupId)->where('owner_id',$service->parent_id)->exists(),403);
                    }
                    $target=(int)($assignments[$service->parent_id]??0);
                    $replacement=User::where('id',$target)->where('is_active',1)->where(fn($q)=>$q->where(fn($q)=>$q->where('type','employee')->where('parent_id',$service->parent_id))->orWhere(fn($q)=>$q->where('type','owner')->where('id',$service->parent_id)))->lockForUpdate()->first();
                    if(!$replacement || $replacement->id===$staff->id)throw ValidationException::withMessages(['assignments'=>'Açık işler için aynı işletmeden aktif bir personel veya işletme sahibini seçin.']);
                    $service->assign=$replacement->id;$service->save();
                }
                $staff->is_active=0;$staff->departed_at=now();$staff->session_epoch=(int)$staff->session_epoch+1;$staff->remember_token=\Illuminate\Support\Str::random(60);$staff->save();
                DB::table('branch_staff_access')->where('user_id',$staff->id)->delete();
                if($groupId){DB::table('business_groups')->where('id',$groupId)->increment('version');BranchActivity::record($groupId,$staff->parent_id,'staff.departed',['user_id'=>$staff->id]);}
                DB::table('employee_departures')->insert(['parent_id'=>$staff->parent_id,'employee_id'=>$staff->id,'actor_id'=>auth()->id(),'employee_name'=>$staff->name,'assignments'=>json_encode($assignments),'created_at'=>now(),'updated_at'=>now()]);
            },3);
        };
        $fileLock(0);
    }
}
