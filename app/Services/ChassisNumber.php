<?php
namespace App\Services;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Validation\ValidationException;

class ChassisNumber
{
    public static function normalize($value): ?string
    {
        if (!is_string($value)) return null;
        $value=strtoupper(preg_replace('/[\s-]+/u','',$value));
        return $value==='' ? null : $value;
    }
    public static function valid(?string $value): bool
    {
        return $value!==null && preg_match('/\A[A-Z0-9]{4,32}\z/D',$value)===1
            && (strlen($value)!==17 || !preg_match('/[IOQ]/',$value));
    }
    public static function rules(bool $required=true): array
    {
        return [$required ? 'required' : 'nullable','string',function($attribute,$value,$fail){
            if (!self::valid(self::normalize($value))) $fail('Şasi numarası 4–32 harf/rakam olmalıdır. Standart 17 haneli numaralarda I, O ve Q harfleri kullanılmaz.');
        }];
    }
    public static function validateForm(Request $request, ?Vehicle $vehicle=null): void
    {
        if ($request->exists('chassis_no')) $request->merge(['chassis_no'=>self::normalize($request->input('chassis_no'))]);
        $request->validate(['chassis_no'=>self::rules(!$vehicle || !empty($vehicle->chassis_no)),
            'history_public'=>'nullable|boolean','chassis_change_confirm'=>'sometimes|accepted']);
        $number=self::normalize($request->input('chassis_no'));
        if ($request->boolean('history_public') && !self::valid($number)) throw ValidationException::withMessages(['chassis_no'=>'Servis geçmişini paylaşmak için önce geçerli şasi numarasını girin.']);
        if ($vehicle && !empty($vehicle->chassis_no) && self::normalize($vehicle->chassis_no)!==$number
            && !$request->boolean('chassis_change_confirm')) {
            throw ValidationException::withMessages(['chassis_change_confirm'=>'Şasi değişikliği servis geçmişinin eşleşmesini değiştirir. Ruhsattan kontrol edip değişikliği onaylayın.']);
        }
        if ($number && Schema::hasTable('vehicle_history_index')) {
            $duplicate=DB::table('vehicle_history_index as h')->join('vehicles as v','v.id','=','h.vehicle_id')
                ->where('h.parent_id',parentId())->where('v.parent_id',parentId())->whereNull('v.deleted_at')
                ->where('h.vin_hash',hash('sha256',$number))->when($vehicle,fn($q)=>$q->where('v.id','!=',$vehicle->id))->exists();
            if ($duplicate) throw ValidationException::withMessages(['chassis_no'=>'Bu şasi numarası işletmenizde zaten kayıtlı. Araç listesindeki mevcut kaydı kullanın.']);
        }
    }
    public static function assertUnique(Vehicle $vehicle): void
    {
        $vin=self::normalize($vehicle->chassis_no);
        if (!$vin || !Schema::hasTable('vehicle_history_index')) return;
        $exists=DB::table('vehicle_history_index as h')->join('vehicles as v','v.id','=','h.vehicle_id')
            ->where('h.parent_id',$vehicle->parent_id)->where('v.parent_id',$vehicle->parent_id)->whereNull('v.deleted_at')
            ->where('h.vin_hash',hash('sha256',$vin))->where('v.id','!=',$vehicle->id)->exists();
        if ($exists) throw ValidationException::withMessages(['chassis_no'=>'Bu şasi numarası işletmenizde zaten kayıtlı. Mevcut araç kaydını kullanın.']);
    }

}
