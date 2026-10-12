@extends('layouts.app')
@section('page-title', 'Satın alım geçmişi')
@section('breadcrumb')<li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li><li class="breadcrumb-item"><a href="{{ route('item.index') }}">Ürünler ve stok</a></li><li class="breadcrumb-item">Satın alım geçmişi</li>@endsection
@section('content')
<div class="card table-card"><div class="card-header"><h5>Satın alım geçmişi</h5><p class="mb-0 text-muted">Son 2 yılda kaydedilen alımlar. Ürün veya tedarikçi silinse de alım bilgileri saklama süresi boyunca korunur. Stok iadeleri ve azaltmaları alım sayılmaz. Özellik etkinleştirilmeden önceki alımlar bu listeye aktarılmaz.</p>
@if(request()->filled('item'))<a href="{{ route('item.purchases') }}" class="btn btn-sm btn-outline-secondary mt-2">Tüm alımları göster</a>@endif</div>
<div class="card-body"><div class="table-responsive"><table class="table table-hover">
<thead><tr><th>Alım tarihi</th><th>Ürün</th><th>Tedarikçi</th><th>Miktar</th><th>Birim alış fiyatı</th><th>Toplam</th><th>Not</th></tr></thead>
<tbody>@forelse($purchases as $purchase)<tr><td class="text-nowrap">{{ dateFormat($purchase->purchase_date) }}</td><td>{{ $purchase->item_title }}</td><td>{{ $purchase->supplier_name ?: 'Tedarikçi seçilmedi' }}</td><td>{{ $purchase->quantity }} {{ $purchase->unit_name }}</td><td>{{ priceFormat($purchase->unit_price) }}</td><td>{{ priceFormat($purchase->amount) }}</td><td style="min-width:150px;max-width:300px;white-space:normal;overflow-wrap:anywhere">{{ $purchase->notes ?: '—' }}</td></tr>
@empty<tr><td colspan="7" class="text-center py-4">Satın alım kaydı bulunmuyor.</td></tr>@endforelse</tbody>
</table></div>{{ $purchases->links() }}</div></div>
@endsection
