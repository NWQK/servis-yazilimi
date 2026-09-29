@extends('layouts.app')
@section('page-title', 'Müşteri fatura bilgileri')
@section('content')
<div class="card"><div class="card-body">
    <h5>Müşteri bilgileri ekle</h5>
    <p>Tüm alanlar isteğe bağlıdır. Bu işlem fatura tutarını, stokları ve tahsilatları değiştirmez.</p>
    <form method="post" action="{{ route('invoice.billing.update', $invoice->id) }}">
        @csrf @method('PUT')
        @include('invoice.billing_fields', ['business' => false])
        <a class="btn btn-secondary" href="{{ route('invoice.show', encrypt($invoice->id)) }}">Vazgeç</a>
        <button type="submit" class="btn btn-primary">Bilgileri kaydet</button>
    </form>
</div></div>
@endsection
