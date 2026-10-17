<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;
    protected static function booted(): void
    {
        $pending=function(self $item): bool {
            return \Schema::hasTable('branch_stock_transfers') && \DB::table('branch_stock_transfers')
                ->where('source_owner_id',$item->getOriginal('parent_id') ?: $item->parent_id)->where('source_item_id',$item->id)->where('status','pending')->exists();
        };
        static::deleting(function(self $item)use($pending){
            if($pending($item)) throw \Illuminate\Validation\ValidationException::withMessages(['item'=>'Bekleyen transferi olan ürün silinemez. Önce transferi sonuçlandırın.']);
        });
        static::deleted(function(self $item){
            if(\Schema::hasTable('inventory_checkpoints'))\DB::table('inventory_checkpoints')->where('parent_id',$item->parent_id)->where('inventory_key',$item->inventory_key)->delete();
        });
        static::updating(function(self $item)use($pending){
            if(($item->isDirty('units') || $item->isDirty('parent_id') || $item->isDirty('inventory_key')) && $pending($item))
                throw \Illuminate\Validation\ValidationException::withMessages(['item'=>'Bekleyen transfer varken ürünün birimi veya stok kimliği değiştirilemez.']);
        });
    }
    protected $fillable=[
        'title',
        'category_id',
        'supplier_id',
        'stock_alert_quantity',
        'item_code',
        'quantity',
        'units',
        'purchase_price',
        'sales_price',
        'manufacturer_by',
        'taxs',
        'purchase_date',
        'warranty_information',
        'notes',
        'parent_id',
    ];

    protected $casts = ['stock_alert_quantity'=>'integer'];

    public function supplier() { return $this->belongsTo(Supplier::class); }
    public function scopeLowStock($query)
    {
        return $query->whereNotNull('stock_alert_quantity')->whereColumn('quantity', '<=', 'stock_alert_quantity');
    }

    public function category()
    {
        return $this->belongsTo(ItemCategory::class, 'category_id');
    }

    public function unit()
    {
        return $this->hasOne('App\Models\Unit','id','units');
    }

    public static function taxes($tax)
    {
        $taxes=Tax::whereIn('id',explode(',',$tax))->get();
        return $taxes;
    }

    public static function taxRate($taxes)
    {
        $taxArr  = explode(',', $taxes);
        $taxRate = 0;
        foreach($taxArr as $tax)
        {
            $tax     = Tax::find($tax);
            $taxRate += $tax?->rate ?? 0;
        }

        return $taxRate;
    }
}
