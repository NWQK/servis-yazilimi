@extends('layouts.app')
@section('page-title','Stok hareket geçmişi')
@section('content')
@include('history.mobile-style')
<div class="card"><div class="card-body"><h5>Stok hareket geçmişi</h5><p class="text-muted">Son 2 yılın miktar değişiklikleri. Geçmişin temizlenmesi mevcut stok miktarını değiştirmez.</p>
@php($kinds=['purchase'=>'Ürün alımı','adjustment'=>'Stok düzeltme','sale'=>'Faturaya ekleme','return'=>'Stok iadesi','counter_sale'=>'Elden satış','transfer_out'=>'Şubeye gönderim','transfer_in'=>'Şubeden teslim alım','transfer_return'=>'Transfer iadesi'])
<form method="get" class="d-flex gap-2 mb-3"><select class="form-select" name="kind"><option value="">Tüm hareketler</option>@foreach($kinds as $key=>$label)<option value="{{ $key }}" @selected(request('kind')===$key)>{{ $label }}</option>@endforeach</select><button class="btn btn-secondary">Filtrele</button></form>
<div class="table-responsive"><table class="table align-middle history-grid-table stock-grid"><thead><tr><th>Tarih</th><th>Ürün</th><th>İşlem</th><th>Miktar</th><th>Personel</th><th>Fatura</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td data-label="Tarih" class="text-nowrap">{{ $row->created_at }}</td><td data-label="Ürün">{{ $row->title ?? 'Eski / silinmiş ürün' }}</td><td data-label="İşlem">{{ $kinds[$row->kind] ?? $row->kind }}</td><td data-label="Miktar" class="{{ $row->quantity<0 ? 'text-danger' : 'text-success' }}">{{ $row->quantity>0 ? '+' : '' }}{{ $row->quantity }}</td><td data-label="Personel">{{ $row->actor_name ?? 'Eski kayıt / sistem' }}</td><td data-label="Fatura">@if($row->invoice_id)<a href="{{ route('history.invoices',['invoice'=>$row->invoice_id]) }}">#{{ $row->invoice_id }}</a>@else — @endif</td></tr>@empty<tr><td colspan="6">Henüz hareket yok.</td></tr>@endforelse
</tbody></table></div>{{ $rows->links() }}</div></div>
@endsection
