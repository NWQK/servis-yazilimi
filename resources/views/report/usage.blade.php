@extends('layouts.app')
@section('page-title', 'Ürün ve servis istatistikleri')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Ana sayfa</a></li>
    <li class="breadcrumb-item">Ürün ve servis istatistikleri</li>
@endsection
@push('css-page')
<style>
.sr-usage{--usage-red:#c60019;--usage-soft:#fff1f3}
.sr-usage .usage-intro{border-top:4px solid var(--usage-red)}
.sr-usage .usage-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;margin-bottom:24px}
.sr-usage .usage-summary .card{margin:0;min-width:0}
.sr-usage .usage-kicker{font-size:.8rem;font-weight:600;opacity:.7;margin-bottom:10px}
.sr-usage .usage-value{font-size:1.8rem;font-weight:750;line-height:1.2;overflow-wrap:anywhere}
.sr-usage .usage-name{font-size:1.05rem;font-weight:700;overflow-wrap:anywhere}
.sr-usage .usage-columns{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px}
.sr-usage .usage-columns>.card{min-width:0}
.sr-usage .usage-list{list-style:none;margin:0;padding:0}
.sr-usage .usage-list>li{padding:18px 0;border-bottom:1px solid rgba(128,128,128,.15)}
.sr-usage .usage-list>li:last-child{border:0}
.sr-usage .usage-row{display:flex;align-items:flex-start;gap:12px;min-width:0}
.sr-usage .usage-rank{display:grid;place-items:center;flex:0 0 30px;height:30px;border-radius:9px;background:var(--usage-soft);color:var(--usage-red);font-weight:700}
.sr-usage .usage-detail{flex:1;min-width:0}
.sr-usage .usage-detail strong{display:block;overflow-wrap:anywhere}
.sr-usage .usage-amount{font-weight:750;text-align:right;flex:0 0 auto;max-width:40%;overflow-wrap:anywhere}
.sr-usage .usage-amount small{display:block;font-weight:400;font-size:.75rem;opacity:.7}
.sr-usage .usage-bar{height:5px;background:rgba(128,128,128,.12);border-radius:8px;overflow:hidden;margin:12px 0 8px}
.sr-usage .usage-bar>span{display:block;height:100%;background:var(--usage-red);border-radius:8px}
.sr-usage .usage-caption{font-size:.8rem;opacity:.7;line-height:1.6}
.sr-usage .usage-empty{padding:40px 12px;text-align:center;opacity:.7}
.sr-usage .usage-shortcuts{display:flex;flex-wrap:wrap;gap:8px;margin-top:16px}
.sr-usage .usage-shortcuts a{font-size:.85rem;border:1px solid rgba(128,128,128,.25);border-radius:20px;padding:7px 13px;color:inherit}
@media(max-width:1100px){.sr-usage .usage-summary{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(max-width:767px){.sr-usage .usage-columns{grid-template-columns:1fr;gap:0}.sr-usage .card-body{padding:18px}.sr-usage .usage-summary{gap:10px}.sr-usage .usage-value{font-size:1.5rem}.sr-usage .usage-name{font-size:.95rem}.sr-usage .usage-row{gap:8px}.sr-usage .usage-rank{flex-basis:25px;height:25px}}
</style>
@endpush
@section('content')
@php
    $topProduct = $report['products']->first();
    $topService = $report['services']->first();
    $presets = [
        ['Bu ay', now()->startOfMonth()->toDateString(), now()->toDateString()],
        ['Geçen ay', now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
        ['Son 90 gün', now()->subDays(89)->toDateString(), now()->toDateString()],
        ['Bu yıl', now()->startOfYear()->toDateString(), now()->toDateString()],
    ];
@endphp
<div class="sr-usage">
    <div class="card usage-intro"><div class="card-body">
        <h4>İşletmenizde en çok neler kullanılıyor?</h4>
        <p class="text-muted mb-4">Ürünlerinizi ve tamamlanan servislerinizi seçtiğiniz döneme göre karşılaştırın.</p>
        @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
        <form method="get" action="{{ route('report.usage') }}" class="row g-3 align-items-end">
            @if($context = \App\Services\BranchContext::current())<input type="hidden" name="_branch" value="{{ $context['token'] }}">@endif
            <div class="col-md-5"><label for="usage-start" class="form-label">Başlangıç tarihi</label><input class="form-control" type="date" id="usage-start" name="start_date" value="{{ $start }}" required></div>
            <div class="col-md-5"><label for="usage-end" class="form-label">Bitiş tarihi</label><input class="form-control" type="date" id="usage-end" name="end_date" value="{{ $end }}" required></div>
            <div class="col-md-2"><button class="btn btn-primary w-100" type="submit">Göster</button></div>
        </form>
        <nav class="usage-shortcuts" aria-label="Hazır tarih aralıkları">@foreach($presets as [$label, $from, $to])<a href="{{ route('report.usage', ['start_date'=>$from, 'end_date'=>$to]) }}">{{ $label }}</a>@endforeach</nav>
    </div></div>

    <div class="usage-summary">
        <div class="card"><div class="card-body"><div class="usage-kicker">Tamamlanan servis</div><div class="usage-value">{{ number_format($report['completed_services'], 0, ',', '.') }}</div><small>Seçili dönemde</small></div></div>
        <div class="card"><div class="card-body"><div class="usage-kicker">Kullanılan ürün çeşidi</div><div class="usage-value">{{ number_format($report['product_count'], 0, ',', '.') }}</div><small>Fatura ve elden satışlarda</small></div></div>
        <div class="card"><div class="card-body"><div class="usage-kicker">En çok kullanılan ürün</div><div class="usage-name">{{ $topProduct->title ?? 'Henüz kayıt yok' }}</div>@if($topProduct)<small>{{ number_format($topProduct->quantity, 0, ',', '.') }} {{ $topProduct->unit_name }}</small>@endif</div></div>
        <div class="card"><div class="card-body"><div class="usage-kicker">En sık yapılan servis türü</div><div class="usage-name">{{ $topService ? ($topService->title ?? 'Silinmiş servis türü #'.$topService->type_id) : 'Henüz kayıt yok' }}</div>@if($topService)<small>{{ number_format($topService->uses, 0, ',', '.') }} servis</small>@endif</div></div>
    </div>

    <div class="usage-columns">
        <section class="card" aria-labelledby="usage-products"><div class="card-header"><h5 id="usage-products" class="mb-1">En çok kullanılan ürünler</h5><span class="usage-caption">Miktara göre ilk 10 · Faturalı ve elden satışlar</span></div><div class="card-body pt-0">
            <ol class="usage-list">@forelse($report['products'] as $product)
                <li><div class="usage-row"><span class="usage-rank" aria-hidden="true">{{ $loop->iteration }}</span><div class="usage-detail"><strong>{{ $product->title }}</strong><div class="usage-bar" aria-hidden="true"><span style="width:{{ (int) round(100 * $product->quantity / max(1, $topProduct->quantity)) }}%"></span></div><div class="usage-caption">Faturalı: {{ number_format($product->invoice_quantity, 0, ',', '.') }} · Elden: {{ number_format($product->counter_quantity, 0, ',', '.') }}</div></div><div class="usage-amount">{{ number_format($product->quantity, 0, ',', '.') }}<small>{{ $product->unit_name }}</small></div></div></li>
            @empty<li class="usage-empty">Bu tarih aralığında ürün kullanımı veya satış kaydı yok.</li>@endforelse</ol>
        </div></section>
        <section class="card" aria-labelledby="usage-services"><div class="card-header"><h5 id="usage-services" class="mb-1">En sık yapılan servis türleri</h5><span class="usage-caption">Tamamlanan servislerde ilk 10 · {{ $report['service_type_count'] }} farklı tür</span></div><div class="card-body pt-0">
            <ol class="usage-list">@forelse($report['services'] as $service)
                <li><div class="usage-row"><span class="usage-rank" aria-hidden="true">{{ $loop->iteration }}</span><div class="usage-detail"><strong>{{ $service->title ?? 'Silinmiş servis türü #'.$service->type_id }}</strong><div class="usage-bar" aria-hidden="true"><span style="width:{{ (int) round(100 * $service->uses / max(1, $topService->uses)) }}%"></span></div><div class="usage-caption">Tamamlanmış {{ number_format($service->uses, 0, ',', '.') }} farklı işte seçildi</div></div><div class="usage-amount">{{ number_format($service->uses, 0, ',', '.') }}<small>servis</small></div></div></li>
            @empty<li class="usage-empty">Bu dönemde servis türü seçilmiş tamamlanmış bir iş yok.</li>@endforelse</ol>
        </div></section>
    </div>
    <div class="card"><div class="card-body usage-caption">
        <strong>Nasıl hesaplanır?</strong> Ürünler fatura tarihine ve elden satış tarihine, servisler servis tarihine göre listelenir. Fatura ödemeleri sayıları değiştirmez. Faturada azaltılan veya çıkarılan ürünler güncel miktara yansır; silinen faturalar kapsamdan çıkar. Satın alımlar ve şubeler arası stok transferleri ürün kullanımı sayılmaz. Aynı servis türü bir işte birkaç kez seçilse bile bir kez sayılır; planlanan ve iptal edilen işler dahil edilmez. Farklı ürün birimleri toplanmaz. Bu ekran güncel kayıtlara dayanır; servis silinir veya tekrar açılırsa sonuç da değişir.
    </div></div>
</div>
@endsection
