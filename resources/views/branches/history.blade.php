@extends('layouts.app')
@section('page-title','Şube işlemleri geçmişi')
@section('content')
@include('branches.links')
<div class="card card-body"><h5>{{ $group->name }} · Son 180 gün</h5><p class="text-muted">Şube bağlantıları, personel yetkileri, stok transferleri ve başarılı kayıt işlemleri. Şifre, form içeriği ve müşteri kişisel bilgileri tutulmaz.</p>
<form method="get" class="row g-2"><input type="hidden" name="group" value="{{ $group->id }}"><div class="col-md-5"><select class="form-select" name="owner" aria-label="Şube"><option value="">Tüm şubeler</option>@foreach($owners as $owner)<option value="{{ $owner->id }}" @selected((int)request('owner')===$owner->id)>{{ $owner->name }}</option>@endforeach</select></div><div class="col-md-5"><select class="form-select" name="action" aria-label="İşlem türü"><option value="">Tüm işlemler</option>@foreach($labels as $key=>$label)<option value="{{ $key }}" @selected(request('action')===$key)>{{ $label }}</option>@endforeach</select></div><div class="col-md-2"><button class="btn btn-primary w-100">Filtrele</button></div></form></div>
@forelse($rows as $row)<div class="card card-body"><div class="d-flex flex-wrap gap-2 justify-content-between"><strong>{{ $labels[$row->action] ?? 'İşlem' }}</strong><small>{{ $row->created_at }}</small></div>
<p class="mt-2 mb-0">{{ $row->actor_name }} · {{ $row->owner_id ? ($owners->firstWhere('id',$row->owner_id)?->name ?? 'Ayrılmış / eski şube #'.$row->owner_id) : 'Merkez' }}</p>
@if($row->route_name)<small class="text-muted">{{ \App\Services\BranchActivity::operationLabel($row->route_name) }}</small>@endif
@php($detail=json_decode($row->details,true) ?: [])
@if(isset($detail['record_id']))<small>Kayıt #{{ $detail['record_id'] }}</small>@endif
@if(isset($detail['transfer_id']))<small>Transfer #{{ $detail['transfer_id'] }} · {{ $detail['quantity'] ?? '' }} adet</small>@endif
</div>@empty<div class="card card-body">Bu filtrede işlem bulunamadı.</div>@endforelse
{{ $rows->links() }}
@endsection
