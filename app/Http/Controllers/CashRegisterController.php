<?php
namespace App\Http\Controllers;
use App\Services\CashRegister;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class CashRegisterController extends Controller {
    private function checkAccess(bool $write=false): void {
        abort_unless(in_array(auth()->user()->type,['owner','employee','branch_manager'],true) && auth()->user()->can($write?'create expense':'manage expense'),403);
    }
    public function index(Request $request,CashRegister $cash) {
        $this->checkAccess();$data=$request->validate(['date'=>'nullable|date_format:Y-m-d|before_or_equal:today']);
        $date=$data['date']??now('Europe/Istanbul')->toDateString();$summary=$cash->summary(parentId(),$date);
        $closing=DB::table('cash_closings')->where('parent_id',parentId())->where('business_date',$date)->first();
        $previous=DB::table('cash_closings')->where('parent_id',parentId())->where('business_date','<',$date)->orderByDesc('business_date')->first();
        $history=DB::table('cash_closings')->where('parent_id',parentId())->orderByDesc('business_date')->paginate(20)->withQueryString();
        return view('cash.index',compact('date','summary','closing','previous','history'));
    }
    public function close(Request $request,CashRegister $cash) {
        $this->checkAccess(true);
        $data=$request->validate(['business_date'=>'required|date_format:Y-m-d|before_or_equal:today',
            'opening'=>'required|numeric|min:0|max:999999999.99|regex:/^\d+(\.\d{1,2})?$/',
            'counted'=>'required|numeric|min:0|max:999999999.99|regex:/^\d+(\.\d{1,2})?$/',
            'source_hash'=>'required|string|size:64','note'=>'nullable|string|max:1000','confirm'=>'accepted']);
        $cash->close(parentId(),$data);return redirect()->route('report.cash',['date'=>$data['business_date']])->with('success','Gün sonu kasa kaydı oluşturuldu.');
    }
}
