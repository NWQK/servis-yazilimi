<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuotationService extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'service_type',
        'rate',
        'tax',
        'note',
        'parent_id',
    ];
}
