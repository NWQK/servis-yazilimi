@extends('layouts.app')
@section('page-title','Gün sonu kasa')
@section('content')
<div class="card"><div class="card-body"><div class="d-flex flex-wrap justify-content-between gap-3"><div><h5>Gün sonu kasa kontrolü</h5><p class="text-muted">Tahsilat ve giderlerin ödeme yöntemine göre özeti. Kapanış gelir veya gider oluşturmaz.</p></div>
<form method="get" action="{{ route('report.cash') }}" class="d-flex gap-2 align-items-start"><input aria-label="Kasa tarihi" type="date" name="date" value="{{ $date }}" max="{{ now('Europe/Istanbul')->toDateString() }}" class="form-control" required><button class="btn btn-secondary">Göster</button></form></div>
<div class="row g-3 mt-1">@foreach(\App\Services\CashRegister::CHANNELS as $channel=>$label)<div class="col-12 col-sm-6 col-xl-3"><div class="border rounded p-3 h-100"><h6>{{ $label }}</h6><p class="mb-1">Tahsilat: <strong>{{ priceFormat($summary['income'][$channel]/100) }}</strong></p><p class="mb-0">Gider: <strong>{{ priceFormat($summary['expense'][$channel]/100) }}</strong></p></div></div>@endforeach</div>
<p class="text-muted mt-3">Eski kayıtlar ve ödeme yöntemi seçilmeyen stok alımları “Belirtilmemiş” altında görünür; nakit kasaya otomatik dahil edilmez. Alış giderinin kayıtlı olması, nakit ödendiği anlamına gelmez. Kasa giriş/çıkışlarını gider ve tahsilat kayıtlarında eksiksiz belirtin.</p>
@if($closing)
<div class="alert {{ $closing->source_hash!==$summary['hash']?'alert-warning':'alert-success' }}"><strong>{{ $date }} kapanışı kayıtlı.</strong> {{ $closing->actor_name }} · {{ $closing->created_at }}
@if($closing->source_hash!==$summary['hash'])<p class="mb-0">Kapanıştan sonra bu güne ait kayıtlar değişti. Aşağıdaki kapanış tutarları eski anlık kayıttır; üstteki toplamlar günceldir.</p>@endif</div>
<div class="row g-3">@foreach(['opening'=>'Açılış nakdi','expected'=>'Beklenen nakit','counted'=>'Sayılan nakit','difference'=>'Kasa farkı'] as $field=>$label)<div class="col-sm-6 col-xl-3"><p>{{ $label }}<strong class="d-block">{{ priceFormat($closing->$field) }}</strong></p></div>@endforeach</div>
@if($closing->note)<p>Not: {{ $closing->note }}</p>@endif
@else
@can('create expense')<form method="post" action="{{ route('report.cash.close') }}">@csrf
<input type="hidden" name="business_date" value="{{ $date }}"><input type="hidden" name="source_hash" value="{{ $summary['hash'] }}">
<div class="row g-3"><div class="col-md-6"><label class="form-label" for="opening">Açılış nakdi (₺)</label><input class="form-control" id="opening" name="opening" type="number" min="0" max="999999999.99" step="0.01" value="{{ old('opening',$previous->counted??0) }}" required><small>Önceki kapanış varsa önerilir. Fiili açılış tutarını kontrol edin.</small></div>
<div class="col-md-6"><label class="form-label" for="counted">Kasada sayılan nakit (₺)</label><input class="form-control" id="counted" name="counted" type="number" min="0" max="999999999.99" step="0.01" value="{{ old('counted') }}" required></div>
<div class="col-12"><p>Beklenen nakit = açılış nakdi + nakit tahsilat − nakit gider. Kart ve havale bu hesaba girmez.</p>
<div class="border rounded p-3 mb-3" id="cash-preview" data-net="{{ $summary['income']['cash']-$summary['expense']['cash'] }}">Beklenen nakit: <strong id="cash-expected">—</strong><br>Kasa farkı: <strong id="cash-difference">—</strong></div>
<label for="cash-note" class="form-label">Açıklama (isteğe bağlı)</label><textarea id="cash-note" name="note" maxlength="1000" class="form-control" rows="2">{{ old('note') }}</textarea></div>
<div class="col-12"><label><input type="checkbox" name="confirm" value="1" required> Belirtilmemiş kayıtları kontrol ettim. Bu günün kasa sayımını kaydetmeyi onaylıyorum.</label></div><div class="col-12"><button class="btn btn-secondary">Gün sonunu kaydet</button></div></div></form>@endcan
@endif
</div></div>
<div class="card"><div class="card-body"><h5>Kapanış geçmişi</h5><div class="table-responsive"><table class="table"><thead><tr><th>Gün</th><th>Beklenen</th><th>Sayılan</th><th>Fark</th><th>Kaydeden</th></tr></thead><tbody>@forelse($history as $row)<tr><td><a href="{{ route('report.cash',['date'=>$row->business_date]) }}">{{ $row->business_date }}</a></td><td>{{ priceFormat($row->expected) }}</td><td>{{ priceFormat($row->counted) }}</td><td>{{ priceFormat($row->difference) }}</td><td>{{ $row->actor_name }}</td></tr>@empty<tr><td colspan="5">Henüz kapanış yok.</td></tr>@endforelse</tbody></table></div>{{ $history->links() }}</div></div>
@endsection
@push('script-page')<script src="{{ asset('js/cash-register.js') }}" defer></script>@endpush
