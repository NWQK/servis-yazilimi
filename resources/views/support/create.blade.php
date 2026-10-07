@extends('layouts.app')
@section('page-title','Yeni destek bileti')
@section('content')
<div class="card"><div class="card-header"><h5>Yeni destek bileti</h5><p class="text-muted mb-0">Yaşadığınız sorunu ve hangi ekranda oluştuğunu açıklayın. Şifrenizi veya API anahtarlarınızı mesaja yazmayın.</p></div><div class="card-body">
<form method="POST" action="{{ route('support.store') }}">@csrf
<div class="row"><div class="col-12 col-md-8 mb-3"><label class="form-label" for="ticket-subject">Konu</label><input class="form-control" id="ticket-subject" name="subject" maxlength="150" value="{{ old('subject') }}" placeholder="Örneğin: Faturada logo görünmüyor" required>@error('subject')<div class="text-danger">{{ $message }}</div>@enderror</div>
<div class="col-12 col-md-4 mb-3"><label class="form-label" for="ticket-priority">Öncelik</label><select class="form-select" id="ticket-priority" name="priority">@foreach(\App\Models\SupportTicket::PRIORITIES as $value=>$label)<option value="{{ $value }}" @selected(old('priority','normal')===$value)>{{ $label }}</option>@endforeach</select>@error('priority')<div class="text-danger">{{ $message }}</div>@enderror</div></div>
<label class="form-label" for="ticket-body">Mesajınız</label><textarea class="form-control" id="ticket-body" name="body" rows="8" maxlength="10000" required>{{ old('body') }}</textarea>@error('body')<div class="text-danger">{{ $message }}</div>@enderror
<div class="d-flex flex-wrap gap-2 mt-4"><button class="btn btn-primary">Destek bileti oluştur</button><a class="btn btn-light" href="{{ route('support.index') }}">Vazgeç</a></div>
</form></div></div>
@endsection