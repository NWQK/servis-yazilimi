<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BusinessReview extends Model
{
    protected $guarded = [];
    protected $hidden = ['token','identity_hash'];
    protected $casts = ['rating'=>'integer','rated_at'=>'datetime'];
    public function owner() { return $this->belongsTo(User::class,'owner_id'); }
    public function publicUrl(): string { return route('reviews.show', $this->token); }
}
