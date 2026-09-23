@extends('layouts.app')

@section('page-title')
    {{ __('Income Report') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item" aria-current="page"> {{ __('Income Report') }}</li>
@endsection

@push('css-page')
    <style>
        .cust-pro {
            width: 230px;
        }

        .choices__list--dropdown .choices__item--selectable:after {
            content: '';
        }

        .choices__list--dropdown .choices__item--selectable {
            padding-right: 10px;
        }
    </style>
@endpush

@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                        <div>
                            <h5 class="mb-0">{{ __('Income Report') }}</h5>
                        </div>

                        <form action="{{ route('report.income') }}" method="get">
                            <div class="row gx-2 gy-1 align-items-end">
                                <div class="cust-pro">
                                    {{ Form::label('client', __('Client'), ['class' => 'form-label']) }}
                                    {!! Form::select('client', $clients, request('property_id'), [
                                        'class' => 'form-control select2',
                                        'id' => 'client_id',
                                    ]) !!}
                                </div>
                               <div class="cust-pro">
                                    {{ Form::label('status', __('Status'), ['class' => 'form-label']) }}
                                    {!! Form::select('status', $status, request('status'), ['class' => 'form-control select2 ']) !!}
                                </div>
                                <div class="cust-pro">
                                    {{ Form::label('start_date', __('Start Date'), ['class' => 'form-label']) }}
                                    <input class="form-control" name="start_date" type="date"
                                        value="{{ request('start_date') }}">
                                </div>

                                <div class="cust-pro">
                                    {{ Form::label('end_date', __('End Date'), ['class' => 'form-label']) }}
                                    <input class="form-control" name="end_date" type="date"
                                        value="{{ request('end_date') }}">
                                </div>


                                <div class="col-auto">
                                    <button type="submit" class="btn btn-light-secondary px-3">
                                        <i class="ti ti-search"></i>
                                    </button>
                                </div>

                                <div class="col-auto">
                                    <a href="{{ route('report.income') }}" class="btn btn-light-dark px-3">
                                        <i class="ti ti-refresh"></i>
                                    </a>
                                </div>

                            </div>
                        </form>
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
                                    <th>Tahsil edilen gelir</th>
                                    <th> {{ __('Status') }}</th>
                                       @if ( Gate::check('show invoice'))
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
                                        <td>{{ priceFormat($invoice->getInvoiceAllTotalAmount()) }}</td>
                                        <td>{{ priceFormat($invoice->payments_sum_amount ?? 0) }}</td>
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
                                        @if ( Gate::check('show invoice'))
                                            <td>
                                                <div class="cart-action">
                                                    @can('show invoice')
                                                        <a class="avtar avtar-xs btn-link-warning text-warning"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Detail') }}"
                                                            href="{{ route('invoice.show', \Illuminate\Support\Facades\Crypt::encrypt($invoice->id)) }}">
                                                            <i data-feather="eye"></i></a>
                                                    @endcan

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
