<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SubscriptionChange extends Model
{
    protected $guarded=['id'];
    protected $casts=['before'=>'array','after'=>'array'];
    public function actor() { return $this->belongsTo(User::class,'actor_id'); }
}
