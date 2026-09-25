<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;
    protected $casts = ['external_labor_amount' => 'decimal:2'];

    protected $fillable = [
        'external_labor_amount',
        'invoice_id',
        'client',
        'service',
        'invoice_date',
        'status',
        'parent_id',
    ];

    public static function statues()
    {
        return [
            __('Unpaid'),
            __('Partialy Paid'),
            __('Paid'),
        ];
    }

    public function tax()
    {
        return $this->hasOne('App\Models\Tax', 'id', 'tax');
    }

    public function items()
    {
        return $this->hasMany('App\Models\InvoiceItem', 'invoice_id', 'id');
    }

    public function types()
    {
        return $this->hasMany(InvoiceService::class, 'invoice_id', 'id');
    }

    public function clients()
    {
        return $this->hasOne('App\Models\User', 'id', 'client');
    }

    public function services()
    {
        return $this->hasOne('App\Models\Service', 'id', 'service');
    }

    public function getInvoiceSubTotalAmount()
    {
        $invoiceSubTotal = 0;
        foreach ($this->items as $invoiceItem) {
            $invoiceSubTotal += ($invoiceItem->amount * $invoiceItem->quantity);
        }
        return $invoiceSubTotal;
    }

    public function getInvoiceTotalTaxAmount()
    {
        $invoiceTotalTax = 0;
        foreach ($this->items as $invoiceItem) {
            $invoiceItemTaxes = Item::taxRate($invoiceItem->tax);
            $invoiceTotalTax += ($invoiceItemTaxes / 100) * ($invoiceItem->amount * $invoiceItem->quantity);
        }

        if ($this->service) {
            $service = Service::find($this->service);
            if ($service && $service->taxes) {
                foreach ($service->taxes as $serviceTax) {
                    $invoiceTotalTax += Service::taxRate($serviceTax->rate, $service->rate);
                }
            }
        }

        return $invoiceTotalTax;
    }
    public function getInvoiceServiceSubTotalAmount()
    {
        $invoiceServiceSubTotal = (float) $this->external_labor_amount;
        foreach ($this->types as $serviceItem) {
            $invoiceServiceSubTotal += $serviceItem->rate;
        }
        return $invoiceServiceSubTotal;
    }

    public function getInvoiceServiceTaxAmount()
    {
        $invoiceServiceTax = 0;
        foreach ($this->types as $serviceItem) {
            if (empty($serviceItem->tax))
                continue;
            $taxIds = explode(',', $serviceItem->tax);
            $taxes = \App\Models\Tax::whereIn('id', $taxIds)->get();
            foreach ($taxes as $tax) {
                $invoiceServiceTax += Service::taxRate($tax->rate, $serviceItem->rate);
            }
        }
        return $invoiceServiceTax;
    }

    public function getInvoiceServiceAmount()
    {
        return $this->getInvoiceServiceSubTotalAmount() + $this->getInvoiceServiceTaxAmount();
    }

    public function getInvoiceItemAmount()
    {
        return ($this->getInvoiceSubTotalAmount() + $this->getInvoiceTotalTaxAmount());
    }

    public function getInvoiceAllTotalAmount()
    {
        return ($this->getInvoiceServiceAmount() + $this->getInvoiceItemAmount());
    }

    public function getInvoiceTotalDueAmount()
    {
        $invoiceTotalPaid = 0;
        foreach ($this->payments as $invoicePayment) {
            $invoiceTotalPaid += $invoicePayment->amount;
        }

        $due = $this->getInvoiceAllTotalAmount() - $invoiceTotalPaid;

        return max($due, 0);
    }


    public static function taxRate($itemTaxRate, $itemPrice, $itemQuantity)
    {
        return ($itemTaxRate / 100) * ($itemPrice * $itemQuantity);
    }

    public function payments()
    {
        return $this->hasMany('App\Models\InvoicePayment', 'invoice_id', 'id');
    }

    public static function addPayment($data)
    {
        $payment = new InvoicePayment();
        $payment->invoice_id = $data['invoice_id'];
        $payment->transaction_id = $data['transaction_id'];
        $payment->payment_type = $data['payment_type'];
        $payment->amount = $data['amount'];
        $payment->payment_date = date('Y-m-d');
        $payment->payment_status = $data['status'] ?? 'success';
        $payment->receipt = !empty($data['receipt']) ? $data['receipt'] : '';
        $payment->description = $data['notes'];
        ;
        $payment->parent_id = parentId();
        $payment->save();
        $invoice = Invoice::find($data['invoice_id']);
        if ($invoice->getInvoiceTotalDueAmount() <= 0) {
            $status = 2;
        } else {
            $status = 1;
        }
        Invoice::statusChange($invoice->id, $status);
    }
    public static function statusChange($invoice_id, $status)
    {
        $invoice = Invoice::find($invoice_id);
        $invoice->status = $status;
        $invoice->save();
        return $invoice;
    }
}
