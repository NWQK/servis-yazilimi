@extends('layouts.app')

@section('page-title')
    {{ __('Vehicle Brand') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
    </li>
    <li class="breadcrumb-item" aria-current="page">

        {{ __('Vehicle Brand') }}
    </li>
@endsection



@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5>{{ __('Vehicle Brand List') }}</h5>
                        </div>
                        @if (Gate::check('create vehicle type'))
                            <div class="col-auto">
                                <a class="btn btn-secondary customModal" href="#" data-size="md"
                                    data-url="{{ route('vehicle-type.create') }}" data-title="{{ __('Create Brand') }}"> <i
                                        class="ti ti-circle-plus align-text-bottom"></i>
                                    {{ __('Create Brand') }}
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
                                    <th>{{ __('Brand') }}</th>
                                    @if (Gate::check('edit vehicle type') || Gate::check('delete vehicle type'))
                                        <th class="text-right">{{ __('Action') }}</th>
                                    @endif
                                </tr>


                            </thead>
                            <tbody>
                                @foreach ($types as $type)
                                    <tr>
                                        <td>{{ $type->type }} </td>
                                        @if (Gate::check('edit vehicle type') || Gate::check('delete vehicle type'))
                                            <td>
                                                <div class="cart-action">
                                                    {!! Form::open(['method' => 'DELETE', 'route' => ['vehicle-type.destroy', $type->id]]) !!}

                                                    @can('edit vehicle type')
                                                        <a class="avtar avtar-xs btn-link-secondary text-secondary customModal"
                                                            data-size="md" data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Edit') }}" href="#"
                                                            data-url="{{ route('vehicle-type.edit', $type) }}"
                                                            data-title="{{ __('Edit Brand') }}"> <i data-feather="edit"></i></a>
                                                    @endcan
                                                    @can('delete vehicle type')
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
