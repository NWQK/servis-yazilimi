@extends('layouts.app')
@section('page-title', $customer ? 'Müşteri düzenle' : 'Müşteri ekle')
@section('breadcrumb')
<li class="breadcrumb-item"><a href="{{ route('client.index') }}">Müşteriler</a></li><li class="breadcrumb-item">{{ $customer ? 'Müşteri düzenle' : 'Müşteri ekle' }}</li>
@endsection
@section('content')
<div class="row justify-content-center"><div class="col-12 col-xl-9"><div class="card">
<div class="card-header"><h5>{{ $customer ? 'Müşteri bilgileri' : 'Müşteri ekle' }}</h5><p class="text-muted mb-0">Yalnızca müşteri bilgilerini kaydedin. Araç ve servis kayıtlarını daha sonra ekleyebilirsiniz.</p></div>
<div class="card-body"><form method="post" action="{{ $customer ? route('client.simple.update',$customer->id) : route('client.simple.store') }}">
@csrf @if($customer) @method('PUT') @endif
@if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
<div class="row g-3">
<div class="col-12 col-md-6"><label for="name" class="form-label">Ad soyad <span class="text-danger">*</span></label><input id="name" name="name" class="form-control" maxlength="255" required value="{{ old('name',$customer?->name) }}"></div>
<div class="col-12 col-md-6"><label for="phone_number" class="form-label">Telefon <span class="text-danger">*</span></label><x-tr-phone :required="true" :value="$customer?->phone_number ?? ''" /></div>
<div class="col-12 col-md-6"><label for="email" class="form-label">E-posta (isteğe bağlı)</label><input id="email" name="email" type="email" maxlength="255" class="form-control" value="{{ old('email',$customer?->email) }}"></div>
@foreach(['city'=>'İl','state'=>'İlçe','zip_code'=>'Posta kodu'] as $field=>$label)
<div class="col-12 col-md-6"><label for="{{ $field }}" class="form-label">{{ $label }} (isteğe bağlı)</label><input id="{{ $field }}" name="{{ $field }}" class="form-control" maxlength="255" value="{{ old($field,$profile?->$field) }}"></div>
@endforeach
@foreach(['address'=>'Adres','notes'=>'Not'] as $field=>$label)
<div class="col-12 col-md-6"><label for="{{ $field }}" class="form-label">{{ $label }} (isteğe bağlı)</label><textarea id="{{ $field }}" name="{{ $field }}" class="form-control" rows="3" maxlength="{{ $field === 'address' ? 1000 : 5000 }}">{{ old($field,$profile?->$field) }}</textarea></div>
@endforeach
<div class="col-12 d-flex flex-wrap gap-2 justify-content-end mt-4"><a class="btn btn-light" href="{{ route('client.index') }}">Vazgeç</a><button class="btn btn-secondary" type="submit">{{ $customer ? 'Değişiklikleri kaydet' : 'Müşteriyi kaydet' }}</button></div>
</div></form></div></div></div></div>
@endsection
