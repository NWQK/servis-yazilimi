<?php
namespace App\Http\Controllers;

use App\Models\{BusinessGroup, User};
use App\Services\{BusinessGroups, BusinessUsage, BranchContext};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BusinessGroupController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->type === 'super admin', 403);
        $groups = BusinessGroup::with(['manager','owners'])->orderBy('name')->get();
        $available = User::where('type','owner')->whereNotIn('id', DB::table('business_branches')->select('owner_id'))->orderBy('name')->get(['id','name','email']);
        return response()->view('branches.admin', compact('groups','available'))->header('Cache-Control','private, no-store');
    }
    public function store(Request $request, BusinessGroups $groups)
    {
        abort_unless(auth()->user()->type === 'super admin', 403);
        $data = $request->validate(['name'=>'required|string|max:150', 'manager_name'=>'required|string|max:150',
            'email'=>'required|email|max:191|unique:users,email', 'password'=>'required|string|min:12|max:100|confirmed']);
        $groups->create($data);
        return back()->with('success','Şube merkez hesabı oluşturuldu. Mevcut işletmeleri bu merkeze bağlayabilirsiniz.');
    }
    public function membership(Request $request, int $group, BusinessGroups $groups)
    {
        abort_unless(auth()->user()->type === 'super admin', 403);
        $data = $request->validate(['owner_id'=>'required|integer|min:1', 'version'=>'required|integer|min:1',
            'action'=>'required|in:attach,detach', 'confirm'=>'accepted']);
        $groups->membership($group, $data['owner_id'], $data['version'], $data['action'] === 'attach');
        return back()->with('success','Şube bağlantısı güncellendi. İşletme kayıtları ve mevcut giriş hesapları korundu.');
    }
    public function status(Request $request, int $group, BusinessGroups $groups)
    {
        abort_unless(auth()->user()->type === 'super admin', 403);
        $data = $request->validate(['version'=>'required|integer|min:1','active'=>'required|boolean']);
        $groups->status($group, $data['version'], (bool)$data['active']);
        return back()->with('success','Merkez erişimi güncellendi. Şubelerin kendi hesapları etkilenmedi.');
    }
    public function portal()
    {
        $user = auth()->user();
        abort_unless(in_array($user->type,['branch_manager','employee'],true), 403);
        $staffMode=$user->type==='employee';
        $group = $staffMode ? BusinessGroup::whereIn('id',DB::table('business_branches')->where('owner_id',$user->parent_id)->select('group_id'))->firstOrFail()
            : BusinessGroup::where('manager_user_id',$user->id)->firstOrFail();
        abort_unless($group->is_active && $user->is_active, 403, 'Merkez hesabı askıda. Yönetici ile iletişime geçin.');
        $query=$staffMode ? User::where('type','owner') : BusinessUsage::query();
        $owners = $query->whereIn('users.id', DB::table('business_branches')->where('group_id',$group->id)->select('owner_id'))
            ->when($staffMode,fn($q)=>$q->where(fn($q)=>$q->where('id',$user->parent_id)->orWhereIn('id',DB::table('branch_staff_access')->where('group_id',$group->id)->where('user_id',$user->id)->select('owner_id'))))
            ->orderBy('name')->get();
        $totals = ['vehicle_count'=>$owners->sum('vehicle_count'), 'client_count'=>$owners->sum('client_count'),
            'month_services'=>$owners->sum('month_services'), 'month_invoices'=>$owners->sum('month_invoices')];
        $links = $owners->mapWithKeys(fn($owner)=>[$owner->id => $staffMode && (int)$user->parent_id===$owner->id
            ? route('dashboard') : route('dashboard',['_branch'=>BranchContext::token($group,$owner,$user)])]);
        return response()->view('branches.portal',compact('group','owners','totals','links','staffMode'))->header('Cache-Control','private, no-store');
    }
}
