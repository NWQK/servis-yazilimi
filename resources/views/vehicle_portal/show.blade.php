@extends('vehicle_portal.layout')
@section('title', $vehicle->license_plate . ' — Faturalar')
@section('content')
    <section class="hero"><span class="eyebrow">ARACINIZ</span><h1>{{ $vehicle->license_plate }}</h1><p>{{ $vehicle->display_name }} · {{ $vehicle->mileage !== null ? number_format($vehicle->mileage, 0, ',', '.') . ' km' : 'Kilometre belirtilmedi' }}</p></section>
    @if($review)<a class="card invoice-link" href="{{ $review->publicUrl() }}"><div><h3>{{ $review->rating ? 'Değerlendirmeniz' : 'İşletmeyi değerlendir' }}</h3><p>{{ $review->rating ? $review->rating . ' / 5 puan verdiniz. Teşekkür ederiz.' : 'Deneyiminizi yıldızlarla puanlayın.' }}</p></div><span>{{ $review->rating ? 'Görüntüle' : 'Değerlendir' }} →</span></a>@endif
    <a class="card invoice-link" href="{{ route('vehicle-history.index') }}"><div><h3>Araç servis geçmişini sorgula</h3><p>Şasi numarasıyla paylaşılan tamamlanmış işlemleri görüntüleyin.</p></div><span>Sorgula →</span></a>
    <section id="invoices"><h2>Faturalar ({{ $invoices->total() }})</h2>
        @forelse ($invoices as $invoice)
            <a class="card invoice-link" href="{{ route('vehicle-portal.invoice', [$code->token, $invoice->id]) }}"><div><h3>{{ $settings['invoice_number_prefix'] . $invoice->invoice_id }}</h3><p>{{ dateFormat($invoice->invoice_date) }} · {{ ['Ödenmedi', 'Kısmen ödendi', 'Ödendi'][$invoice->status] ?? '—' }}</p></div><span>Faturayı görüntüle →</span></a>
        @empty <p class="card muted">Bu araç için henüz fatura bulunmuyor.</p> @endforelse
        @include('vehicle_portal.pagination', ['paginator' => $invoices, 'anchor' => 'invoices'])
    </section>
    <section id="acceptance-photos" class="sr-photos">
        <h2>Araç kabul fotoğrafları ({{ $photos->total() }})</h2>
        <p class="sr-photo-caption">Aracınızın servise kabulü sırasında işletme tarafından eklenen fotoğraflar. Fotoğraflar servis tamamlandıktan sonra {{ \App\Services\PhotoRetention::days() }} gün saklanır. Saklamak istediklerinizi süre dolmadan indirebilirsiniz.</p>
        <div class="sr-photo-grid">
            @forelse($photos as $photo)
                <figure class="sr-photo">
                    <a href="{{ route('vehicle-portal.photo',[$code->token,$photo->id]) }}" target="_blank" rel="noopener noreferrer" aria-label="Araç kabul fotoğrafını büyüt">
                        <img src="{{ route('vehicle-portal.photo',[$code->token,$photo->id]) }}" width="320" height="240" loading="lazy" alt="Aracınızın kabul fotoğrafı {{ $loop->iteration }}">
                    </a>
                    <figcaption><span>Servis {{ $settings['service_number_prefix'] . $photo->service->service_id }} · {{ $photo->created_at->format('d.m.Y') }}</span><a href="{{ route('vehicle-portal.photo',[$code->token,$photo->id]) }}" download="arac-kabul.jpg">İndir</a></figcaption>
                </figure>
            @empty <p class="card muted">Henüz kabul fotoğrafı bulunmuyor.</p> @endforelse
        </div>
        @include('vehicle_portal.pagination',['paginator'=>$photos,'anchor'=>'acceptance-photos'])
    </section>
@endsection
