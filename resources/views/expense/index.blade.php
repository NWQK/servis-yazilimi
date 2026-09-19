@extends('layouts.app')

@section('page-title')
    {{ __('Expense') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
    </li>
    <li class="breadcrumb-item" aria-current="page">
        {{ __('Expense') }}
    </li>
@endsection

@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5>{{ __('Expense List') }}</h5>
                        </div>
                        @if (Gate::check('create expense'))
                            <div class="col-auto">
                                <a class="btn btn-secondary customModal" href="#" data-size="lg"
                                    data-url="{{ route('expense.create') }}" data-title="{{ __('Create Expense') }}"> <i
                                        class="ti ti-circle-plus align-text-bottom"></i>
                                    {{ __('Create Expense') }}
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
                                    <th>{{ __('Title') }}</th>
                                    <th>{{ __('Date') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Notes') }}</th>
                                    @if (Gate::check('edit expense') || Gate::check('delete expense'))
                                        <th class="text-right">{{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($expenses as $expense)
                                    <tr>
                                        <td>{{ $expense->title }} </td>
                                        <td> {{ dateFormat($expense->date) }} </td>
                                        <td>{{ priceFormat($expense->amount) }} </td>
                                        <td>{{ $expense->notes }} </td>
                                        @if (Gate::check('edit expense') || Gate::check('delete expense'))
                                            <td>
                                                <div class="cart-action">
                                                    {!! Form::open(['method' => 'DELETE', 'route' => ['expense.destroy', $expense->id]]) !!}
                                                    @if (!empty($expense->receipt))
                                                        <a class="avtar avtar-xs btn-link-secondary text-secondary"
                                                            href="{{ asset('/storage/upload/expense/' . $expense->receipt) }} "
                                                            target="_blank" data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Receipt') }}"> <i
                                                                data-feather="file"></i> </a>
                                                    @endif
                                                    @can('edit expense')
                                                        <a class="avtar avtar-xs btn-link-secondary text-secondary customModal"
                                                            data-size="lg" data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Edit') }}" href="#"
                                                            data-url="{{ route('expense.edit', $expense->id) }}"
                                                            data-title="{{ __('Edit') }}"> <i data-feather="edit"></i></a>
                                                    @endcan
                                                    @can('delete expense')
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
