<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Announcement extends Model {
 protected $guarded=['id']; protected $casts=['published'=>'boolean'];
 public function recipients(){return $this->hasMany(AnnouncementRecipient::class);}
}
