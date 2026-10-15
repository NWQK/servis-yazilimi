@extends('layouts.app')
@section('page-title','Fotoğraf saklama ve kullanım')
@push('css-page')<link rel="stylesheet" href="{{ asset('css/photo-retention.css') }}">@endpush
@section('content')
@php($size = static fn($bytes) => $bytes >= 1073741824 ? number_format($bytes/1073741824,2,',','.').' GB' : number_format($bytes/1048576,2,',','.').' MB')
<div class="card"><div class="card-body">
    <h1 class="h5">Araç kabul fotoğrafları</h1>
    <p class="text-muted">Saklama süresini belirleyin, işletmelerin kayıtlı fotoğraf kullanımını takip edin.</p>
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    @if($error)<div class="alert alert-danger">{{ $error }}</div>@endif
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="border rounded p-3"><small>Toplam kayıtlı fotoğraf</small><div class="h4 mb-0">{{ number_format($total->photos,0,',','.') }}</div></div></div>
        <div class="col-md-4"><div class="border rounded p-3"><small>Kayıtlı dosya boyutu</small><div class="h4 mb-0">{{ $size($total->bytes) }}</div></div></div>
        <div class="col-md-4"><div class="border rounded p-3"><small>Son başarılı temizlik</small><div>{{ isset($last['at']) ? \Carbon\Carbon::parse($last['at'])->timezone('Europe/Istanbul')->format('d.m.Y H:i') : 'Henüz çalışmadı' }}</div>
        @if($last)<small>{{ $last['photos'] }} fotoğraf, {{ $last['orphans'] }} sahipsiz dosya; {{ $size($last['bytes']) }} boşaltıldı.</small>@endif</div></div>
    </div>
    <form method="post" action="{{ route('photo-retention.save') }}" class="row g-3 align-items-end">@csrf
        <div class="col-sm-6 col-lg-4"><label class="form-label" for="photo-retention-days">Tamamlanan servislerde saklama süresi (gün)</label><input id="photo-retention-days" class="form-control" type="number" name="days" min="1" max="365" value="{{ old('days',$days) }}" required aria-describedby="photo-retention-help"></div>
        <div class="col-sm-6"><button class="btn btn-primary">Süreyi kaydet</button></div>
    </form>
    <p id="photo-retention-help" class="text-muted small mt-3">Varsayılan 90 gün; 1–365 gün seçilebilir. Süre servis tamamlandığında başlar. Devam eden servisler korunur. Süreyi kısaltmak mevcut fotoğrafları da etkiler; süresi dolanlar hemen gizlenir ve her gece 03.30’da otomatik silinir. Servis, araç ve faturalar korunur.</p>
    <div class="alert alert-light border mb-0">Süresi dolan fotoğraflar eski yedeklerden geri yüklense de yeniden gösterilmez. Eski yedeklerdeki kopyalar, yedek saklama süresi dolana kadar bulunabilir. Kullanım rakamları canlı fotoğraf kayıtlarını gösterir; yedek arşivlerinin boyutunu kapsamaz.</div>
</div></div>
<div class="card"><div class="card-body">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"><h2 class="h5 mb-0">İşletme bazında kullanım</h2>
        <form method="get" class="d-flex gap-2"><input class="form-control" type="search" name="search" value="{{ request('search') }}" aria-label="İşletme adı veya e-posta ile ara" placeholder="İşletme ara"><button class="btn btn-outline-primary">Ara</button></form>
    </div>
    <div class="table-responsive"><table class="table sr-photo-usage"><thead><tr><th scope="col">İşletme</th><th scope="col">Fotoğraf sayısı</th><th scope="col">Dosya boyutu</th></tr></thead><tbody>
    @forelse($owners as $owner)<tr><td><a href="{{ route('business-detail.show',$owner->id) }}">{{ $owner->name }}</a><small class="usage-email text-muted" title="{{ $owner->email }}">{{ $owner->email }}</small></td><td>{{ number_format($owner->photo_count,0,',','.') }}</td><td>{{ $size($owner->photo_bytes) }}</td></tr>
    @empty<tr><td colspan="3">İşletme bulunamadı.</td></tr>@endforelse
    </tbody></table></div>{{ $owners->links() }}
</div></div>
@endsection
