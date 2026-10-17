<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class BranchReferral extends Model {
    protected $guarded=[];
    protected $casts=['shared_data'=>'encrypted:array','resolved_at'=>'datetime'];
    protected $hidden=['shared_data','request_hash','accept_hash'];
}
