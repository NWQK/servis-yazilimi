<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;
    protected $fillable=[
        'title',
        'vehicle',
        'date',
        'amount',
        'receipt',
        'notes',
        'parent_id',
    ];

    public function vehicles()
    {
        return $this->hasOne('App\Models\Vehicle','id','vehicle');
    }
}
