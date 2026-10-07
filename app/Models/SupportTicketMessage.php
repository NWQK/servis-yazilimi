<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class SupportTicketMessage extends Model
{
    protected $guarded=['id'];
    protected $casts=['is_admin'=>'boolean'];
    public function author() {return $this->belongsTo(User::class,'author_id');}
}
