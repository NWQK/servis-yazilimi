@extends('layouts.app')
@section('page-title','Şube bazlı personel erişimi')
@section('content')
@include('branches.links')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="alert alert-info">Yalnızca aynı merkeze bağlı işletmelerin personeline ek şube erişimi verilir. Personelin ana şubesindeki mevcut rolü değişmez. Silme yetkisi için düzenleme yetkisi de açık olmalıdır.</div>
<div class="card card-body"><h5>{{ $group->name }} · Erişim tanımla / güncelle</h5>
<form method="post" action="{{ route('branches.staff.save',$group->id) }}">@csrf
<input type="hidden" name="version" value="{{ $group->version }}"><input type="hidden" name="action" value="grant">
<div class="row g-3 mt-1">
<div class="col-md-6"><label class="form-label" for="staff-user">Personel</label><select id="staff-user" name="user_id" class="form-select" required><option value="">Personel seçin</option>@foreach($staff->where('is_active',1) as $person)<option value="{{ $person->id }}">{{ $person->name }} · Ana şube: {{ $owners->firstWhere('id',$person->parent_id)?->name }}</option>@endforeach</select></div>
<div class="col-md-6"><label class="form-label" for="staff-owner">Ek erişim verilecek şube</label><select id="staff-owner" name="owner_id" class="form-select" required><option value="">Ana şubesi dışında bir şube seçin</option>@foreach($owners as $owner)<option value="{{ $owner->id }}">{{ $owner->name }}</option>@endforeach</select></div>
<div class="col-12"><strong class="d-block mb-2">Görüntülenebilecek modüller</strong><div class="row g-2">@foreach($modules as $key=>$label)<label class="col-md-4"><input type="checkbox" name="modules[]" value="{{ $key }}" class="form-check-input me-2">{{ $label }}</label>@endforeach</div></div>
<div class="col-12"><label class="me-4"><input type="checkbox" class="form-check-input me-2" name="can_write" value="1"> Ekleme ve düzenleme</label><label><input type="checkbox" class="form-check-input me-2" name="can_delete" value="1"> Silme</label><p class="text-muted small mt-2">İşaretlenmezse yalnızca görüntüleme yapılır. Müşteri, servis ve stok gibi bağlantılı modülleri ihtiyaca göre birlikte açın.</p></div>
</div><button class="btn btn-primary mt-3">Erişimi kaydet</button>
</form></div>
@forelse($grants as $grant)
<div class="card card-body"><div class="d-flex flex-wrap gap-3 align-items-center justify-content-between"><div><h6>{{ $staff->firstWhere('id',$grant->user_id)?->name ?? 'Personel #'.$grant->user_id }} → {{ $owners->firstWhere('id',$grant->owner_id)?->name ?? 'Şube #'.$grant->owner_id }}</h6>
<p class="mb-1">{{ collect(json_decode($grant->modules,true))->map(fn($module)=>$modules[$module] ?? $module)->join(', ') }}</p>
<span class="badge bg-light text-dark">{{ $grant->can_write ? 'Ekleme / düzenleme açık' : 'Yalnızca görüntüleme' }}</span> @if($grant->can_delete)<span class="badge bg-danger">Silme açık</span>@endif</div>
<form method="post" action="{{ route('branches.staff.save',$group->id) }}" onsubmit="return confirm('Bu personelin ek şube erişimi kaldırılsın mı?')">@csrf
<input type="hidden" name="version" value="{{ $group->version }}"><input type="hidden" name="action" value="revoke"><input type="hidden" name="user_id" value="{{ $grant->user_id }}"><input type="hidden" name="owner_id" value="{{ $grant->owner_id }}"><button class="btn btn-outline-danger">Erişimi kaldır</button></form></div></div>
@empty<div class="card card-body">Henüz ek şube erişimi tanımlanmadı.</div>@endforelse
@endsection
