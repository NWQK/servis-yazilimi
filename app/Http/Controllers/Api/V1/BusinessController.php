<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\{User,Vehicle,Service};
use App\Services\ClientRegistration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class BusinessController extends Controller {
    private function query(Request $r,string $kind) {
        $owner=(int)$r->attributes->get('mobile_owner');
        $query=match($kind) {
            'customers'=>User::where('type','client')->whereNull('client_archived_at'),
            'vehicles'=>Vehicle::query(), 'services'=>Service::query(),
        };
        $query->where('parent_id',$owner);
        if($kind==='services' && $r->user()->type==='employee')$query->where('assign',$r->user()->id);
        return $query;
    }
    private function module(string $kind): string { return ['customers'=>'client','vehicles'=>'vehicle','services'=>'service'][$kind]; }
    private function fields(string $kind,bool $detail=false): array {
        $fields=match($kind) {
            'customers'=>['id','name','phone_number','contact_email'],
            'vehicles'=>['id','license_plate','color','mileage','fuel_type'],
            'services'=>['id','service_id','status','service_date','due_date'],
        };
        if($detail && $kind!=='customers')$fields[]='notes';return $fields;
    }
    private function selectFields($query,string $kind,bool $detail=false) {
        $query->select($this->fields($kind,$detail));
        if($kind==='vehicles') {
            $query->selectRaw("COALESCE((SELECT name FROM vehicle_brands WHERE vehicle_brands.id=vehicles.brand AND vehicle_brands.parent_id=vehicles.parent_id LIMIT 1), vehicles.model) AS model");
            $query->selectSub(DB::table('vehicle_types')->select('type')->whereColumn('vehicle_types.id','vehicles.type')->whereColumn('vehicle_types.parent_id','vehicles.parent_id')->limit(1),'manufacturer');
        }
        if($detail && $kind!=='customers') {
            $table=$kind==='vehicles'?'vehicles':'services';
            $query->selectSub(DB::table('users')->select('name')->where('type','client')->whereColumn('users.id',$table.'.client')->whereColumn('users.parent_id',$table.'.parent_id')->limit(1),'customer_name');
        }
        if($kind==='services') {
            $query->selectSub(DB::table('vehicles')->select('license_plate')->whereColumn('vehicles.id','services.vehicle')->whereColumn('vehicles.parent_id','services.parent_id')->limit(1),'vehicle_plate');
        }
        // Query-builder rows avoid legacy model accessors lazy-loading unrelated records.
        return $query->toBase();
    }
    public function index(Request $r,string $kind) {
        abort_unless($r->user()->can('manage '.$this->module($kind)),403);
        $data=$r->validate(['q'=>'nullable|string|max:100','page'=>'sometimes|integer|min:1','per_page'=>'sometimes|integer|min:1|max:50']);
        $query=$this->query($r,$kind);
        if(!empty($data['q'])) {
            $search=$data['q'];
            $columns=match($kind){'customers'=>['name','phone_number'],'vehicles'=>['license_plate','model'],'services'=>['service_id','status']};
            $query->where(function($q)use($columns,$search){foreach($columns as $col)$q->orWhere($col,'like','%'.$search.'%');});
        }
        $page=$this->selectFields($query,$kind)->orderByDesc('id')->simplePaginate($data['per_page']??20);
        return response()->json(['data'=>$page->items(),'meta'=>['page'=>$page->currentPage(),'has_more'=>$page->hasMorePages()]]);
    }
    public function show(Request $r,string $kind,int $id) {
        abort_unless($r->user()->can('show '.$this->module($kind)),403);
        $item=$this->selectFields($this->query($r,$kind),$kind,true)->where('id',$id)->first();
        abort_unless($item,404);
        $data=(array)$item;
        if($kind==='customers')$data['profile']=DB::table('clients')->where('parent_id',$r->attributes->get('mobile_owner'))->where('user_id',$id)->first(['city','state','address','notes']);
        return response()->json(['data'=>$data]);
    }
    public function dashboard(Request $r) {
        $counts=[];
        foreach(['customers','vehicles','services'] as $kind)if($r->user()->can('manage '.$this->module($kind)))$counts[$kind]=$this->query($r,$kind)->count();
        return response()->json(['data'=>$counts]);
    }
    public function storeCustomer(Request $r) {
        abort_unless($r->user()->can('create client'),403);
        $data=$r->validate(['name'=>'required|string|max:255','phone_number'=>['required','string','regex:/\A5[0-9]{9}\z/'],
            'email'=>'nullable|email|max:255','city'=>'nullable|string|max:255','state'=>'nullable|string|max:255',
            'address'=>'nullable|string|max:1000','notes'=>'nullable|string|max:5000']);
        $key=$r->header('Idempotency-Key');
        validator(['key'=>$key],['key'=>'required|uuid'])->validate();
        ksort($data);$owner=$r->attributes->get('mobile_owner');
        $recoveryVersion=(int)User::whereKey($owner)->value('recovery_version');
        $hash=hash('sha256',json_encode([$recoveryVersion,$data]));
        $existing=DB::table('mobile_requests')->where('owner_id',$owner)->where('actor_id',$r->user()->id)->where('request_key',$key)->lockForUpdate()->first();
        if($existing){abort_unless(hash_equals($existing->payload_hash,$hash),409,'İşlem anahtarı farklı bilgilerle kullanılmış.');return response()->json(['data'=>['id'=>$existing->customer_id]],200);}
        $customer=app(ClientRegistration::class)->create($owner,$data);
        DB::table('mobile_requests')->insert(['owner_id'=>$owner,'actor_id'=>$r->user()->id,'request_key'=>$key,'payload_hash'=>$hash,'customer_id'=>$customer->id,'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['data'=>['id'=>$customer->id]],201);
    }
}
