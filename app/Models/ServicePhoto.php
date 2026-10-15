<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ServicePhoto extends Model
{
    protected $guarded=['id'];
    public function service() { return $this->belongsTo(Service::class); }
    public function scopeVisible($query)
    {
        $days=\App\Services\PhotoRetention::days();
        return $query->whereNotExists(fn($q)=>$q->selectRaw('1')->from('service_photo_deletions')->whereColumn('filename','service_photos.filename'))
            ->whereHas('service',fn($q)=>$q->whereColumn('services.parent_id','service_photos.parent_id')
                ->where(fn($q)=>$q->where('status','!=','completed')
                    ->orWhereRaw('COALESCE(services.completed_at, services.updated_at, services.created_at) > ?',[now()->subDays($days)])));
    }
}
