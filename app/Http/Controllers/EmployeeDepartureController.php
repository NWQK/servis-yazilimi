<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Services\EmployeeDeparture;
use Illuminate\Http\Request;
class EmployeeDepartureController extends Controller {
    public function show(int $id,EmployeeDeparture $flow){
        $employee=User::where('type','employee')->findOrFail($id);$flow->allowed($employee);
        $services=$flow->open($employee)->get()->groupBy('parent_id');$replacements=[];
        foreach($services as $owner=>$rows){
            if(auth()->user()->type==='owner' && (int)$owner!==auth()->id())abort(403,'Diğer şubelerdeki işleri merkez yöneticisi devretmelidir.');
            if(auth()->user()->type==='branch_manager')abort_unless(\DB::table('business_branches')->join('business_groups','business_groups.id','=','business_branches.group_id')->where('owner_id',$owner)->where('manager_user_id',auth()->id())->where('is_active',1)->exists(),403);
            $replacements[$owner]=User::where('is_active',1)->where('id','!=',$employee->id)->where(fn($q)=>$q->where(fn($q)=>$q->where('parent_id',$owner)->where('type','employee'))->orWhere(fn($q)=>$q->where('id',$owner)->where('type','owner')))->get(['id','name']);
        }
        return view('employee.departure',compact('employee','services','replacements'));
    }
    public function depart(Request $request,int $id,EmployeeDeparture $flow){
        $data=$request->validate(['assignments'=>'nullable|array|max:100','assignments.*'=>'integer','confirm'=>'accepted']);
        $flow->depart($id,$data['assignments']??[]);return redirect()->route('employee-departures.show',$id)->with('success','Personel erişimi kapatıldı. Açık işler devredildi, geçmiş kayıtlar korundu.');
    }
}
