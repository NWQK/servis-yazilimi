<?php
namespace App\Http\Controllers;
use App\Models\{BusinessGroup,User};
use App\Services\{BranchStaff,BranchPermissions,BranchReports,BranchActivity};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
class BranchOperationsController extends Controller
{
    public function staff(Request $request)
    {
        $group=BranchStaff::managerGroup($request->integer('group') ?: null);
        $owners=$group->owners()->orderBy('name')->get(['users.id','users.name']);
        $staff=User::where('type','employee')->whereIn('parent_id',$owners->pluck('id'))->orderBy('name')->get(['id','name','parent_id','is_active']);
        $grants=DB::table('branch_staff_access')->where('group_id',$group->id)->orderBy('id')->get();
        $modules=BranchPermissions::MODULES;
        return view('branches.staff',compact('group','owners','staff','grants','modules'));
    }
    public function saveStaff(Request $request, int $group, BranchStaff $staff)
    {
        BranchStaff::managerGroup($group);
        $data=$request->validate(['user_id'=>'required|integer|min:1','owner_id'=>'required|integer|min:1','version'=>'required|integer|min:1',
            'action'=>'required|in:grant,revoke','modules'=>'required_if:action,grant|array|max:6','modules.*'=>['string','distinct',Rule::in(array_keys(BranchPermissions::MODULES))],
            'can_write'=>'nullable|boolean','can_delete'=>'nullable|boolean']);
        $staff->save($group,$data);
        return back()->with('success','Personel erişimi güncellendi. Eski şube formları geçersizleşti.');
    }
    public function report(Request $request, BranchReports $reports)
    {
        $group=BranchStaff::managerGroup($request->integer('group') ?: null);
        $data=$request->validate(['start'=>'nullable|date_format:Y-m-d','end'=>'nullable|date_format:Y-m-d|after_or_equal:start']);
        $start=$data['start'] ?? now()->startOfMonth()->toDateString(); $end=$data['end'] ?? now()->toDateString();
        abort_unless($start <= $end && \Carbon\Carbon::parse($start)->diffInDays($end)<=366,422,'En fazla bir yıllık tarih aralığı seçin.');
        $rows=$reports->compare($group,$start,$end);
        return response()->view('branches.report',compact('group','start','end','rows'))->header('Cache-Control','private, no-store');
    }
    public function history(Request $request)
    {
        $group=BranchStaff::managerGroup($request->integer('group') ?: null);
        $data=$request->validate(['owner'=>'nullable|integer|min:1','action'=>['nullable',Rule::in(array_keys(BranchActivity::labels()))]]);
        $owners=$group->owners()->get(['users.id','users.name']);
        if (!empty($data['owner'])) abort_unless($owners->contains('id',(int)$data['owner']),403);
        $rows=DB::table('branch_activity_logs')->where('group_id',$group->id)->where('created_at','>=',now()->subDays(180))
            ->when(!empty($data['owner']),fn($q)=>$q->where('owner_id',$data['owner']))
            ->when(!empty($data['action']),fn($q)=>$q->where('action',$data['action']))->orderByDesc('id')->paginate(30)->withQueryString();
        $labels=BranchActivity::labels();
        return response()->view('branches.history',compact('group','owners','rows','labels'))->header('Cache-Control','private, no-store');
    }
}
