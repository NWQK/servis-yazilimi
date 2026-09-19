<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceService extends Model
{
    use HasFactory;
    protected $fillable = [
        'invoice_id',
        'service_type',
        'tax',
        'rate',
        'parent_id',
        'note'
    ];


}
