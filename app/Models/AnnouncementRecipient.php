<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AnnouncementRecipient extends Model {
 public $timestamps=false; protected $guarded=['id']; protected $casts=['read_at'=>'datetime'];
 public function announcement(){return $this->belongsTo(Announcement::class);}
 public function owner(){return $this->belongsTo(User::class,'owner_id');}
}
