@extends('layouts.app')
@section('page-title','Şube çalışma alanı')
@section('content')
<div class="card card-body"><h4>{{ $context['owner']->name }}</h4><p>Bu şubede size açılan modüller aşağıdadır. Ana şubenizdeki personel hesabınız ve rolünüz korunur.</p><p class="mb-0"><span class="badge bg-light text-dark">{{ $context['grant']->can_write ? 'Ekleme ve düzenleme açık' : 'Yalnızca görüntüleme' }}</span> @if($context['grant']->can_delete)<span class="badge bg-danger">Silme açık</span>@endif</p></div>
@php($routes=['clients'=>'client.index','vehicles'=>'vehicle.index','services'=>'service.index','stock'=>'item.index','invoices'=>'invoice.index','finance'=>'report.income'])
<div class="row g-3">@foreach($modules as $module)<div class="col-md-6 col-lg-4"><a href="{{ route($routes[$module]) }}" class="card card-body text-decoration-none"><h5>{{ \App\Services\BranchPermissions::MODULES[$module] }}</h5><span>Modülü aç →</span></a></div>@endforeach</div>
@endsection
