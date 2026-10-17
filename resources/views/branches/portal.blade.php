@extends('layouts.app')
@section('page-title','Şubelerim')
@section('content')
@include('branches.links')
<div class="card"><div class="card-body"><h3>{{ $group->name }}</h3><p class="text-muted mb-0">Yönetmek istediğiniz şubeyi açın. Her şubenin kayıtları, stokları ve aboneliği ayrıdır. Farklı sekmelerde farklı şubelerle çalışabilirsiniz.</p></div></div>
@unless($staffMode)<div class="row g-3 mb-4">
@foreach(['vehicle_count'=>'Toplam araç','client_count'=>'Toplam müşteri kaydı','month_services'=>'Bu ay servis','month_invoices'=>'Bu ay fatura'] as $key=>$label)
<div class="col-6 col-lg-3"><div class="card h-100 mb-0"><div class="card-body"><small class="text-muted">{{ $label }}</small><h3 class="mt-2 mb-0">{{ number_format($totals[$key],0,',','.') }}</h3></div></div></div>
@endforeach</div>@endunless
<div class="row g-3">
@forelse($owners as $owner)
<div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-body"><h5>{{ $owner->name }}</h5>@unless($staffMode)<p class="text-muted">{{ $owner->email }}</p>
<dl class="row"><dt class="col-7">Kayıtlı araç</dt><dd class="col-5 text-end">{{ $owner->vehicle_count }}</dd><dt class="col-7">Müşteri kaydı</dt><dd class="col-5 text-end">{{ $owner->client_count }}</dd><dt class="col-7">Bu ay servis</dt><dd class="col-5 text-end">{{ $owner->month_services }}</dd><dt class="col-7">Bu ay randevu</dt><dd class="col-5 text-end">{{ $owner->month_appointments }}</dd><dt class="col-7">Paket</dt><dd class="col-5 text-end">{{ $owner->subscriptions?->title ?? 'Paket yok' }}</dd></dl>@endunless
@if(!$owner->is_active || $owner->subscription_suspended_at)<div class="alert alert-warning">Şube hesabı / aboneliği askıda.</div>@else<a href="{{ $links[$owner->id] }}" class="btn btn-primary w-100">Şubeyi yönet</a>@endif
<small class="d-block text-muted mt-3">Paket değişiklikleri şubenin kendi hesabından yapılır.</small>
</div></div></div>
@empty<div class="col-12"><div class="card card-body">Henüz bağlı şubeniz yok. Süper yönetici mevcut işletmelerinizi bu merkeze bağlayabilir.</div></div>@endforelse
</div>
@endsection
