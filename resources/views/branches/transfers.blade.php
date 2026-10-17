@extends('layouts.app')
@section('page-title','Şubeler arası stok transferi')
@section('content')
@include('branches.links')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="alert alert-info">Gönderilen miktar gönderen şubenin kullanılabilir stoğundan düşer. Alan şube teslim alınca kendi stoğuna eklenir. İptal / iade, miktarı gönderen şubeye geri ekler. Satış, tahsilat veya alış gideri oluşmaz.</div>
@if($sources->isNotEmpty() && $group->is_active)
<div class="card card-body"><h5>Yeni transfer</h5>
<form method="get" class="mb-3"><input type="hidden" name="group" value="{{ $group->id }}"><label class="form-label" for="source">Gönderen şube</label><select class="form-select" name="source" id="source" onchange="this.form.submit()">@foreach($sources as $owner)<option value="{{ $owner->id }}" @selected($selected===$owner->id)>{{ $owner->name }}</option>@endforeach</select><noscript><button>Şubeyi seç</button></noscript></form>
<form method="post" action="{{ route('branch-transfers.send') }}">@csrf
<input type="hidden" name="group" value="{{ $group->id }}"><input type="hidden" name="source_owner_id" value="{{ $selected }}"><input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}">
<div class="row g-3"><div class="col-md-6"><label for="target" class="form-label">Alan şube</label><select id="target" name="target_owner_id" class="form-select" required><option value="">Şube seçin</option>@foreach($owners->where('id','!=',$selected) as $owner)<option value="{{ $owner->id }}">{{ $owner->name }}</option>@endforeach</select></div>
<div class="col-md-6"><label for="source-item" class="form-label">Gönderilecek ürün</label><select id="source-item" name="source_item_id" class="form-select" required><option value="">Ürün seçin</option>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->title }} · {{ $item->quantity }} {{ $item->unit?->unit }}</option>@endforeach</select></div>
<div class="col-md-4"><label for="quantity" class="form-label">Miktar</label><input id="quantity" name="quantity" type="number" min="1" max="100000000" step="1" class="form-control" required></div><div class="col-md-8"><label for="transfer-note" class="form-label">Not (isteğe bağlı)</label><input id="transfer-note" name="note" maxlength="500" class="form-control"></div></div>
<label class="d-block mt-3"><input type="checkbox" class="form-check-input me-2" name="confirm" value="1" required> Ürünün seçilen şubeye gönderileceğini ve kullanılabilir stoktan düşeceğini onaylıyorum.</label><button class="btn btn-primary mt-3">Transferi gönder</button></form></div>
@endif
@php($statuses=['pending'=>'Teslim bekliyor','received'=>'Teslim alındı','cancelled'=>'İptal / iade edildi'])
@forelse($rows as $row)<div class="card card-body"><div class="d-flex flex-wrap gap-2 justify-content-between"><h6>#{{ $row->id }} · {{ $row->snapshot['title'] }}</h6><span class="badge {{ $row->status==='pending'?'bg-warning text-dark':'bg-light text-dark' }}">{{ $statuses[$row->status] }}</span></div>
<p>{{ $row->snapshot['source_owner_name'] ?? 'Eski şube #'.$row->source_owner_id }} → {{ $row->snapshot['target_owner_name'] ?? 'Eski şube #'.$row->target_owner_id }}<br><strong>{{ $row->quantity }} {{ $row->snapshot['unit_name'] }}</strong> · {{ $row->created_at }}</p>@if($row->note)<p>{{ $row->note }}</p>@endif
@if($row->status==='pending')<div class="d-flex flex-wrap gap-3 align-items-center">
@if($canReceive[$row->target_owner_id] ?? false)<a class="btn btn-primary" href="{{ route('branch-transfers.receipt',$row->id) }}">Teslim al</a>@endif
@if(($canReceive[$row->source_owner_id] ?? false) || ($canReceive[$row->target_owner_id] ?? false))
<form method="post" action="{{ route('branch-transfers.resolve',$row->id) }}" onsubmit="return confirm('Ürünler gönderen şubede kaldı veya geri teslim edildi mi? Onaylarsanız stok gönderen şubeye iade edilir.')">@csrf
<input type="hidden" name="action" value="cancel"><input type="hidden" name="version" value="{{ $row->version }}"><input type="hidden" name="confirm" value="1"><button class="btn btn-outline-danger">İptal / iade et</button></form>@endif</div>@endif
</div>@empty<div class="card card-body">Henüz erişebildiğiniz bir transfer yok.</div>@endforelse
{{ $rows->links() }}
@endsection
