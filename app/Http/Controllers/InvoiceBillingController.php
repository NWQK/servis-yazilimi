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
        $data=$request->validate(['google_maps_url'=>'nullable|url|max:2048',
            'map_latitude'=>'nullable|required_with:map_longitude|numeric|between:-90,90',
            'map_longitude'=>'nullable|required_with:map_latitude|numeric|between:-180,180']);
        $location=null;
        if (!empty($data['google_maps_url'])) {
            abort_unless(\App\Models\User::where('type','owner')->whereKey(parentId())->exists(),403);
            $saved=\App\Models\BusinessLocation::where('owner_id',parentId())->first();
            $reuse=$saved && $saved->google_maps_url===$data['google_maps_url'] && !$request->filled('map_latitude');
            $location=app(\App\Services\GoogleMapsLocation::class)->resolve($data['google_maps_url'], $reuse ? $saved->latitude : ($data['map_latitude'] ?? null), $reuse ? $saved->longitude : ($data['map_longitude'] ?? null));
        } elseif ($request->filled('map_latitude') || $request->filled('map_longitude')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['google_maps_url'=>'Koordinatlarla birlikte Google Maps bağlantısını da girin.']);
        }
        DB::transaction(function() use($details,$location,$request) {
            DB::table('invoice_business_profiles')->updateOrInsert(['parent_id' => parentId()],
                ['details' => json_encode($details, JSON_UNESCAPED_UNICODE)]);
            if ($request->exists('google_maps_url')) {
                if ($location) \App\Models\BusinessLocation::updateOrCreate(['owner_id'=>parentId()],$location);
                else \App\Models\BusinessLocation::where('owner_id',parentId())->delete();
            }
        });
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
