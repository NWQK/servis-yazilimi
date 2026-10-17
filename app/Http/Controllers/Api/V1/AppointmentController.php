<?php
namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Appointment,AppointmentProfile,User};
use App\Services\{AppointmentBooking,AppointmentSms,MobileAppointmentView};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    private function owner(Request $request): int {
        abort_unless($request->user()->type==='owner',403,'Randevuları yalnızca işletme sahibi yönetebilir.');
        return (int)$request->attributes->get('mobile_owner');
    }
    private function query(int $owner) {
        return Appointment::whereHas('profile',fn($q)=>$q->where('owner_id',$owner));
    }
    public function index(Request $request) {
        $owner=$this->owner($request);
        $input=$request->validate(['status'=>['nullable',Rule::in(array_keys(Appointment::statuses()))],
            'date'=>'nullable|date_format:Y-m-d','q'=>'nullable|string|max:100',
            'page'=>'sometimes|integer|min:1','per_page'=>'sometimes|integer|min:1|max:50']);
        $query=$this->query($owner);
        // Present expired pending requests accurately without mutating data in GET.
        $effective="CASE WHEN status = 'pending' AND (starts_at <= ? OR pending_expires_at <= ?) THEN 'expired' ELSE status END";
        $clock=now()->toDateTimeString();
        if (!empty($input['status'])) $query->whereRaw('('.$effective.') = ?',[$clock,$clock,$input['status']]);
        if (!empty($input['date'])) $query->whereDate('starts_at',$input['date']);
        if (!empty($input['q'])) $query->where(fn($q)=>$q->where('customer_name','like','%'.$input['q'].'%')
            ->orWhere('license_plate','like','%'.$input['q'].'%')->orWhere('phone','like','%'.$input['q'].'%'));
        $page=$query->orderByRaw("CASE (".$effective.") WHEN 'pending' THEN 0 WHEN 'approved' THEN 1 ELSE 2 END",[$clock,$clock])->orderBy('starts_at')->orderBy('id')->simplePaginate($input['per_page']??20,
            ['id','customer_name','license_plate','requested_service','requested_vehicle','status','starts_at','ends_at','pending_expires_at']);
        return response()->json(['data'=>collect($page->items())->map(fn($a)=>app(MobileAppointmentView::class)->data($a)),
            'meta'=>['page'=>$page->currentPage(),'has_more'=>$page->hasMorePages(),'statuses'=>Appointment::statuses()]]);
    }
    public function show(Request $request,int $id) {
        $appointment=$this->query($this->owner($request))->findOrFail($id);
        return response()->json(['data'=>app(MobileAppointmentView::class)->data($appointment,true)]);
    }
    public function status(Request $request,int $id) {
        $owner=$this->owner($request);
        $input=$request->validate(['status'=>['required',Rule::in(['approved','rejected','cancelled','completed','no_show'])],
            'expected_version'=>'required|string|size:64']);
        $key=$request->header('Idempotency-Key');validator(['key'=>$key],['key'=>'required|uuid'])->validate();
        $hash=hash('sha256',json_encode([$id,$input['status'],$input['expected_version'],(int)User::findOrFail($owner)->recovery_version]));
        $profile=AppointmentProfile::where('owner_id',$owner)->lockForUpdate()->firstOrFail();
        $appointment=$profile->appointments()->whereKey($id)->lockForUpdate()->firstOrFail();
        $previous=DB::table('mobile_appointment_operations')->where('owner_id',$owner)->where('actor_id',$request->user()->id)
            ->where('request_key',$key)->lockForUpdate()->first();
        if ($previous) {
            abort_unless(hash_equals($previous->payload_hash,$hash),409,'İşlem anahtarı farklı bilgilerle kullanıldı.');
            return response()->json(['data'=>app(MobileAppointmentView::class)->data($appointment,true),'meta'=>['replayed'=>true]]);
        }
        abort_unless(hash_equals(app(MobileAppointmentView::class)->version($appointment),$input['expected_version']),409,
            'Randevu başka bir işlemle değişti. Güncel bilgileri yenileyin.');
        $appointment=app(AppointmentBooking::class)->changeStatus($profile,$id,$input['status']);
        DB::table('mobile_appointment_operations')->insert(['owner_id'=>$owner,'actor_id'=>$request->user()->id,
            'appointment_id'=>$id,'request_key'=>$key,'payload_hash'=>$hash,'created_at'=>now(),'updated_at'=>now()]);
        if ($input['status']==='approved') DB::afterCommit(fn()=>app(AppointmentSms::class)->dispatchApproval($id));
        return response()->json(['data'=>app(MobileAppointmentView::class)->data($appointment,true),'meta'=>['replayed'=>false]]);
    }
}
