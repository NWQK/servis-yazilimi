<?php
namespace App\Services;
use App\Models\Vehicle;
use Illuminate\Support\Facades\{DB,Schema};

class PublicVehicleHistory
{
    public const DISCLAIMER='Bu bilgiler işletmelerin kendi araç ve servis kayıtlarından derlenir. Hatalı şasi numarası veya işlem girişi bulunabilir; kayıtlar %100 doğrulanmış değildir. Resmî hasar kaydı, ekspertiz raporu veya aracın eksiksiz geçmişi değildir. Sonuçları ilgili işletmeden ve araç belgelerinden teyit edin.';

    public function sync(Vehicle $vehicle): void {
        if(!Schema::hasTable('vehicle_history_index'))return;
        DB::transaction(function()use($vehicle){
            $current=DB::table('vehicles')->where('id',$vehicle->id)->lockForUpdate()->first();
            if(!$current)return;
            $number=ChassisNumber::normalize($current->chassis_no);
            $hash=ChassisNumber::valid($number)?hash('sha256',$number):null;
            DB::table('vehicle_history_index')->updateOrInsert(['vehicle_id'=>$vehicle->id],
                ['parent_id'=>$current->parent_id,'vin_hash'=>$hash,
                 'is_public'=>$hash!==null && !$this->hidden($vehicle->id),'updated_at'=>now()]);
        });
        $this->captureVehicle($vehicle->id);
    }
    public function hidden(int $vehicle): bool {
        return Schema::hasTable('vehicle_history_controls') && (bool)DB::table('vehicle_history_controls')->where('vehicle_id',$vehicle)->value('hidden');
    }
    /** Businesses cannot override platform moderation, including legacy internal callers. */
    public function share(Vehicle $vehicle,bool $public): void {
        abort(403,'Görünürlük yalnız gerekçeli yönetici formundan değiştirilebilir.');
    }
    public function isPublic(Vehicle $vehicle): bool {
        return ChassisNumber::valid(ChassisNumber::normalize($vehicle->chassis_no)) && !$this->hidden($vehicle->id);
    }
    public function captureVehicle(int $vehicle): void {
        if(!Schema::hasTable('public_service_history'))return;
        DB::table('services as s')->join('vehicles as v',fn($j)=>$j->on('v.id','=','s.vehicle')->on('v.parent_id','=','s.parent_id'))
            ->where('v.id',$vehicle)->where('s.status','completed')
            ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('public_service_history')->whereColumn('service_id','s.id'))
            ->select('s.id')->orderBy('s.id')->chunkById(100,function($rows){foreach($rows as $row)$this->capture($row->id);},'s.id','id');
    }
    public function capture(int $service): void {
        if(!Schema::hasTable('public_service_history'))return;
        $seed=DB::table('services')->where('id',$service)->first(['parent_id']);if(!$seed)return;
        DB::transaction(function()use($service,$seed){
            DB::table('users')->where('id',$seed->parent_id)->lockForUpdate()->first();
            $job=DB::table('services')->where('id',$service)->where('parent_id',$seed->parent_id)->lockForUpdate()->first();
            if(!$job || $job->status!=='completed')return;
            $vehicle=DB::table('vehicles')->where('id',$job->vehicle)->where('parent_id',$job->parent_id)->lockForUpdate()->first();
            if(!$vehicle)return;
            $vin=ChassisNumber::normalize($vehicle->chassis_no);if(!ChassisNumber::valid($vin))return;
            $hash=hash('sha256',$vin);
            $existing=DB::table('public_service_history')->where('service_id',$service)->lockForUpdate()->first();
            if($existing && ($existing->vin_hash!==$hash || (int)$existing->vehicle_id!==(int)$vehicle->id || (int)$existing->parent_id!==(int)$job->parent_id))return;
            $operations=DB::table('service_items as i')->join('service_types as t',fn($j)=>$j->on('t.id','=','i.type_id')->on('t.parent_id','=','i.parent_id'))
                ->where('i.service_id',$service)->where('i.parent_id',$job->parent_id)->orderBy('i.id')->limit(100)->pluck('t.type')->map(fn($v)=>mb_substr(trim($v),0,120))->filter()->values()->all();
            if($existing){
                // Operation lines may be saved after completion. Append, never erase published types.
                $before=json_decode($existing->operations,true)?:[];
                $after=array_slice(array_values(array_unique(array_merge($before,$operations))),0,100);
                if($after!==$before)DB::table('public_service_history')->where('service_id',$service)->update(['operations'=>json_encode($after,JSON_UNESCAPED_UNICODE)]);
                return;
            }
            $business=InvoiceBilling::business((int)$job->parent_id)['name']??null;
            if(!trim($business??''))$business=DB::table('appointment_profiles')->where('owner_id',$job->parent_id)->value('display_name');
            $brand=DB::table('vehicle_types')->where('id',$vehicle->type)->where('parent_id',$job->parent_id)->value('type');
            $model=DB::table('vehicle_brands')->where('id',$vehicle->brand)->where('parent_id',$job->parent_id)->where('type',$vehicle->type)->value('name');
            $plate=preg_replace('/[^\pL\pN]/u','',mb_strtoupper($vehicle->license_plate??''));
            $date=$job->service_date;
            if($date && (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',$date,$parts) || !checkdate((int)$parts[2],(int)$parts[3],(int)$parts[1])))$date=null;
            DB::table('public_service_history')->insert(['service_id'=>$service,'vehicle_id'=>$vehicle->id,'parent_id'=>$job->parent_id,
                'vin_hash'=>$hash,'business_name'=>mb_substr(trim($business??'')?:'İşletme adı belirtilmedi',0,255),
                'vehicle_brand'=>$brand,'vehicle_model'=>$model??($vehicle->model??null),
                'plate_suffix'=>$plate!==''?mb_substr($plate,-3):null,
                'service_date'=>$date,'mileage_at_service'=>$job->mileage_at_service,
                'operations'=>json_encode(array_values(array_unique($operations)),JSON_UNESCAPED_UNICODE),'captured_at'=>now()]);
        });
    }
    public function results(string $hash) {
        $rows=DB::table('public_service_history as h')->leftJoin('vehicle_history_controls as c','c.vehicle_id','=','h.vehicle_id')
            ->where('h.vin_hash',$hash)->where(fn($q)=>$q->whereNull('c.vehicle_id')->orWhere('c.hidden',false))
            ->when(Schema::hasTable('business_recovery_locks'),fn($q)=>$q->whereNotExists(fn($q)=>$q->selectRaw('1')->from('business_recovery_locks')->whereColumn('owner_id','h.parent_id')))
            ->select(['h.service_id as id','h.parent_id','h.business_name','h.vehicle_brand','h.vehicle_model','h.plate_suffix','h.service_date','h.mileage_at_service','h.operations'])
            ->orderByDesc('h.service_date')->orderByDesc('h.service_id')->paginate(20);
        foreach($rows as $row)$row->operations=collect(json_decode($row->operations,true)?:[]);
        return $rows;
    }
}
