@extends('layouts.app')

@section('page-title')
    {{ __('Vehicle') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
    </li>
    <li class="breadcrumb-item" aria-current="page">

        {{ __('Vehicle') }}
    </li>
@endsection



@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5>{{ __('Vehicle List') }}</h5>
                        </div>
                        @if (Gate::check('create vehicle'))
                            <div class="col-auto">
                                <a class="btn btn-secondary customModal" href="#" data-size="lg"
                                    data-url="{{ route('vehicle.create') }}" data-title="{{ __('Create Vehicle') }}"> <i
                                        class="ti ti-circle-plus align-text-bottom"></i>
                                    {{ __('Create Vehicle') }}
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
                                    <th>{{ __('ID') }}</th>
                                    <th>{{ __('Client') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Brand') }}</th>
                                    <th>{{ __('Model') }}</th>
                                    <th>{{ __('License Plate') }}</th>
                                    <th>{{ __('Color') }}</th>
                                    <th>{{ __('Engine Type') }}</th>
                                    @if (Gate::check('edit vehicle') || Gate::check('delete vehicle') || Gate::check('show vehicle'))
                                        <th class="text-right">{{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($vehicles as $vehicle)
                                    <tr>
                                        <td>{{ vehiclePrefix() . $vehicle->vehicle_id }} </td>
                                        <td>{{ !empty($vehicle->clients) ? $vehicle->clients->name : '-' }} </td>
                                        <td>{{ !empty($vehicle->types) ? $vehicle->types->type : '-' }} </td>
                                        <td>{{ !empty($vehicle->brands) ? $vehicle->brands->name : '-' }} </td>
                                        <td>{{ $vehicle->model }} </td>
                                        <td>{{ $vehicle->license_plate }} </td>
                                        <td>{{ $vehicle->color }} </td>
                                        <td>{{ $vehicle->engine_type }} </td>
                                        @if(Gate::check('edit vehicle') || Gate::check('delete vehicle') || Gate::check('show vehicle'))
                                        <td>
                                                <div class="cart-action">
                                                    {!! Form::open(['method' => 'DELETE', 'route' => ['vehicle.destroy', $vehicle->id]]) !!}
                                                    @can('show vehicle')
                                                        <a class="avtar avtar-xs btn-link-warning text-warning customModal"
                                                            data-size="lg" data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Show') }}" href="#"
                                                            data-url="{{ route('vehicle.show', $vehicle->id) }}"
                                                            data-title="{{ __('Details') }}"> <i data-feather="eye"></i></a>
                                                    @endcan
                                                    @can('edit vehicle')
                                                        <a class="avtar avtar-xs btn-link-secondary text-secondary customModal"
                                                            data-size="lg" data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Edit') }}" href="#"
                                                            data-url="{{ route('vehicle.edit', $vehicle->id) }}"
                                                            data-title="{{ __('Edit') }}"> <i data-feather="edit"></i></a>
                                                    @endcan
                                                    @can('delete vehicle')
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
