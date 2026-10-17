<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessGroup extends Model
{
    protected $guarded = [];
    protected $casts = ['is_active'=>'boolean', 'version'=>'integer', 'manager_user_id'=>'integer'];
    public function manager() { return $this->belongsTo(User::class, 'manager_user_id'); }
    public function owners()
    {
        return $this->belongsToMany(User::class, 'business_branches', 'group_id', 'owner_id')->withTimestamps();
    }
}
