<?php
namespace App\Http\Controllers;

use App\Services\{AdminAudit, PlatformMaintenance};
use Illuminate\Http\Request;

class PlatformMaintenanceController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()?->type === 'super admin',403);
        return view('admin.maintenance',['enabled'=>PlatformMaintenance::enabled()]);
    }
    public function save(Request $request)
    {
        abort_unless(auth()->user()?->type === 'super admin',403);
        $data=$request->validate(['enabled'=>'required|boolean']);
        $before=PlatformMaintenance::enabled();
        PlatformMaintenance::set((bool)$data['enabled']);
        AdminAudit::record('maintenance.status',null,['enabled'=>$before],['enabled'=>(bool)$data['enabled']]);
        return back()->with('success',$data['enabled'] ? 'Bakım modu açıldı. Tanıtım sayfası yayında kalır.' : 'Bakım modu kapatıldı. Sistem kullanıma açık.');
    }
}
