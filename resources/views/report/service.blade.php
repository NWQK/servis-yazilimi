@extends('layouts.app')

@section('page-title')
    {{ __('Service Report') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item" aria-current="page"> {{ __('Service Report') }}</li>
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
                            <h5 class="mb-0">{{ __('Service Report') }}</h5>
                        </div>

                        <form action="{{ route('report.service') }}" method="get">
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
                                    <a href="{{ route('report.service') }}" class="btn btn-light-dark px-3">
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
                                            @elseif($service->status == 'cancelled')
                                                <span
                                                    class="badge bg-light-danger">{{ \App\Models\Service::status()[$service->status] }}</span>
                                            @else
                                                <span
                                                    class="badge bg-light-info">{{ \App\Models\Service::status()[$service->status] }}</span>
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
