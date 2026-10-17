@extends('layouts.app')
@section('page-title','Şube karşılaştırmalı rapor')
@section('content')
@include('branches.links')
<div class="card card-body"><h5>{{ $group->name }}</h5><form method="get" class="row g-3"><input type="hidden" name="group" value="{{ $group->id }}"><div class="col-md-5"><label class="form-label" for="start">Başlangıç</label><input id="start" type="date" name="start" value="{{ $start }}" class="form-control" required></div><div class="col-md-5"><label class="form-label" for="end">Bitiş</label><input id="end" type="date" name="end" value="{{ $end }}" class="form-control" required></div><div class="col-md-2 align-self-end"><button class="btn btn-primary w-100">Göster</button></div></form></div>
<div class="alert alert-info">Tahsilat, onaylı fatura ödemeleri ve elden satışlardan oluşur. Tahsilat − gider farkı nakit esaslıdır; muhasebesel kâr değildir. Açık borç, tarih filtresinden bağımsız güncel toplamdır. Stok transferleri gelir veya gider oluşturmaz; alış gideri ilk alımın yapıldığı şubede kalır.</div>
@php($metrics=['services'=>'Servis sayısı','invoices'=>'Fatura sayısı','billed'=>'Fatura toplamı','collections'=>'Tahsilat','expenses'=>'Gider','cash_difference'=>'Tahsilat − gider','outstanding'=>'Güncel açık borç'])
<div class="row g-3">
@foreach($rows as $row)<div class="col-md-6 col-xl-4"><div class="card h-100"><div class="card-header"><h5>{{ $row['name'] }}</h5></div><div class="card-body">@foreach($metrics as $key=>$label)<div class="d-flex justify-content-between gap-3 py-2 border-bottom"><span>{{ $label }}</span><strong class="text-end text-nowrap">{{ in_array($key,['services','invoices']) ? number_format($row[$key],0,',','.') : number_format($row[$key],2,',','.').' ₺' }}</strong></div>@endforeach</div></div></div>@endforeach
</div>
<div class="card card-body mt-3"><h5>Merkez toplamı</h5><div class="row g-3">@foreach($metrics as $key=>$label)<div class="col-6 col-lg-3"><small>{{ $label }}</small><strong class="d-block">{{ in_array($key,['services','invoices']) ? number_format(collect($rows)->sum($key),0,',','.') : number_format(collect($rows)->sum($key),2,',','.').' ₺' }}</strong></div>@endforeach</div></div>
@endsection
