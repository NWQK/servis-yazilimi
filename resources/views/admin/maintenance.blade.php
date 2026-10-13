@extends('layouts.app')
@section('page-title','Bakım modu')
@section('content')
<div class="card"><div class="card-body">
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3"><div><h5>Sistem bakım modu</h5><p class="text-muted mb-0">İşletmelerin sisteme erişimini geçici olarak durdurun.</p></div><span class="badge {{ $enabled ? 'bg-danger' : 'bg-success' }}">{{ $enabled ? 'Bakım modu açık' : 'Sistem kullanıma açık' }}</span></div>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="alert alert-light border">Bakım açıkken işletme panelleri, müşteri araç bağlantıları ve randevu işlemleri bakım ekranı gösterir. <strong>Tanıtım ve bilgilendirme sayfaları, giriş ve şifre sıfırlama açık kalır.</strong> Süper admin erişimi devam eder; bakım modunu buradan kapatabilirsiniz.</div>
<form method="post" action="{{ route('maintenance.save') }}">@csrf
<input type="hidden" name="enabled" value="0">
<label class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="enabled" value="1" @checked($enabled)><span class="form-check-label">Bakım modunu etkinleştir</span></label>
<button class="btn btn-secondary">Durumu kaydet</button>
</form>
<p class="small text-muted mt-3 mb-0">Bu seçenek web erişimini yönetir; zamanlanmış işler ve kuyruk çalışanları devam eder. Veritabanı güncellemesi sırasında terminalden kullanılan bakım prosedürü ayrıca uygulanmalıdır.</p>
</div></div>
@endsection
