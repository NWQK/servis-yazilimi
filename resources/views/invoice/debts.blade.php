@extends('layouts.app')
@section('page-title', 'Borç takibi')
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('invoice.index') }}">Faturalar</a></li>
    <li class="breadcrumb-item">Borç takibi</li>
@endsection
@section('content')
    <div class="row g-3">
        <div class="col-12 col-md-6"><div class="card"><div class="card-body">
            <p class="text-muted mb-1">Toplam kalan borç</p><h3 class="mb-0 text-danger">{{ priceFormat($totalDue) }}</h3>
        </div></div></div>
        <div class="col-12 col-md-6"><div class="card"><div class="card-body">
            <p class="text-muted mb-1">Ödemesi tamamlanmamış fatura</p><h3 class="mb-0">{{ $debts->count() }}</h3>
        </div></div></div>
    </div>
    <div class="card table-card">
        <div class="card-header">
            <h5>Borç takibi</h5>
            <p class="text-muted mb-0 mt-2">Kısmen ödenen ve hiç ödeme alınmayan faturalar burada listelenir. Ödeme eklendiğinde kalan tutar güncellenir; borcu kapanan fatura bu listeden çıkar.</p>
        </div>
        <div class="card-body pt-0">
            @if($debts->isEmpty())
                <div class="text-center py-5"><i class="ti ti-circle-check text-success fs-1"></i><h5 class="mt-3">Ödemesi beklenen fatura bulunmuyor.</h5></div>
            @else
                <div class="dt-responsive table-responsive">
                    <table class="table table-hover advance-datatable">
                        <thead><tr><th>Fatura</th><th>Müşteri</th><th>Fatura tarihi</th><th>Fatura tutarı</th><th>Ödenen</th><th>Kalan borç</th><th>Durum</th><th>İşlemler</th></tr></thead>
                        <tbody>
                        @foreach($debts as $row)
                            @php($invoice = $row['invoice'])
                            <tr>
                                <td>{{ invoicePrefix() . $invoice->invoice_id }}</td>
                                <td>{{ $invoice->customer_details['name'] ?? $invoice->clients?->name ?? 'Müşteri kaydı bulunmuyor' }}</td>
                                <td data-order="{{ $invoice->invoice_date }}">{{ dateFormat($invoice->invoice_date) }}</td>
                                <td data-order="{{ $row['total'] }}">{{ priceFormat($row['total']) }}</td>
                                <td data-order="{{ $row['paid'] }}">{{ priceFormat($row['paid']) }}</td>
                                <td class="fw-bold text-danger" data-order="{{ $row['due'] }}">{{ priceFormat($row['due']) }}</td>
                                <td><span class="badge {{ $row['paid'] > 0 ? 'bg-light-warning' : 'bg-light-danger' }}">{{ $row['paid'] > 0 ? 'Kısmen ödendi' : 'Ödenmedi' }}</span></td>
                                <td><div class="d-flex align-items-center gap-1 flex-nowrap">
                                    @can('show invoice')
                                        <a class="avtar avtar-xs btn-link-warning text-warning" href="{{ route('invoice.show', encrypt($invoice->id)) }}" data-bs-toggle="tooltip" data-bs-original-title="Faturayı görüntüle" aria-label="Faturayı görüntüle"><i data-feather="eye"></i></a>
                                        <a class="avtar avtar-xs btn-link-secondary text-secondary" href="{{ route('invoice.show', encrypt($invoice->id)) }}?print=1" target="_blank" rel="noopener" data-bs-toggle="tooltip" data-bs-original-title="Yazdır" aria-label="Yazdır"><i data-feather="printer"></i></a>
                                    @endcan
                                    @can('create invoice payment')
                                        <a href="#" class="avtar avtar-xs btn-link-success text-success customModal" data-url="{{ route('invoice.payment', $invoice->id) }}" data-size="md" data-title="Ödeme ekle" data-bs-toggle="tooltip" data-bs-original-title="Ödeme ekle" aria-label="Ödeme ekle"><i data-feather="credit-card"></i></a>
                                    @endcan
                                </div></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
