<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BusinessRecovery extends Model {
 public $incrementing=false;protected $keyType='string';protected $guarded=[];
 protected $casts=['summary'=>'array','requested_at'=>'datetime','started_at'=>'datetime','finished_at'=>'datetime','expires_at'=>'datetime','checkpoint_expires_at'=>'datetime'];
 public function owner(){return $this->belongsTo(User::class,'owner_id');}
 public function backup(){return $this->belongsTo(PlatformBackup::class,'backup_id');}
 public function sourceRecovery(){return $this->belongsTo(self::class,'source_recovery_id');}
}
