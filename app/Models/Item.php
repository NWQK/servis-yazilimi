<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    use HasFactory;
    protected $fillable=[
        'title',
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
