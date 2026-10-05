@php $admin = auth()->user()->type === 'super admin'; @endphp
@if ($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div><h4 class="mb-1">{{ $admin ? 'Araç kapasitesi paketleri' : 'Paketim ve abonelik' }}</h4><p class="text-muted mb-0">Paket kapasitesi, işletmenizde kayıtlı toplam araç sayısına göre hesaplanır.</p></div>
    @if ($admin || auth()->user()->type === 'owner')<a class="btn btn-outline-secondary" href="{{ route('capacity.index') }}">Ek kapasite talepleri</a>@endif
</div>
@if ($capacity)
<div class="card"><div class="card-body">
<h5>{{ $capacity['plan']->title ?? 'Henüz paket atanmadı' }}</h5>
<div class="d-flex flex-wrap gap-4 my-3"><div>Kayıtlı araç<br><strong>{{ number_format($capacity['used'], 0, ',', '.') }}</strong></div><div>Toplam kapasite<br><strong>{{ $capacity['limit'] === null ? 'Eski paket — araç limiti yok' : number_format($capacity['limit'],0,',','.') . ' araç' }}</strong></div><div>Kalan kapasite<br><strong>{{ $capacity['remaining'] === null ? '—' : number_format($capacity['remaining'],0,',','.') . ' araç' }}</strong></div><div>Onaylı ek kapasite<br><strong>{{ number_format($capacity['extra'],0,',','.') }} araç</strong></div></div>
<p>Abonelik bitişi: {{ auth()->user()->subscription_expire_date ? dateFormat(auth()->user()->subscription_expire_date) : 'Süresiz' }}</p>
@if ($capacity['limit'] !== null && $capacity['used'] > $capacity['limit'])<p class="text-danger">Mevcut kayıtlarınız korunur. Yeni araç eklemek için kapasitenizi artırmanız gerekir.</p>@endif
@if ((int) optional($capacity['plan'])->vehicle_limit === 3000)
    @if ((float) $capacity['plan']->vehicle_block_amount > 0)
        <form action="{{ route('capacity.store') }}" method="post">@csrf
            <input type="hidden" name="quoted_amount" value="{{ $capacity['plan']->vehicle_block_amount }}">
            <p><strong>500 ek araç: {{ priceFormat($capacity['plan']->vehicle_block_amount) }}</strong></p>
            <div class="form-check mb-3"><input type="checkbox" name="confirm" value="1" id="capacity-confirm" class="form-check-input" required><label for="capacity-confirm" class="form-check-label">Bu tutarla 500 araçlık ek kapasite talep etmeyi onaylıyorum.</label></div>
            <button class="btn btn-primary">500 araçlık ek kapasite talep et</button>
            <p class="text-muted mt-2 mb-0">Ödeme kontrolü ve süper admin onayından sonra kapasiteniz artar. Ek kapasite 3.000 araçlık paketinizde kullanılabilir; abonelik süresini uzatmaz.</p>
        </form>
    @else<p class="text-muted mb-0">Ek kapasite ücreti henüz belirlenmedi. İşletme yöneticisiyle iletişime geçin.</p>@endif
@endif
</div></div>
@endif
<div class="row g-3">
@foreach ($subscriptions->whereNotNull('vehicle_limit') as $plan)
<div class="col-12 col-md-6 col-xl-3"><div class="card h-100"><div class="card-body d-flex flex-column">
    <h5>{{ $plan->title }}</h5><p class="fs-3 fw-bold mb-1">{{ number_format($plan->vehicle_limit,0,',','.') }} araç</p>
    <p class="text-muted">Kayıtlı toplam araç kapasitesi</p>
    <p class="fs-4 fw-bold">{{ (int)$plan->vehicle_limit === 50 ? 'Ücretsiz demo' : ((float) $plan->package_amount > 0 ? priceFormat($plan->package_amount) : 'Ücret belirlenmedi') }}<small class="d-block fs-6 text-muted">{{ __((string) $plan->interval) }}</small></p>
    <ul class="ps-3"><li>Müşteri sayısı sınırsız</li><li>Personel sayısı sınırsız</li><li>Kullanıcı sayısı sınırsız</li>@if((int)$plan->vehicle_limit === 3000)<li>Onayla 500 araçlık ek kapasite</li><li>500 ek araç: {{ (float)$plan->vehicle_block_amount > 0 ? priceFormat($plan->vehicle_block_amount) : 'Ücret belirlenmedi' }}</li>@else<li>Ek kapasite için üst pakete geçiş</li>@endif</ul>
    <div class="mt-auto">
    @if ($admin && auth()->user()->can('edit pricing packages'))
        <a href="#" class="btn btn-outline-primary customModal w-100" data-size="md" data-url="{{ route('subscriptions.edit',$plan->id) }}" data-title="Paketi düzenle">Paketi düzenle</a>
    @elseif (auth()->user()->subscription == $plan->id)<span class="badge bg-success">Mevcut paketiniz</span>
    @elseif (auth()->user()->type === 'owner' && auth()->user()->can('buy pricing packages') && (float) $plan->package_amount > 0)
        <a href="{{ route('subscriptions.show',encrypt($plan->id)) }}" class="btn btn-primary w-100">Paketi seç</a>
    @endif
    </div>
</div></div></div>
@endforeach
</div>
@if ($admin && $subscriptions->whereNull('vehicle_limit')->isNotEmpty())
<details class="mt-4"><summary>Eski paketler ve mevcut atamalar</summary><p class="text-muted mt-2">Eski paketler korunur. İşletmeleri yeni araç paketlerine yönetim panelinden atayabilirsiniz.</p>
@foreach ($subscriptions->whereNull('vehicle_limit') as $plan)<div class="d-flex justify-content-between align-items-center p-3 border rounded mb-2"><span>{{ $plan->title }}</span><a href="#" class="btn btn-sm btn-outline-secondary customModal" data-size="md" data-url="{{ route('subscriptions.edit',$plan->id) }}" data-title="Eski paketi düzenle">Düzenle</a></div>@endforeach
</details>
@endif
