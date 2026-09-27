@extends('layouts.app')
@section('page-title', 'Randevu hizmetleri')
@section('content')
<div class="card"><div class="card-body"><h4>Genel randevu hizmetleri</h4><p>İşletmeler bu listeden sundukları hizmetleri seçer. Pasif hizmetler yeni aramalarda gösterilmez; eski randevu kayıtları korunur.</p><a href="{{ route('directory.index') }}" target="_blank" rel="noopener">Genel randevu sayfasını aç ↗</a>@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif</div></div>
@foreach($services->concat([new \App\Models\BookingService(['is_active'=>true,'vehicle_types'=>array_keys($vehicles)])]) as $service)
<form method="post" action="{{ route('appointments.catalog.save') }}" class="card">@csrf<div class="card-body"><h5>{{ $service->exists ? $service->name : 'Yeni hizmet ekle' }}</h5>@if($service->exists)<input type="hidden" name="id" value="{{ $service->id }}">@endif<label>Hizmet adı<input class="form-control" name="name" required maxlength="100" value="{{ $service->name }}"></label><div class="my-3">@foreach($vehicles as $key=>$label)<label class="me-3"><input type="checkbox" name="vehicle_types[]" value="{{ $key }}" @checked(in_array($key,$service->vehicle_types ?? []))> {{ $label }}</label>@endforeach</div><input type="hidden" name="is_active" value="0"><label class="me-3"><input type="checkbox" name="is_active" value="1" @checked($service->is_active)> Aktif</label><button class="btn btn-secondary">{{ $service->exists ? 'Kaydet' : 'Hizmet ekle' }}</button></div></form>
@endforeach
@endsection
