@extends('layouts.app')
@section('page-title','Şube merkezleri')
@section('content')
<div class="alert alert-info">Her şube kendi müşteri, araç, servis, fatura, stok ve paket kayıtlarını korur. Bağlama / ayırma yalnızca merkez erişimini değiştirir. Şube hesabıyla giriş yapılmaya devam edilebilir.</div>
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="card"><div class="card-header"><h5>Merkez hesabı oluştur</h5></div><div class="card-body">
<form method="post" action="{{ route('branches.store') }}">@csrf
<div class="row g-3">
@foreach(['name'=>'Merkez / grup adı','manager_name'=>'Yönetici adı','email'=>'Yönetici e-postası','password'=>'Şifre (en az 12 karakter)','password_confirmation'=>'Şifre tekrarı'] as $key=>$label)
<div class="col-md-6"><label class="form-label" for="{{ $key }}">{{ $label }}</label><input id="{{ $key }}" class="form-control" name="{{ $key }}" type="{{ str_starts_with($key,'password') ? 'password' : ($key === 'email' ? 'email' : 'text') }}" required maxlength="{{ str_starts_with($key,'password') ? 100 : 191 }}" autocomplete="{{ str_starts_with($key,'password') ? 'new-password' : 'off' }}"></div>
@endforeach
</div><p class="text-muted mt-3">Bu hesap demo paketi oluşturmaz. Şubelerin paket ve kapasite sınırları ayrı ayrı geçerlidir. Giriş bilgilerini yöneticinizle güvenli biçimde paylaşın.</p><button class="btn btn-primary">Merkez oluştur</button>
</form></div></div>
@forelse($groups as $group)
<div class="card"><div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center"><div><h5>{{ $group->name }}</h5><small>{{ $group->manager->name }} · {{ $group->manager->email }} · {{ $group->is_active ? 'Merkez erişimi açık' : 'Merkez erişimi askıda' }}</small></div>
<form method="post" action="{{ route('branches.status',$group->id) }}">@csrf<input type="hidden" name="version" value="{{ $group->version }}"><input type="hidden" name="active" value="{{ $group->is_active ? 0 : 1 }}"><button class="btn btn-outline-primary">{{ $group->is_active ? 'Merkezi askıya al' : 'Merkez erişimini aç' }}</button></form></div>
<div class="card-body">
@include('branches.links')
@forelse($group->owners as $owner)
<div class="d-flex flex-wrap gap-3 justify-content-between align-items-center border-bottom py-3"><div><strong>{{ $owner->name }}</strong><div class="text-muted">{{ $owner->email }} · İşletme #{{ $owner->id }}</div></div>
<form method="post" action="{{ route('branches.membership',$group->id) }}" onsubmit="return confirm('Bu şubenin merkez erişimi kaldırılacak. İşletme kayıtları ve kendi hesabı korunacak. Devam edilsin mi?')">@csrf
<input type="hidden" name="owner_id" value="{{ $owner->id }}"><input type="hidden" name="version" value="{{ $group->version }}"><input type="hidden" name="action" value="detach"><input type="hidden" name="confirm" value="1"><button class="btn btn-outline-danger">Şubeyi ayır</button></form></div>
@empty<p>Henüz şube bağlanmadı.</p>@endforelse
@if($available->isNotEmpty())
<form class="row g-2 mt-3" method="post" action="{{ route('branches.membership',$group->id) }}" onsubmit="return confirm('Merkez yöneticisi seçilen işletmenin kayıtlarını görebilecek ve yönetebilecek. Mevcut kayıtlar taşınmayacak. Bağlansın mı?')">@csrf
<input type="hidden" name="version" value="{{ $group->version }}"><input type="hidden" name="action" value="attach"><input type="hidden" name="confirm" value="1">
<div class="col-md-9"><label class="form-label" for="owner-{{ $group->id }}">Mevcut işletmeyi şube olarak bağla</label><select id="owner-{{ $group->id }}" class="form-select" name="owner_id" required><option value="">İşletme seçin</option>@foreach($available as $owner)<option value="{{ $owner->id }}">{{ $owner->name }} · {{ $owner->email }} (#{{ $owner->id }})</option>@endforeach</select></div><div class="col-md-3 align-self-end"><button class="btn btn-primary w-100">Şube bağla</button></div>
</form>@endif
</div></div>
@empty<div class="card card-body">Henüz şube merkezi yok.</div>@endforelse
@endsection
