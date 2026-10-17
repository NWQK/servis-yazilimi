<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BranchStockTransfer extends Model
{
    protected $guarded=[];
    protected $casts=['snapshot'=>'array','quantity'=>'integer','version'=>'integer','resolved_at'=>'datetime'];
    public function source() { return $this->belongsTo(User::class,'source_owner_id'); }
    public function target() { return $this->belongsTo(User::class,'target_owner_id'); }
}
