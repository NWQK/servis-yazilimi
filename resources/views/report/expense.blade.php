@extends('layouts.app')

@section('page-title')
    {{ __('Expense Report') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item" aria-current="page"> {{ __('Expense Report') }}</li>
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
                            <h5 class="mb-0">{{ __('Expense Report') }}</h5>
                        </div>

                        <form action="{{ route('report.expense') }}" method="get">
                            <div class="row gx-2 gy-1 align-items-end">

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
                                    <a href="{{ route('report.expense') }}" class="btn btn-light-dark px-3">
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
                                    <th>{{ __('Title') }}</th>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Notes') }}</th>
                                    <th>{{ __('Attachment') }}</th>
                                    <th class="text-end">{{ __('Amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $totalAmount = 0; @endphp
                                @foreach ($expenses as $expense)
                                    @php $totalAmount += $expense->amount; @endphp
                                    <tr>
                                        <td>{{ $expense->title }} </td>
                                        <td>{{ dateFormat($expense->date) }} </td>
                                        <td>{{ $expense->notes }} </td>
                                        @if (Gate::check('edit expense') || Gate::check('delete expense'))
                                            @if (!empty($expense->receipt))
                                                <td>
                                                    <div class="cart-action">
                                                        <a class="avtar avtar-xs btn-link-secondary text-secondary"
                                                            href="{{ asset('/storage/upload/expense/' . $expense->receipt) }}"
                                                            target="_blank" data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Receipt') }}">
                                                            <i data-feather="file"></i>
                                                        </a>
                                                    </div>
                                                </td>
                                            @else
                                                <td>-</td>
                                            @endif
                                        @endif
                                        <td class="text-end">{{ priceFormat($expense->amount) }} </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-light-dark">
                                <tr>
                                    <th colspan="4" class="text-end">{{ __('Total') }}</th>
                                    <th class="text-end">{{ priceFormat($totalAmount) }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
@endsection
