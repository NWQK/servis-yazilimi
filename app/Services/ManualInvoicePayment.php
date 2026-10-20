<?php
namespace App\Services;
use App\Models\{Invoice,InvoicePayment};
use Illuminate\Http\Request;
class ManualInvoicePayment {
    public function create(Invoice $invoice,Request $request): InvoicePayment {
        $due=round($invoice->getInvoiceTotalDueAmount(),2);
        $data=$request->validate(['payment_date'=>'required|date','cash_channel'=>'nullable|in:cash,card,transfer','amount'=>['required','numeric','min:0.01','max:'.$due,'regex:/^\d+(\.\d{1,2})?$/'],'description'=>'nullable|string|max:2000']);
        $payment=new InvoicePayment();
        $payment->transaction_id=(string)\Illuminate\Support\Str::uuid();
        $payment->invoice_id=$invoice->id; $payment->parent_id=$invoice->parent_id;
        $payment->payment_date=$data['payment_date']; $payment->amount=$data['amount'];
        $payment->payment_type='Manual'; $payment->payment_status='success'; $payment->description=$data['description']??null;
        $payment->cash_channel=$data['cash_channel']??'unclassified';
        $payment->save();
        app(InventoryAccounting::class)->refreshStatus($invoice);

        return $payment;
    }
    public function notify(Invoice $invoice): void {
        $notification=\App\Services\CentralEmail::template('payment_create');
        if($notification && $notification->enabled_email && $invoice->clients?->email) {
            $message=MessageReplace($notification,$invoice->id);
            commonEmailSend($invoice->clients->email,['module'=>'payment_create','subject'=>$message['subject'],'message'=>$message['message']]);
        }
    }
}
