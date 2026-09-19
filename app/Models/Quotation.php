<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'quotation_id',
        'client_id',
        'vehicle_id',
        'quotation_date',
        'status',
        'notes',
        'convert_service',
        'parent_id',
    ];


    public static function statues()
    {
        return [
            'draft' => __('Draft'),
            'sent' => __('Sent'),
            'accepted' => __('Accepted'),
            'rejected' => __('Rejected'),
            'cancelled' => __('Cancelled'),
        ];
    }


    public function clients()
    {
        return $this->hasOne('App\Models\User', 'id', 'client_id');
    }
    public function vehicles()
    {
        return $this->hasOne('App\Models\Vehicle', 'id', 'vehicle_id');
    }
    public function types()
    {
        return $this->hasMany('App\Models\QuotationService', 'quotation_id', 'id');
    }
    public function items()
    {
        return $this->hasMany('App\Models\QuotationItem', 'quotation_id', 'id');
    }

    public function getQuotationServiceSubTotalAmount()
    {
        $total = 0;
        foreach ($this->types as $serviceItem) {
            $total += $serviceItem->rate;
        }
        return $total;
    }

    public function getQuotationServiceTaxAmount()
    {
        $total = 0;
        foreach ($this->types as $serviceItem) {
            if (empty($serviceItem->tax))
                continue;
            $taxIds = explode(',', $serviceItem->tax);
            $taxes = \App\Models\Tax::whereIn('id', $taxIds)->get();
            foreach ($taxes as $tax) {
                $total += ($tax->rate / 100) * $serviceItem->rate;
            }
        }
        return $total;
    }

    public function getQuotationServiceAmount()
    {
        return $this->getQuotationServiceSubTotalAmount() + $this->getQuotationServiceTaxAmount();
    }

    public function getQuotationSubTotalAmount()
    {
        $total = 0;
        foreach ($this->items as $invoiceItem) {
            $total += ($invoiceItem->amount * $invoiceItem->quantity);
        }
        return $total;
    }

    public function getQuotationTotalTaxAmount()
    {
        $invoiceTotalTax = 0;
        foreach ($this->items as $invoiceItem) {
            $invoiceItemTaxes = Item::taxRate($invoiceItem->tax);
            $invoiceTotalTax += ($invoiceItemTaxes / 100) * ($invoiceItem->amount * $invoiceItem->quantity);
        }
        return $invoiceTotalTax;
    }

    public function getQuotationTotalAmount()
    {
        return $this->getQuotationSubTotalAmount() + $this->getQuotationTotalTaxAmount();
    }

    public function getQuotationAllTotalAmount()
    {
        return $this->getQuotationServiceAmount() + $this->getQuotationTotalAmount();
    }
}
