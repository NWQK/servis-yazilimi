<?php
namespace App\Services;
use App\Models\{Vehicle,User,Service,Invoice};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VehicleCleanup
{
    public function query(int $ownerId,array $filters=[])
    {
        // UNION + MAX works on both MySQL and SQLite. Financial activity also keeps a vehicle recent.
        $events=DB::table('vehicles')->where('parent_id',$ownerId)->select('id as vehicle_id')->selectRaw('created_at as activity_at');
        foreach (['updated_at','last_service_date'] as $column) {
            $events->unionAll(DB::table('vehicles')->where('parent_id',$ownerId)->select('id as vehicle_id')->selectRaw($column.' as activity_at'));
        }
        foreach (['created_at','updated_at','service_date','due_date'] as $column) {
            $events->unionAll(DB::table('services')->where('parent_id',$ownerId)->select('vehicle as vehicle_id')->selectRaw($column.' as activity_at'));
        }
        foreach (['created_at','updated_at','invoice_date'] as $column) {
            $events->unionAll(DB::table('invoices as i')->join('services as s','s.id','=','i.service')->where('i.parent_id',$ownerId)->where('s.parent_id',$ownerId)->select('s.vehicle as vehicle_id')->selectRaw('i.'.$column.' as activity_at'));
        }
        foreach (['created_at','updated_at','payment_date'] as $column) {
            $events->unionAll(DB::table('invoice_payments as p')->join('invoices as i','i.id','=','p.invoice_id')->join('services as s','s.id','=','i.service')->where('p.parent_id',$ownerId)->where('i.parent_id',$ownerId)->where('s.parent_id',$ownerId)->select('s.vehicle as vehicle_id')->selectRaw('p.'.$column.' as activity_at'));
        }
        foreach (['created_at','updated_at','quotation_date'] as $column) {
            $events->unionAll(DB::table('quotations')->where('parent_id',$ownerId)->select('vehicle_id')->selectRaw($column.' as activity_at'));
        }
        $latest=DB::query()->fromSub($events,'events')->select('vehicle_id')->selectRaw('MAX(activity_at) as last_activity_at')->groupBy('vehicle_id');
        $query=Vehicle::where('vehicles.parent_id',$ownerId)->leftJoinSub($latest,'activity','activity.vehicle_id','=','vehicles.id')
            ->select('vehicles.*','activity.last_activity_at')->with(['clients','types','brands'])
            ->withCount(['services'=>fn($q)=>$q->where('parent_id',$ownerId)])
            ->withMax(['services'=>fn($q)=>$q->where('parent_id',$ownerId)],'service_date')
            ->selectSub(Invoice::selectRaw('COUNT(*)')->join('services as invoice_services','invoice_services.id','=','invoices.service')->where('invoices.parent_id',$ownerId)->where('invoice_services.parent_id',$ownerId)->whereColumn('invoice_services.vehicle','vehicles.id'),'invoice_count')
            ->withCount(['services as open_service_count'=>fn($q)=>$q->where('parent_id',$ownerId)->where(fn($q)=>$q->whereNull('status')->orWhereNotIn('status',['completed','cancelled']))]);
        if (($filters['view'] ?? '')==='trash') { $query->onlyTrashed(); }
        if (!empty($filters['never_serviced'])) { $query->whereDoesntHave('services',fn($q)=>$q->where('parent_id',$ownerId)); }
        $months=$filters['months'] ?? '6';
        if ($months!=='all') { $query->where('activity.last_activity_at','<=',now()->subMonthsNoOverflow((int)$months)->toDateTimeString()); }
        if (!empty($filters['search'])) {
            $search=$filters['search'];
            $query->where(fn($q)=>$q->where('license_plate','like','%'.$search.'%')->orWhereHas('clients',fn($q)=>$q->where('name','like','%'.$search.'%')));
        }
        return $query;
    }

    public function remove(int $ownerId,array $ids,?array $filters=null): array
    {
        return DB::transaction(function() use ($ownerId,$ids,$filters) {
            User::whereKey($ownerId)->lockForUpdate()->firstOrFail();
            $vehicles=Vehicle::where('parent_id',$ownerId)->whereIn('id',$ids)->orderBy('id')->lockForUpdate()->get();
            if ($vehicles->count()!==count($ids)) { throw ValidationException::withMessages(['ids'=>'Seçilen araçlardan biri silinmiş veya bu işletmeye ait değil. Listeyi yenileyin.']); }
            if ($filters!==null && $this->query($ownerId,$filters)->whereIn('vehicles.id',$ids)->count()!==count($ids)) {
                throw ValidationException::withMessages(['ids'=>'Seçilen araçlardan birinde yeni işlem var veya filtre artık uyuşmuyor. Listeyi yenileyin.']);
            }
            if (Service::where('parent_id',$ownerId)->whereIn('vehicle',$ids)->where(fn($q)=>$q->whereNull('status')->orWhereNotIn('status',['completed','cancelled']))->exists()) {
                throw ValidationException::withMessages(['ids'=>'Açık servisi bulunan araçlar silinemez. Önce servis işlemini tamamlayın veya iptal edin.']);
            }
            $clients=$vehicles->pluck('client')->unique();
            foreach ($vehicles as $vehicle) { $vehicle->delete(); }
            $deletedClients=0;
            foreach ($clients as $clientId) {
                if (Vehicle::where('parent_id',$ownerId)->where('client',$clientId)->exists()) { continue; }
                $client=User::where('parent_id',$ownerId)->where('type','client')->whereNull('client_archived_at')->whereKey($clientId)->lockForUpdate()->first();
                if ($client) {
                    $client->client_archived_at=now()->toDateTimeString(); $client->client_archive_was_active=$client->is_active;
                    $client->is_active=0; $client->save(); $deletedClients++;
                }
            }
            return ['vehicles'=>$vehicles->count(),'clients'=>$deletedClients];
        });
    }

    public function restore(int $ownerId,array $ids): int
    {
        return DB::transaction(function() use ($ownerId,$ids) {
            User::whereKey($ownerId)->lockForUpdate()->firstOrFail();
            $vehicles=Vehicle::onlyTrashed()->where('parent_id',$ownerId)->whereIn('id',$ids)->lockForUpdate()->get();
            if ($vehicles->count()!==count($ids)) { throw ValidationException::withMessages(['ids'=>'Silinen araç listesi değişti. Listeyi yenileyin.']); }
            foreach ($vehicles as $vehicle) {
                app(VehicleCapacity::class)->assertAvailable($ownerId);
                $vehicle->restore();
                $client=User::where('type','client')->where('parent_id',$ownerId)->whereKey($vehicle->client)->lockForUpdate()->first();
                if ($client && $client->client_archived_at!==null) {
                    $client->client_archived_at=null; $client->is_active=$client->client_archive_was_active ?? 0;
                    $client->client_archive_was_active=null; $client->save();
                }
            }
            return $vehicles->count();
        });
    }
}
