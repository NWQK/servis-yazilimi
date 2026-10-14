<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoicePayment extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        foreach(['created','updated','deleted'] as $event) static::$event(function(self $payment) use($event){
            if(request()->attributes->get('invoice_operation_guard') || !\Schema::hasTable('invoice_changes')) return;
            $invoice=Invoice::where('parent_id',$payment->parent_id)->find($payment->invoice_id);
            if(!$invoice) return;
            $keys=['amount','payment_date','description','payment_status'];
            $before=$event==='created' ? [] : array_intersect_key($payment->getOriginal(),array_flip($keys));
            $after=$event==='deleted' ? [] : $payment->only($keys);
            $changes=\App\Services\InvoiceJournal::difference($before,$after,'payments.'.$payment->id.'.');
            if(!$changes) return;
            \DB::table('invoices')->where('id',$invoice->id)->increment('revision');
            \App\Services\InvoiceJournal::record($invoice,$changes,'payment.auto');
        });
    }

    public function scopeAccounted($query)
    {
        // Older installations may still have the pre-payment-status schema.
        if (!\Schema::hasColumn('invoice_payments','payment_status')) return $query;
        return $query->where(fn($q)=>$q->whereNull('invoice_payments.payment_status')->orWhereIn('invoice_payments.payment_status',['success','succeeded']));
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }
}
