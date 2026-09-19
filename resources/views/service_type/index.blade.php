@extends('layouts.app')

@section('page-title')
    {{ __('Service Type') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
    </li>
    <li class="breadcrumb-item" aria-current="page">

        {{ __('Service Type') }}
    </li>
@endsection



@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5>{{ __('Service Type List') }}</h5>
                        </div>
                        @if (Gate::check('create service type'))
                            <div class="col-auto">
                                <a class="btn btn-secondary customModal" href="#" data-size="md"
                                    data-url="{{ route('service-type.create') }}"
                                    data-title="{{ __('Create Service Type') }}"> <i
                                        class="ti ti-circle-plus align-text-bottom"></i>
                                    {{ __('Create Service Type') }}
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
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Rate') }}</th>
                                    <th>{{ __('Tax') }}</th>
                                    <th>{{ __('Note') }}</th>
                                    @if (Gate::check('edit service type') || Gate::check('delete service type'))
                                        <th class="text-right">{{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($types as $type)
                                    <tr>
                                        <td>{{ $type->type }} </td>
                                        <td>{{ $type->rate }} </td>
                                        <td>
                                            @php
                                                $taxIds = !empty($type->tax) ? explode(',', $type->tax) : [];
                                                $taxData = \App\Models\Tax::whereIn('id', $taxIds)->get();
                                            @endphp

                                            @foreach ($taxData as $tax)
                                                {{ $tax->title }} ({{ $tax->rate }}%) <br>
                                            @endforeach
                                        </td>
                                        <td>{{ $type->note }} </td>
                                        @if (Gate::check('edit service type') || Gate::check('delete service type'))
                                            <td>
                                                <div class="cart-action">
                                                    {!! Form::open(['method' => 'DELETE', 'route' => ['service-type.destroy', $type->id]]) !!}

                                                    @can('edit service type')
                                                        <a class="avtar avtar-xs btn-link-secondary text-secondary customModal"
                                                            data-size="md" data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Edit') }}" href="#"
                                                            data-url="{{ route('service-type.edit', $type) }}"
                                                            data-title="{{ __('Edit') }}"> <i data-feather="edit"></i></a>
                                                    @endcan
                                                    @can('delete service type')
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
