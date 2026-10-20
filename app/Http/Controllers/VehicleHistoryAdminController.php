<?php
namespace App\Http\Controllers;
use App\Services\{AdminAudit,ChassisNumber};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VehicleHistoryAdminController extends Controller {
    private function authorizeAdmin(): void { abort_unless(auth()->user()?->type==='super admin',403); }
    private function entities() {
        return DB::table('vehicles')->selectRaw('id as vehicle_id, parent_id')
            ->union(DB::table('public_service_history')->select('vehicle_id','parent_id'));
    }
    private function query() {
        return DB::query()->fromSub($this->entities(),'v')
            ->leftJoin('vehicles as live',fn($j)=>$j->on('live.id','=','v.vehicle_id')->on('live.parent_id','=','v.parent_id'))
            ->leftJoin('users as owner','owner.id','=','v.parent_id')
            ->leftJoin('vehicle_history_controls as c','c.vehicle_id','=','v.vehicle_id')
            ->select(['v.vehicle_id','v.parent_id','live.license_plate','live.chassis_no','owner.name as owner_name','c.hidden','c.reason','c.updated_at'])
            ->selectRaw('COALESCE(c.revision,0) as revision')
            ->selectSub(DB::table('public_service_history')->whereColumn('vehicle_id','v.vehicle_id')->whereColumn('parent_id','v.parent_id')->selectRaw('COUNT(*)'),'record_count');
    }
    public function index(Request $request) {
        $this->authorizeAdmin();
        $filters=$request->validate(['q'=>'nullable|string|max:150','status'=>'nullable|in:public,hidden']);
        $query=$this->query();
        if(!empty($filters['q'])){
            $term=$filters['q'];$vin=ChassisNumber::normalize($term);
            $query->where(function($q)use($term,$vin){
                $q->where('live.license_plate','like','%'.$term.'%')->orWhere('owner.name','like','%'.$term.'%');
                if(ChassisNumber::valid($vin))$q->orWhere('live.chassis_no',$vin)->orWhereExists(fn($s)=>$s->selectRaw('1')->from('public_service_history as h')->whereColumn('h.vehicle_id','v.vehicle_id')->where('h.vin_hash',hash('sha256',$vin)));
            });
        }
        if(($filters['status']??'')==='hidden')$query->where('c.hidden',true);
        if(($filters['status']??'')==='public')$query->where(fn($q)=>$q->whereNull('c.vehicle_id')->orWhere('c.hidden',false));
        $vehicles=$query->orderByDesc('v.vehicle_id')->paginate(25)->withQueryString();
        return response()->view('vehicle_history.admin.index',compact('vehicles','filters'))->header('Cache-Control','private, no-store');
    }
    public function show(int $vehicle) {
        $this->authorizeAdmin();$entry=$this->query()->where('v.vehicle_id',$vehicle)->first();abort_unless($entry,404);
        $records=DB::table('public_service_history')->where('vehicle_id',$vehicle)->orderByDesc('service_date')->orderByDesc('service_id')->paginate(20);
        return response()->view('vehicle_history.admin.show',compact('entry','records'))->header('Cache-Control','private, no-store');
    }
    public function update(Request $request,int $vehicle) {
        $this->authorizeAdmin();
        $data=$request->validate(['hidden'=>'required|boolean','reason'=>'required|string|min:5|max:500','expected_revision'=>'required|integer|min:0']);
        DB::transaction(function()use($vehicle,$data){
            $entity=DB::query()->fromSub($this->entities(),'v')->where('vehicle_id',$vehicle)->first();abort_unless($entity,404);
            DB::table('vehicle_history_controls')->insertOrIgnore(['vehicle_id'=>$vehicle,'hidden'=>false,'revision'=>0]);
            $before=DB::table('vehicle_history_controls')->where('vehicle_id',$vehicle)->lockForUpdate()->first();
            abort_unless((int)$before->revision===(int)$data['expected_revision'],409,'Başka bir yönetici görünürlüğü değiştirdi. Sayfayı yenileyin.');
            DB::table('vehicle_history_controls')->where('vehicle_id',$vehicle)->update([
                'hidden'=>(bool)$data['hidden'],'reason'=>$data['reason'],'actor_id'=>auth()->id(),
                'revision'=>(int)$before->revision+1,'updated_at'=>now()]);
            DB::table('vehicle_history_index')->where('vehicle_id',$vehicle)->update([
                'is_public'=>!(bool)$data['hidden'] && DB::table('vehicle_history_index')->where('vehicle_id',$vehicle)->whereNotNull('vin_hash')->exists(),
                'updated_at'=>now()]);
            AdminAudit::record('vehicle-history.visibility',$entity->parent_id,
                ['vehicle_id'=>$vehicle,'hidden'=>(bool)$before->hidden,'reason'=>$before->reason,'revision'=>$before->revision],
                ['vehicle_id'=>$vehicle,'hidden'=>(bool)$data['hidden'],'reason'=>$data['reason'],'revision'=>$before->revision+1]);
        });
        return redirect()->route('vehicle-history-admin.show',$vehicle)->with('success','Sorgu görünürlüğü güncellendi; yönetici işlem geçmişine kaydedildi.');
    }
}
