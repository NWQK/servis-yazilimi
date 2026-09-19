@extends('layouts.app')

@section('page-title')
    {{ __('Item Tax') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
    </li>
    <li class="breadcrumb-item" aria-current="page">

        {{ __('Item Tax') }}
    </li>
@endsection



@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5>{{ __('Item Tax List') }}</h5>
                        </div>
                        @if (Gate::check('create tax'))
                            <div class="col-auto">
                                <a class="btn btn-secondary customModal" href="#" data-size="md"
                                    data-url="{{ route('tax.create') }}" data-title="{{ __('Create Tax') }}"> <i
                                        class="ti ti-circle-plus align-text-bottom"></i>
                                    {{ __('Create Tax') }}
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
                                    <th>{{ __('Rate') }}</th>
                                    @if (Gate::check('edit tax') || Gate::check('delete tax'))
                                        <th class="text-right">{{ __('Action') }}</th>
                                    @endif
                                </tr>


                            </thead>
                            <tbody>
                                @foreach ($taxs as $tax)
                                    <tr>
                                        <td>{{ $tax->title }} </td>
                                        <td>{{ $tax->rate }} </td>
                                        @if(Gate::check('edit tax') || Gate::check('delete tax'))
                                            <td>
                                                <div class="cart-action">
                                                    {!! Form::open(['method' => 'DELETE', 'route' => ['tax.destroy', $tax->id]]) !!}

                                                    @can('edit tax')
                                                        <a class="avtar avtar-xs btn-link-secondary text-secondary customModal"
                                                            data-size="lg" data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Tax') }}" href="#"
                                                            data-url="{{ route('tax.edit', $tax->id) }}"
                                                            data-title="{{ __('Tax') }}"> <i data-feather="edit"></i></a>
                                                    @endcan
                                                    @can('delete tax')
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
