<?php
namespace App\Services;
use App\Models\Vehicle;
use Illuminate\Support\Facades\{DB,Schema};

class PublicVehicleHistory
{
    public const DISCLAIMER='Bu bilgiler işletmelerin kendi araç ve servis kayıtlarından derlenir. Hatalı şasi numarası veya işlem girişi bulunabilir; kayıtlar %100 doğrulanmış değildir. Resmî hasar kaydı, ekspertiz raporu veya aracın eksiksiz geçmişi değildir. Sonuçları ilgili işletmeden ve araç belgelerinden teyit edin.';

    public function sync(Vehicle $vehicle): void
    {
        if (!Schema::hasTable('vehicle_history_index')) return;
        $number=ChassisNumber::normalize($vehicle->chassis_no);
        $hash=ChassisNumber::valid($number) ? hash('sha256',$number) : null;
        $previous=DB::table('vehicle_history_index')->where('vehicle_id',$vehicle->id)->first();
        $public=$previous && $previous->vin_hash===$hash && $hash!==null && !$vehicle->trashed() ? (bool)$previous->is_public : false;
        DB::table('vehicle_history_index')->updateOrInsert(['vehicle_id'=>$vehicle->id],
            ['parent_id'=>$vehicle->parent_id,'vin_hash'=>$hash,'is_public'=>$public,'updated_at'=>now()]);
    }
    public function share(Vehicle $vehicle,bool $public): void
    {
        DB::transaction(function() use($vehicle,$public){
            \App\Models\User::whereKey($vehicle->parent_id)->lockForUpdate()->firstOrFail();
            $vehicle=Vehicle::where('parent_id',$vehicle->parent_id)->lockForUpdate()->findOrFail($vehicle->id);
            $this->sync($vehicle);
            DB::table('vehicle_history_index')->where('vehicle_id',$vehicle->id)->where('parent_id',$vehicle->parent_id)
                ->whereNotNull('vin_hash')->update(['is_public'=>$public,'updated_at'=>now()]);
        },5);
    }
    public function isPublic(Vehicle $vehicle): bool
    {
        return Schema::hasTable('vehicle_history_index') && DB::table('vehicle_history_index')
            ->where('vehicle_id',$vehicle->id)->where('parent_id',$vehicle->parent_id)->where('is_public',true)->exists();
    }
    public function results(string $hash)
    {
        // Only explicitly shared, completed services. No customer, invoice or private note columns are selected.
        $rows=DB::table('vehicle_history_index as h')->join('vehicles as v',function($j){$j->on('v.id','=','h.vehicle_id')->on('v.parent_id','=','h.parent_id');})
            ->join('services as s',function($j){$j->on('s.vehicle','=','v.id')->on('s.parent_id','=','h.parent_id');})
            ->join('users as owner',fn($j)=>$j->on('owner.id','=','h.parent_id')->where('owner.type','owner'))
            ->where('h.vin_hash',$hash)->where('h.is_public',true)->whereNull('v.deleted_at')->where('s.status','completed')
            ->select(['s.id','s.parent_id','s.service_date','s.mileage_at_service'])
            ->selectSub(DB::table('settings')->select('value')->whereColumn('parent_id','h.parent_id')->where('name','company_name')->limit(1),'business_name')
            ->selectSub(DB::table('appointment_profiles')->select('display_name')->whereColumn('owner_id','h.parent_id')->limit(1),'profile_name')
            ->orderByDesc('s.service_date')->orderByDesc('s.id')->paginate(20);
        $types=DB::table('service_items as i')->join('service_types as t',fn($j)=>$j->on('t.id','=','i.type_id')->on('t.parent_id','=','i.parent_id'))
            ->join('services as s',fn($j)=>$j->on('s.id','=','i.service_id')->on('s.parent_id','=','i.parent_id'))
            ->whereIn('i.service_id',$rows->pluck('id'))->select(['i.service_id','t.type'])->get()->groupBy('service_id');
        foreach ($rows as $row) {
            $row->business_name=trim($row->business_name ?? '') ?: ($row->profile_name ?: 'İşletme adı belirtilmedi');
            $row->operations=$types->get($row->id,collect())->pluck('type')->filter()->unique()->values();
        }
        return $rows;
    }
}
