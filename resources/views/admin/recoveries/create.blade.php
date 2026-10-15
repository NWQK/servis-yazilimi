@extends('layouts.app')
@section('page-title','Kurtarma hazırlığı')
@section('content')
<div class="card"><div class="card-body"><h4>Hangi işletme, hangi yedek?</h4><p class="text-muted">Bu adım yalnızca ön izleme hazırlar. İşletme kayıtları henüz değiştirilmez.</p>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@if(!$sqlite)<div class="alert alert-danger">Sunucuda PHP PDO SQLite eklentisi etkinleştirilmelidir. SQL yedekleri canlı veritabanında çalıştırılmaz.</div>@endif
<form method="post" action="{{ route('recoveries.store') }}">@csrf
<div class="row g-3"><div class="col-12 col-md-6"><label class="form-label" for="recovery-owner">İşletme hesabı</label><select class="form-select" id="recovery-owner" name="owner_id" required><option value="">İşletme seçin</option>@foreach($owners as $owner)<option value="{{ $owner->id }}" @selected((int)old('owner_id',$selectedOwner)===$owner->id)>{{ $owner->name }} · #{{ $owner->id }}</option>@endforeach</select></div>
<div class="col-12 col-md-6"><label class="form-label" for="recovery-backup">Yedek</label><select class="form-select" id="recovery-backup" name="backup_id" required><option value="">Yedek seçin</option>@foreach($copies as $copy)<option value="{{ $copy->id }}" @selected((int)old('backup_id',$selectedBackup)===$copy->id)>{{ $copy->backup_date->format('d.m.Y') }} · {{ $copy->kind==='monthly'?'Aylık':'Günlük' }} · {{ $copy->local_available?'VDS':'Google Drive' }}</option>@endforeach</select></div></div>
<div class="row g-3 my-3"><div class="col-12 col-md-6"><label class="border rounded p-3 d-block h-100"><input class="form-check-input me-2" type="radio" name="mode" value="missing" @checked(old('mode','missing')==='missing')><strong>Silinen kayıtları kurtar</strong><p class="text-muted small mt-2 mb-0">Yedekte bulunup güncel kayıtlarda bulunmayan kayıtları ekler. Silinenlere taşınmış araç/müşterileri geri getirir. Mevcut kayıtları ezmez. Stok ve mali hareketler uyuşmuyorsa işlem durur.</p></label></div>
<div class="col-12 col-md-6"><label class="border rounded p-3 d-block h-100"><input class="form-check-input me-2" type="radio" name="mode" value="replace" @checked(old('mode')==='replace')><strong>İşletmeyi yedek tarihine döndür</strong><p class="text-muted small mt-2 mb-0">Bu işletmenin müşteri, araç, servis, stok, fatura ve ödeme kayıtlarını birlikte döndürür. Yedekten sonra oluşan ticari değişiklikler kaybolur. Diğer işletmeler etkilenmez.</p></label></div></div>
<div class="alert alert-light border">Hesap giriş bilgileri, iki aşamalı doğrulama, mevcut yetkiler, abonelik/kapasite ve merkezi iletişim ayarları korunur. Kurtarılan yeni personel hesapları güvenlik nedeniyle kapalı gelir. SMS ve e-posta tekrar gönderilmez.</div>
<button class="btn btn-primary" @disabled(!$sqlite||$copies->isEmpty()||$owners->isEmpty())>Ön izlemeyi hazırla</button><a class="btn btn-link" href="{{ route('recoveries.index') }}">Geri dön</a>
</form></div></div>
@endsection
