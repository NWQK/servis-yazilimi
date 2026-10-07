<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AdminAuditLog extends Model {
 public $timestamps=false; protected $guarded=['id']; protected $casts=['before'=>'array','after'=>'array','created_at'=>'datetime'];
 public function owner(){return $this->belongsTo(User::class,'owner_id');}
}
