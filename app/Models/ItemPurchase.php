<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class ItemPurchase extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['purchase_date'=>'date', 'quantity'=>'integer', 'unit_price'=>'decimal:2', 'amount'=>'decimal:2'];
    public function scopeRetained($query) { return $query->where('created_at', '>=', now()->subYears(2)); }
}
