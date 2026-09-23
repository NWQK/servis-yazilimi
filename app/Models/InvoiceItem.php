<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $casts = ['item_snapshot' => 'array', 'stock_quantity' => 'integer'];

    public function getItemTitleAttribute()
    {
        return $this->item_snapshot['title'] ?? $this->items?->title ?? $this->description ?? 'Ürün';
    }

    protected $fillable=[
        'invoice_id',
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
