@extends('layouts.app')
@section('page-title','İşletme doğrulama yönetimi')
@section('content')
<div class="card"><div class="card-header"><h5>İşletmelerin iki aşamalı doğrulaması</h5><p class="text-muted mb-0">Doğrulamayı zorunlu tutabilir, kapatabilir veya telefonunu kaybeden işletmenin kurulumunu sıfırlayabilirsiniz. Sıfırlama eski QR anahtarını ve kurtarma kodlarını iptal eder.</p></div><div class="card-body">
@if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
<form method="GET" class="d-flex gap-2 mb-4"><label class="visually-hidden" for="twofa-search">İşletme veya e-posta ara</label><input class="form-control" id="twofa-search" name="search" value="{{ request('search') }}" placeholder="İşletme adı veya e-posta"><button class="btn btn-primary">Ara</button></form>
@forelse($owners as $owner)
<div class="border rounded p-3 mb-3"><div class="d-flex flex-wrap gap-2 justify-content-between align-items-start"><div><h6>{{ $owner->name }}</h6><span class="text-muted">{{ $owner->email }}</span></div><div><span class="badge {{ $owner->twofa_secret ? 'bg-success' : 'bg-secondary' }}">{{ $owner->twofa_secret ? 'Etkin' : ($owner->twofa_required ? 'Kurulum bekleniyor' : 'Kapalı') }}</span>@if($owner->twofa_required)<span class="badge bg-warning text-dark">Zorunlu</span>@endif</div></div>
<details class="mt-3"><summary>Doğrulama ayarlarını değiştir</summary><form method="POST" action="{{ route('two-factor-admin.update',$owner->id) }}" class="mt-3">@csrf
<div class="row g-3"><div class="col-12 col-md-7"><label for="twofa-reason-{{ $owner->id }}" class="form-label">İşlem nedeni</label><input id="twofa-reason-{{ $owner->id }}" name="reason" maxlength="500" class="form-control" placeholder="Örneğin: Telefon kaybı nedeniyle destek talebi" required></div><div class="col-12 col-md-5"><label for="twofa-admin-password-{{ $owner->id }}" class="form-label">Süper admin şifreniz</label><input id="twofa-admin-password-{{ $owner->id }}" name="password" type="password" autocomplete="current-password" class="form-control" required></div></div>
<div class="d-flex flex-wrap gap-2 mt-3"><button type="submit" name="action" value="require" class="btn btn-primary">Aç / zorunlu tut</button><button type="submit" name="action" value="reset" class="btn btn-warning" onclick="return confirm('Eski doğrulama anahtarı ve kurtarma kodları iptal edilecek. İşletme yeniden QR kurulumu yapacak. Devam edilsin mi?')">Sıfırla ve yeniden kurdur</button><button type="submit" name="action" value="disable" class="btn btn-danger" onclick="return confirm('İşletmenin iki aşamalı doğrulaması kapatılacak. Devam edilsin mi?')">Kapat</button></div>
<p class="small text-muted mt-3 mb-0">Aç işlemi, kurulum yoksa işletmeyi QR kurulumu yapmaya yönlendirir. Mevcut kurulum varsa korunur. Her değişiklik yönetici ve işlem nedeni ile kaydedilir.</p>
</form></details></div>
@empty<p>Kayıt bulunamadı.</p>@endforelse
{{ $owners->links() }}
</div></div>
<div class="card"><div class="card-header"><h5>Son destek işlemleri</h5></div><div class="card-body table-responsive"><table class="table"><thead><tr><th>İşletme</th><th>Yönetici</th><th>İşlem</th><th>Neden</th><th>Tarih</th></tr></thead><tbody>
@forelse($actions as $action)<tr><td>{{ $action->owner_name ?? 'Silinmiş hesap' }}</td><td>{{ $action->actor_name ?? 'Silinmiş hesap' }}</td><td>{{ ['require'=>'Zorunlu tut','reset'=>'Kurulumu sıfırla','disable'=>'Kapat'][$action->action] }}</td><td>{{ $action->reason }}</td><td>{{ \Carbon\Carbon::parse($action->created_at)->timezone('Europe/Istanbul')->translatedFormat('d M Y H:i') }}</td></tr>@empty<tr><td colspan="5">Henüz işlem yapılmadı.</td></tr>@endforelse
</tbody></table></div></div>
@endsection