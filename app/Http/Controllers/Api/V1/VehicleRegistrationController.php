<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{User,Vehicle,VehicleType,VehicleBrand};
use App\Services\{ChassisNumber,VehicleRegistration};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VehicleRegistrationController extends Controller
{
    private function owner(Request $request): int {
        abort_unless($request->user()->can('create vehicle'),403);
        return (int)$request->attributes->get('mobile_owner');
    }
    public function lookup(Request $request,string $kind) {
        $owner=$this->owner($request);
        $input=$request->validate(['q'=>'nullable|string|max:100','page'=>'sometimes|integer|min:1',
            'id'=>'sometimes|integer|min:1','type'=>[$kind==='vehicle-brands'?'required':'sometimes','integer',Rule::exists('vehicle_types','id')->where('parent_id',$owner)]]);
        [$query,$column]=match($kind){
            'vehicle-customers'=>[User::where('type','client')->whereNull('client_archived_at'),'name'],
            'vehicle-types'=>[VehicleType::query(),'type'],
            'vehicle-brands'=>[VehicleBrand::where('type',$input['type']),'name'],
        };
        $query->where('parent_id',$owner);
        if (isset($input['id'])) $query->whereKey($input['id']);
        if (!empty($input['q'])) $query->where($column,'like','%'.$input['q'].'%');
        $page=$query->orderBy($column)->orderBy('id')->simplePaginate(20,['id',$column]);
        return response()->json(['data'=>collect($page->items())->map(fn($row)=>['id'=>$row->id,'label'=>$row->$column]),
            'meta'=>['page'=>$page->currentPage(),'has_more'=>$page->hasMorePages()]]);
    }
    public function store(Request $request) {
        $owner=$this->owner($request);
        $key=$request->header('Idempotency-Key');validator(['key'=>$key],['key'=>'required|uuid'])->validate();
        $payload=$request->only(['client','type','brand','license_plate','chassis_no','color','fuel_type','mileage','notes']);
        if (array_key_exists('chassis_no',$payload)) $payload['chassis_no']=ChassisNumber::normalize($payload['chassis_no']);
        ksort($payload);
        $hash=hash('sha256',json_encode([(int)User::findOrFail($owner)->recovery_version,$payload]));
        $previous=DB::table('mobile_vehicle_requests')->where('owner_id',$owner)->where('actor_id',$request->user()->id)->where('request_key',$key)->lockForUpdate()->first();
        if ($previous) {
            abort_unless(hash_equals($previous->payload_hash,$hash),409,'İşlem anahtarı farklı araç bilgileriyle kullanıldı.');
            abort_unless(Vehicle::where('parent_id',$owner)->whereKey($previous->vehicle_id)->exists(),409,'Önceki araç kaydı artık aktif değil.');
            return response()->json(['data'=>['id'=>$previous->vehicle_id],'meta'=>['replayed'=>true]]);
        }
        $request->replace($payload);
        $request->validate(['client'=>'required|integer','type'=>'required|integer','brand'=>'required|integer','license_plate'=>'required|string|max:20','notes'=>'nullable|string|max:5000']);
        ChassisNumber::validateForm($request);
        $data=$request->validate(app(VehicleRegistration::class)->rules($owner,$request));
        $request->replace($data);
        $vehicle=app(VehicleRegistration::class)->create($owner,$request);
        DB::table('mobile_vehicle_requests')->insert(['owner_id'=>$owner,'actor_id'=>$request->user()->id,'vehicle_id'=>$vehicle->id,
            'request_key'=>$key,'payload_hash'=>$hash,'created_at'=>now(),'updated_at'=>now()]);
        return response()->json(['data'=>['id'=>$vehicle->id],'meta'=>['replayed'=>false]],201);
    }
}
