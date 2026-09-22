@extends('vehicle_portal.layout')
@section('title', $vehicle->license_plate . ' — Servis Kaydı')
@section('content')
    <section class="hero"><span class="eyebrow">ARACINIZ</span><h1>{{ $vehicle->license_plate }}</h1><p>{{ $vehicle->model }} · {{ number_format($vehicle->mileage, 0, ',', '.') }} km</p><p>Son servis: {{ $vehicle->last_service_date ? \Carbon\Carbon::parse($vehicle->last_service_date)->format('d.m.Y') : '—' }} · Sonraki servis: {{ $vehicle->next_service_due_date ? \Carbon\Carbon::parse($vehicle->next_service_due_date)->format('d.m.Y') : '—' }}</p></section>
    <nav class="tabs"><a href="#services">Servisler ({{ $services->total() }})</a><a href="#invoices">Faturalar ({{ $invoices->total() }})</a></nav>
    <section id="services"><h2>Servis geçmişi</h2>
        @forelse ($services as $service)
            <article class="card"><div class="card-top"><h3>{{ $settings['service_number_prefix'] . $service->service_id }}</h3><span class="badge">{{ ['scheduled'=>'Planlandı','in_progress'=>'Devam ediyor','completed'=>'Tamamlandı','pending_parts'=>'Parça bekleniyor','on_hold'=>'Beklemede','cancelled'=>'İptal edildi'][$service->status] ?? $service->status }}</span></div>
                <p>{{ $service->service_date ? \Carbon\Carbon::parse($service->service_date)->format('d.m.Y') : 'Tarih belirtilmemiş' }} @if ($service->due_date) · Teslim: {{ \Carbon\Carbon::parse($service->due_date)->format('d.m.Y') }} @endif</p>
                @foreach ($service->types as $item)<div class="service-line"><strong>{{ $serviceTypes[$item->type_id] ?? 'Servis işlemi' }}</strong>@if ($item->note)<p class="note">{{ $item->note }}</p>@endif</div>@endforeach
                @if ($service->notes)<p class="note">{{ $service->notes }}</p>@endif
            </article>
        @empty <p class="card muted">Bu araç için henüz servis kaydı bulunmuyor.</p> @endforelse
        @include('vehicle_portal.pagination', ['paginator' => $services, 'anchor' => 'services'])
    </section>
    <section id="invoices"><h2>Faturalar</h2>
        @forelse ($invoices as $invoice)
            <a class="card invoice-link" href="{{ route('vehicle-portal.invoice', [$code->token, $invoice->id]) }}"><div><h3>{{ $settings['invoice_number_prefix'] . $invoice->invoice_id }}</h3><p>{{ $invoice->invoice_date ? \Carbon\Carbon::parse($invoice->invoice_date)->format('d.m.Y') : '—' }} · {{ ['Ödenmedi', 'Kısmen ödendi', 'Ödendi'][$invoice->status] ?? '—' }}</p></div><span>Faturayı görüntüle →</span></a>
        @empty <p class="card muted">Bu araç için henüz fatura bulunmuyor.</p> @endforelse
        @include('vehicle_portal.pagination', ['paginator' => $invoices, 'anchor' => 'invoices'])
    </section>
@endsection
