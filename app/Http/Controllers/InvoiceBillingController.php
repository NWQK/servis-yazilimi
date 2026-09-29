<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\InvoiceBilling;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceBillingController extends Controller
{
    public function customer(int $id)
    {
        abort_unless(auth()->user()->can('create invoice') || auth()->user()->can('edit invoice'), 403);
        \App\Models\User::where('parent_id', parentId())->where('type', 'client')->findOrFail($id);
        return response()->json(InvoiceBilling::customer(parentId(), $id))->header('Cache-Control', 'private, no-store');
    }

    public function business(Request $request)
    {
        abort_unless(auth()->user()->can('manage company settings'), 403);
        $request->session()->flash('tab', 'invoice_business');
        $details = InvoiceBilling::validated($request, true);
        DB::table('invoice_business_profiles')->updateOrInsert(['parent_id' => parentId()],
            ['details' => json_encode($details, JSON_UNESCAPED_UNICODE)]);
        return back()->with('tab', 'invoice_business')->with('success', 'İşletme bilgileri kaydedildi. Yeni faturalara otomatik eklenir.');
    }

    public function edit(int $id)
    {
        abort_unless(auth()->user()->can('edit invoice'), 403);
        $invoice = Invoice::where('parent_id', parentId())->findOrFail($id);
        return view('invoice.billing_edit', compact('invoice'));
    }

    public function update(Request $request, int $id)
    {
        abort_unless(auth()->user()->can('edit invoice'), 403);
        $invoice = Invoice::where('parent_id', parentId())->findOrFail($id);
        $invoice->customer_details = InvoiceBilling::validated($request);
        $invoice->save();
        return redirect()->route('invoice.show', encrypt($invoice->id))->with('success', 'Müşteri fatura bilgileri kaydedildi.');
    }
}
