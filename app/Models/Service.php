<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $casts = ['external_labor_tax_rate' => 'decimal:4', 'external_labor_amount' => 'decimal:2','completed_at'=>'datetime'];

    protected $fillable = [
        'mileage_at_service',
        'external_labor_amount',
        'external_labor_tax_id',
        'external_labor_tax_rate',
        'external_labor_tax_title',
        'service_id',
        'vehicle',
        'client',
        'service_date',
        'service_time',
        'due_date',
        'due_time',
        'status',
        'notes',
        'assign',
        'parent_id',
    ];

    protected static function booted(): void
    {
        static::saving(function(self $service){
            if($service->exists && $service->isDirty('status') && $service->getOriginal('status')==='completed')
                \App\Services\PhotoRetention::markExpired((int)$service->parent_id,(int)$service->id);
            if(($service->getAttributes()['status']??null)==='completed' && (!$service->exists || $service->isDirty('status')))
                $service->completed_at=now();
        });
        static::creating(function(self $service){
            if (\Illuminate\Support\Facades\Schema::hasColumn('services','mileage_at_service') && $service->mileage_at_service===null)
                $service->mileage_at_service=Vehicle::where('parent_id',$service->parent_id)->whereKey($service->vehicle)->value('mileage');
        });
        static::deleting(fn(self $service)=>\App\Services\PhotoRetention::markExpired((int)$service->parent_id,(int)$service->id));
        static::deleted(function(self $service){
            $photos=ServicePhoto::where('parent_id',$service->parent_id)->where('service_id',$service->id)->get();
            ServicePhoto::where('parent_id',$service->parent_id)->where('service_id',$service->id)->delete();
            \Illuminate\Support\Facades\DB::afterCommit(function()use($photos){
                foreach($photos as $photo) if(preg_match('/\A[0-9]+_[0-9]+_[a-f0-9-]{36}\.jpg\z/',$photo->filename))
                    \Illuminate\Support\Facades\Storage::disk('service_photos')->delete('photo/'.$photo->filename);
            });
        });
    }

    public function photos() { return $this->hasMany(ServicePhoto::class)->visible(); }

    public function getExternalLaborTaxAmount(): float
    {
        return round((float) $this->external_labor_amount * (float) $this->external_labor_tax_rate / 100, 2);
    }

    public static function status()
    {
        return [
            'scheduled' => __('Scheduled'),
            'in_progress' => __('In Progress'),
            'completed' => __('Completed'),
            'pending_parts' => __('Pending Parts'),
            'on_hold' => __('On Hold'),
            'cancelled' => __('Cancelled'),
        ];
    }

    public function vehicles()
    {
        return $this->hasOne('App\Models\Vehicle', 'id', 'vehicle')->withTrashed();
    }
    public function clients()
    {
        return $this->hasOne('App\Models\User', 'id', 'client');
    }

    public function assigns()
    {
        return $this->hasOne('App\Models\User', 'id', 'assign');
    }

    public function types()
    {
        return $this->hasMany('App\Models\ServiceItem', 'service_id', 'id');
    }

    public function getServiceTotalTaxAmount()
    {
        $serviceTotalTax = $this->getExternalLaborTaxAmount();
        foreach ($this->types as $serviceItem) {
            if (empty($serviceItem->tax))
                continue;
            $taxIds = explode(',', $serviceItem->tax);
            $taxes = \App\Models\Tax::whereIn('id', $taxIds)->get();
            foreach ($taxes as $tax) {
                $serviceTotalTax += ($tax->rate / 100) * $serviceItem->rate;
            }
        }
        return $serviceTotalTax;
    }

    public function getServiceTaxBreakdown()
    {
        $breakdown = [];

        foreach ($this->types as $serviceItem) {
            if (empty($serviceItem->tax))
                continue;

            $taxIds = explode(',', $serviceItem->tax);
            $taxes = \App\Models\Tax::whereIn('id', $taxIds)->get();

            foreach ($taxes as $tax) {
                $taxPrice = ($tax->rate / 100) * $serviceItem->rate;


                if (isset($breakdown[$tax->id])) {
                    $breakdown[$tax->id]['price'] += $taxPrice;
                } else {
                    $breakdown[$tax->id] = [
                        'title' => $tax->title,
                        'rate' => $tax->rate,
                        'price' => $taxPrice,
                    ];
                }
            }
        }

        return $breakdown;
    }

    public static function taxRate($itemTaxRate, $itemPrice)
    {
        return ($itemTaxRate / 100) * $itemPrice;
    }
}
