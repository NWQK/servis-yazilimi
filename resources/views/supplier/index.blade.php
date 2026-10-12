@extends('layouts.app')
@section('page-title', 'Tedarikçiler')
@section('breadcrumb')<li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li><li class="breadcrumb-item">Tedarikçiler</li>@endsection
@section('content')
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div class="card table-card">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2"><div><h5>Tedarikçi defteri</h5><p class="text-muted mb-0">Firma ve iletişim bilgilerinizi tek yerde tutun.</p></div>
        @can('create item')<a href="#" class="btn btn-secondary customModal" data-size="lg" data-url="{{ route('supplier.create') }}" data-title="Tedarikçi ekle"><i class="ti ti-plus"></i> Tedarikçi ekle</a>@endcan
    </div>
    <div class="card-body pt-0"><div class="table-responsive"><table class="table table-hover advance-datatable">
        <thead><tr><th>Firma</th><th>Firma personeli</th><th>Telefon</th><th>E-posta</th><th>Ürün sayısı</th><th>İşlemler</th></tr></thead>
        <tbody>@foreach($suppliers as $supplier)<tr>
            <td>{{ $supplier->company_name }}</td><td>{{ $supplier->contact_name ?: '—' }}</td><td class="text-nowrap">{{ $supplier->phone ? '+90 '.$supplier->phone : '—' }}</td><td>{{ $supplier->email ?: '—' }}</td>
            <td><a href="{{ route('item.index', ['supplier'=>$supplier->id]) }}">{{ $supplier->items_count }}</a></td>
            <td><div class="d-flex align-items-center gap-1 flex-nowrap">
                @if($supplier->whatsapp_url)<a href="{{ $supplier->whatsapp_url }}" target="_blank" rel="noopener noreferrer" class="avtar avtar-xs btn-link-success text-success" data-bs-toggle="tooltip" data-bs-original-title="WhatsApp'tan ulaş" aria-label="WhatsApp'tan ulaş"><i class="ti ti-brand-whatsapp" style="font-size:20px"></i></a>@endif
                <a href="#" class="avtar avtar-xs btn-link-warning text-warning customModal" data-url="{{ route('supplier.show', $supplier->id) }}" data-title="İletişim bilgileri" data-bs-toggle="tooltip" data-bs-original-title="İletişim bilgileri" aria-label="İletişim bilgileri"><i data-feather="phone"></i></a>
                @can('edit item')<a href="#" class="avtar avtar-xs btn-link-secondary text-secondary customModal" data-size="lg" data-url="{{ route('supplier.edit', $supplier->id) }}" data-title="Tedarikçiyi düzenle" aria-label="Tedarikçiyi düzenle"><i data-feather="edit"></i></a>@endcan
                @can('delete item')<form method="post" action="{{ route('supplier.destroy', $supplier->id) }}" class="m-0">@csrf @method('DELETE')<a href="#" class="avtar avtar-xs btn-link-danger text-danger confirm_dialog" aria-label="Tedarikçiyi sil" data-bs-toggle="tooltip" data-bs-original-title="Tedarikçiyi sil; ürünler ve alım geçmişi korunur"><i data-feather="trash-2"></i></a></form>@endcan
            </div></td>
        </tr>@endforeach</tbody>
    </table></div></div>
</div>
@endsection
