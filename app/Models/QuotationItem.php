<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotationItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'item',
        'quantity',
        'amount',
        'tax',
        'description',
        'parent_id',
    ];

    public function items()
    {
        return $this->hasOne('App\Models\Item', 'id', 'item');
    }
}
