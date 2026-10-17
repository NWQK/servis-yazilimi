@extends('layouts.app')
@section('page-title','Personel ayrılış işlemi')
@section('content')
<div class="card"><div class="card-body"><h5>{{ $employee->name }} — personel ayrılışı</h5><p>Hesap ve geçmiş işlemler silinmez. Giriş ve mevcut oturumlar kapatılır, ek şube erişimleri kaldırılır. Tamamlanmış işler aynı personelin adıyla kalır.</p>
@if($employee->departed_at)<div class="alert alert-info">Ayrılış kaydedilmiş: {{ $employee->departed_at }}</div>@else
<form method="post" action="{{ route('employee-departures.store',$employee->id) }}">@csrf
@forelse($services as $owner=>$rows)<div class="border rounded p-3 mb-3"><h6>İşletme #{{ $owner }} · {{ $rows->count() }} açık servis</h6><label class="form-label" for="replacement-{{ $owner }}">İşleri devralacak kişi</label><select id="replacement-{{ $owner }}" class="form-control" name="assignments[{{ $owner }}]" required><option value="">Personel / işletme sahibi seçin</option>@foreach($replacements[$owner] as $person)<option value="{{ $person->id }}">{{ $person->name }}</option>@endforeach</select><details class="mt-2"><summary>Açık servisleri görüntüle</summary><ul>@foreach($rows as $service)<li>Servis #{{ $service->service_id }} · {{ \App\Models\Service::status()[$service->status]??$service->status }}</li>@endforeach</ul></details></div>@empty<p>Açık servis bulunmuyor.</p>@endforelse
<label class="d-block mb-3"><input type="checkbox" name="confirm" value="1" required> Açık işlerin devrini ve personelin erişiminin kapatılmasını onaylıyorum.</label><button class="btn btn-danger">İşleri devret ve erişimi kapat</button></form>@endif</div></div>
@endsection
