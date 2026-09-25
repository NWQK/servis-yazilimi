@extends('vehicle_portal.layout')
@section('title', $vehicle->license_plate . ' — Faturalar')
@section('content')
    <section class="hero"><span class="eyebrow">ARACINIZ</span><h1>{{ $vehicle->license_plate }}</h1><p>{{ $vehicle->display_name }} · {{ $vehicle->mileage !== null ? number_format($vehicle->mileage, 0, ',', '.') . ' km' : 'Kilometre belirtilmedi' }}</p></section>
    <section id="invoices"><h2>Faturalar ({{ $invoices->total() }})</h2>
        @forelse ($invoices as $invoice)
            <a class="card invoice-link" href="{{ route('vehicle-portal.invoice', [$code->token, $invoice->id]) }}"><div><h3>{{ $settings['invoice_number_prefix'] . $invoice->invoice_id }}</h3><p>{{ dateFormat($invoice->invoice_date) }} · {{ ['Ödenmedi', 'Kısmen ödendi', 'Ödendi'][$invoice->status] ?? '—' }}</p></div><span>Faturayı görüntüle →</span></a>
        @empty <p class="card muted">Bu araç için henüz fatura bulunmuyor.</p> @endforelse
        @include('vehicle_portal.pagination', ['paginator' => $invoices, 'anchor' => 'invoices'])
    </section>
@endsection
