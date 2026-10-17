<?php
namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
class IntegrityController extends Controller {
    public function index() {
        abort_unless(auth()->user()->type==='super admin',403);
        $checks=DB::table('integrity_checks')->join('users','users.id','=','integrity_checks.parent_id')->where('users.type','owner')
            ->select('integrity_checks.*','users.name')->orderByDesc('issue_count')->paginate(25);
        $messages=DB::table('communication_outbox')->select('status')->selectRaw('COUNT(*) as total')->groupBy('status')->pluck('total','status');
        $latest=DB::table('communication_outbox')->select('id','channel','status','attempts','error_code','created_at','updated_at')->orderByDesc('id')->paginate(20,['*'],'messages_page');
        return view('admin.integrity',compact('checks','messages','latest'));
    }
    public function run(Request $request) {
        abort_unless(auth()->user()->type==='super admin',403);
        $data=$request->validate(['owner'=>'required|integer']);
        \App\Models\User::where('type','owner')->findOrFail($data['owner']);
        app(\App\Services\IntegrityCheck::class)->run($data['owner']);return back()->with('success','Kontrol tamamlandı; kayıtlar değiştirilmedi.');
    }
}
