@extends('layouts.app')
@section('page-title')
    {{ __('Today Service') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
    </li>
    <li class="breadcrumb-item" aria-current="page">
        {{ __('Today Service') }}
    </li>
@endsection
@section('content')
    <div class="row">
        <div class="col-sm-12">
            <div class="card table-card">
                <div class="card-header">
                    <div class="row align-items-center g-2">
                        <div class="col">
                            <h5>{{ __('Today Service List') }}</h5>
                        </div>
                        @if (Gate::check('create service'))
                            <div class="col-auto">
                                <a class="btn btn-secondary"
                                    href="{{ route('service.create') }}"data-title="{{ __('Create Service') }}"> <i
                                        class="ti ti-circle-plus align-text-bottom"></i>
                                    {{ __('Create Service') }}
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
                                    <th>{{ __('Vehicle') }}</th>
                                    <th>{{ __('Client') }}</th>
                                    <th>{{ __('Duration') }}</th>
                                    <th>{{ __('Assign') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    @if (Gate::check('edit service') || Gate::check('delete service') || Gate::check('show service'))
                                        <th class="text-right">{{ __('Action') }}</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($services as $service)
                                    <tr>
                                        <td>{{ servicePrefix() . $service->service_id }} </td>
                                        <td><a class="customModal" href="#" data-size="lg" data-bs-toggle="tooltip"
                                                data-bs-original-title="{{ __('Details') }}" href="#"
                                                data-url="{{ route('vehicle.show', $service->vehicle) }}"
                                                data-title="{{ __('Vehicle Details') }}">
                                                {{ vehiclePrefix() }}{{ !empty($service->vehicles) ? $service->vehicles->vehicle_id : '' }}
                                            </a> </td>
                                        <td>{{ !empty($service->clients) ? $service->clients->name : '-' }} </td>
                                        <td>
                                            {{ dateFormat($service->service_date) }} -
                                            {{ timeFormat($service->service_time) }} <br>
                                            {{ dateFormat($service->due_date) }} - {{ timeFormat($service->due_time) }}
                                        </td>
                                        <td>{{ !empty($service->assigns) ? $service->assigns->name : '-' }} </td>
                                        <td>
                                            @if ($service->status == 'scheduled')
                                                <span
                                                    class="badge bg-light-primary">{{ \App\Models\Service::status()[$service->status] }}</span>
                                            @elseif($service->status == 'in_progress')
                                                <span
                                                    class="badge bg-light-secondary">{{ \App\Models\Service::status()[$service->status] }}</span>
                                            @elseif($service->status == 'completed')
                                                <span
                                                    class="badge bg-light-success">{{ \App\Models\Service::status()[$service->status] }}</span>
                                            @elseif($service->status == 'on_hold' || $service->status == 'pending_parts')
                                                <span
                                                    class="badge bg-light-warning">{{ \App\Models\Service::status()[$service->status] }}</span>
                                            @else
                                                <span
                                                    class="badge bg-light-danger">{{ \App\Models\Service::status()[$service->status] }}</span>
                                            @endif
                                        </td>
                                        @if (Gate::check('edit service') || Gate::check('delete service') || Gate::check('show service'))
                                            <td>
                                                <div class="cart-action">
                                                    {!! Form::open(['method' => 'DELETE', 'route' => ['service.destroy', $service->id]]) !!}

                                                    @can('show service')
                                                        <a class="avtar avtar-xs btn-link-warning text-warning"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Detail') }}"
                                                            href="{{ route('service.show', \Crypt::encrypt($service->id)) }}">
                                                            <i data-feather="eye"></i></a>
                                                    @endcan
                                                    @can('edit service')
                                                        <a class="avtar avtar-xs btn-link-secondary text-secondary"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Edit') }}"
                                                            href="{{ route('service.edit', \Crypt::encrypt($service->id)) }}"
                                                            data-title="{{ __('Edit Service') }}"> <i
                                                                data-feather="edit"></i></a>
                                                    @endcan
                                                    @can('delete service')
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
