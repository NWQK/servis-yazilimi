@extends('layouts.app')
@section('page-title','Stok transferini teslim al')
@section('content')
@include('branches.links')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="card card-body"><h5>Transfer #{{ $transfer->id }} · {{ $transfer->snapshot['title'] }}</h5><p>Gelen miktar: <strong>{{ $transfer->quantity }} {{ $transfer->snapshot['unit_name'] }}</strong></p>
<form method="post" action="{{ route('branch-transfers.resolve',$transfer->id) }}">@csrf
<input type="hidden" name="action" value="receive"><input type="hidden" name="version" value="{{ $transfer->version }}">
<label for="target-item" class="form-label">Stoğun ekleneceği yerel ürün</label><select id="target-item" name="target_item_id" class="form-select"><option value="">Alan şubede yeni ürün oluştur</option>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->title }} · {{ $item->quantity }} {{ $item->unit?->unit }}</option>@endforeach</select>
<label for="target-unit" class="form-label mt-3">Yeni ürün oluşturulacaksa yerel birim</label><select id="target-unit" name="target_unit_id" class="form-select"><option value="">Birim seçin</option>@foreach($units as $unit)<option value="{{ $unit->id }}">{{ $unit->unit }}</option>@endforeach</select>
<p class="text-muted mt-3">Birim gönderen ürünle aynı olmalıdır. Mevcut ürünün fiyatı ve diğer ayarları korunur. Yeni ürün, gönderenin ürün adı ve fiyatlarıyla açılır; kategori, tedarikçi ve vergi kimlikleri taşınmaz. Bunları alan şubede düzenleyebilirsiniz.</p>
<label class="d-block"><input type="checkbox" name="confirm" value="1" class="form-check-input me-2" required> Ürünleri fiziksel olarak teslim aldım ve doğru yerel ürünü seçtim.</label><button class="btn btn-primary mt-3">Teslim al ve stoğa ekle</button>
</form></div>
@endsection
