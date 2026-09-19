@extends('layouts.app')

@section('page-title')
    {{ __('Invoices') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
    </li>
    <li class="breadcrumb-item" aria-current="page">

        {{ __('Invoices') }}
    </li>
@endsection

@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5>{{ __('Invoices List') }}</h5>
                        </div>
                        @if (Gate::check('create invoice'))
                            <div class="col-auto">
                                <a class="btn btn-secondary" href="{{ route('invoice.create') }}" data-size="lg"
                                    data-url="" data-title="{{ __('Create Invoice') }}"> <i
                                        class="ti ti-circle-plus align-text-bottom"></i>
                                    {{ __('Create Invoice') }}
                                </a>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="card-body pt-0">
                    <div class="dt-responsive table-responsive">
                        <table class="table table-hover advance-datatable">
                            <thead>
                                <tr>
                                    <th> {{ __('Invoice') }}</th>
                                    <th> {{ __('Client') }}</th>
                                    <th> {{ __('Service') }}</th>
                                    <th> {{ __('Invoice Date') }}</th>
                                    <th> {{ __('Total Amount') }}</th>
                                    <th> {{ __('Status') }}</th>
                                    @if (Gate::check('edit invoice') || Gate::check('delete invoice') || Gate::check('show invoice'))
                                        <th class="text-right"> {{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoices as $invoice)
                                    <tr>
                                        <td>{{ invoicePrefix() . $invoice->invoice_id }}</td>
                                        <td>{{ !empty($invoice->clients) ? $invoice->clients->name : '-' }}</td>
                                        <td>
                                            {{ servicePrefix() }}{{ !empty($invoice->services) ? $invoice->services->service_id : '' }}
                                        </td>
                                        <td>{{ dateFormat($invoice->invoice_date) }}</td>
                                        <td>{{ priceFormat( number_format($invoice->getInvoiceAllTotalAmount(), 2)) }}</td>
                                        <td>
                                            @if ($invoice->status == 0)
                                                <span
                                                    class="badge bg-light-danger ml-3">{{ __(\App\Models\Invoice::statues()[$invoice->status]) }}</span>
                                            @elseif($invoice->status == 1)
                                                <span
                                                    class="badge bg-light-warning ml-3">{{ __(\App\Models\Invoice::statues()[$invoice->status]) }}</span>
                                            @elseif($invoice->status == 2)
                                                <span
                                                    class="badge bg-light-success ml-3">{{ __(\App\Models\Invoice::statues()[$invoice->status]) }}</span>
                                            @endif
                                        </td>
                                        @if (Gate::check('edit invoice') || Gate::check('delete invoice') || Gate::check('show invoice'))
                                            <td>
                                                <div class="cart-action">
                                                    {!! Form::open(['method' => 'DELETE', 'route' => ['invoice.destroy', $invoice->id]]) !!}
                                                    @can('show invoice')
                                                        <a class="avtar avtar-xs btn-link-warning text-warning" data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Detail') }}"
                                                            href="{{ route('invoice.show', \Illuminate\Support\Facades\Crypt::encrypt($invoice->id)) }}">
                                                            <i data-feather="eye"></i></a>
                                                    @endcan
                                                    @can('edit invoice')
                                                        <a class="avtar avtar-xs btn-link-secondary text-secondary"
                                                            data-size="lg" data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Edit') }}" href="{{ route('invoice.edit',  encrypt($invoice->id)) }}"
                                                            data-title="{{ __('Edit') }}"> <i data-feather="edit"></i></a>
                                                    @endcan
                                                    @can('delete invoice')
                                                        <a class=" avtar avtar-xs btn-link-danger text-danger confirm_dialog"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Detete') }}" href="#"> <i
                                                                data-feather="trash-2"></i></a>
                                                    @endcan
                                                    {!! Form::close() !!}
                                                </div>

                                            </td>
                                        @endif
                                    </tr>
                                @endforeach

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
