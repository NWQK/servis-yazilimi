<?php
namespace App\Http\Controllers;
use App\Services\VehicleCleanup;
use Illuminate\Http\Request;

class VehicleCleanupController extends Controller
{
    private function authorizeStaff(): void
    {
        abort_unless(auth()->user()->type!=='client' && auth()->user()->can('manage vehicle') && auth()->user()->can('delete vehicle') && auth()->user()->can('delete client'),403);
    }
    private function filters(Request $request): array
    {
        return $request->validate(['months'=>'nullable|in:3,6,12,all','view'=>'nullable|in:active,trash','search'=>'nullable|string|max:150','never_serviced'=>'nullable|boolean']);
    }
    public function index(Request $request,VehicleCleanup $cleanup)
    {
        $this->authorizeStaff(); $filters=$this->filters($request);
        $filters['months']=$filters['months'] ?? (($filters['view'] ?? '')==='trash' ? 'all' : '6');
        $vehicles=$cleanup->query((int)parentId(),$filters)->orderBy('last_activity_at')->orderBy('vehicles.id')->paginate(50)->withQueryString();
        return view('vehicle.cleanup',compact('vehicles','filters'));
    }
    public function destroy(Request $request,VehicleCleanup $cleanup)
    {
        $this->authorizeStaff(); $filters=$this->filters($request);
        $filters['view']='active';
        $data=$request->validate(['ids'=>'required|array|min:1|max:50','ids.*'=>'required|integer|distinct','confirm'=>'accepted']);
        $result=$cleanup->remove((int)parentId(),$data['ids'],$filters);
        return redirect()->route('vehicle-cleanup.index',$filters)->with('success',$result['vehicles'].' araç ve '.$result['clients'].' müşteri kaydı silinenlere taşındı. Fatura, servis, ödeme ve stok kayıtları korundu.');
    }
    public function restore(Request $request,VehicleCleanup $cleanup)
    {
        $this->authorizeStaff(); abort_unless(auth()->user()->can('create vehicle') && auth()->user()->can('create client'),403);
        $data=$request->validate(['ids'=>'required|array|min:1|max:50','ids.*'=>'required|integer|distinct','confirm'=>'accepted']);
        $count=$cleanup->restore((int)parentId(),$data['ids']);
        return redirect()->route('vehicle-cleanup.index',['view'=>'trash','months'=>'all'])->with('success',$count.' araç ve ilgili müşteri kayıtları geri getirildi.');
    }
}
