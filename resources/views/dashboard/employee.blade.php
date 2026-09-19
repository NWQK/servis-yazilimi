@extends('layouts.app')
@section('page-title')
    {{ __('Dashboard') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item" aria-current="page">{{ __('Dashboard') }}</li>
@endsection
@push('script-page')
    <script>
        var options = {
            chart: {
                type: 'area',
                height: 250,
                toolbar: {
                    show: false
                }
            },
            colors: ['#2ca58d', '#0a2342'],
            dataLabels: {
                enabled: false
            },
            legend: {
                show: true,
                position: 'top'
            },
            markers: {
                size: 1,
                colors: ['#fff', '#fff', '#fff'],
                strokeColors: ['#2ca58d', '#0a2342'],
                strokeWidth: 1,
                shape: 'circle',
                hover: {
                    size: 4
                }
            },
            stroke: {
                width: 2,
                curve: 'smooth'
            },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    type: 'vertical',
                    inverseColors: false,
                    opacityFrom: 0.5,
                    opacityTo: 0
                }
            },
            grid: {
                show: false
            },
            series: [{
                name: "{{ __('Total Service') }}",
                data: {!! json_encode($result['lastAssignService']['service']) !!},
                // data: [50,20,50,70,55,80,70,60,40,50,30,60],
            }, ],
            xaxis: {
                categories: {!! json_encode($result['lastAssignService']['label']) !!},
                tooltip: {
                    enabled: false
                },
                labels: {
                    hideOverlappingLabels: true
                },
                axisBorder: {
                    show: false
                },
                axisTicks: {
                    show: false
                }
            }
        };
        var chart = new ApexCharts(document.querySelector('#lastAssignService'), options);
        chart.render();
    </script>
@endpush
@section('content')
    <div class="row">
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avtar bg-light-primary">
                                <i class="ti ti-history f-24"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="mb-1">{{ __('Total Service') }}</p>
                            <div class="d-flex align-items-center justify-content-between">
                                <h4 class="mb-0">{{ $result['totalService'] }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avtar bg-light-warning">
                                <i class="ti ti-package f-24"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="mb-1">{{ __('Today Service') }}</p>
                            <div class="d-flex align-items-center justify-content-between">
                                <h4 class="mb-0">{{ $result['todayService'] }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avtar bg-light-info">
                                <i class="ti ti-package f-24"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="mb-1">{{ __('In Progress Service') }}</p>
                            <div class="d-flex align-items-center justify-content-between">
                                <h4 class="mb-0">{{ $result['inProgressService'] }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="avtar bg-light-success">
                                <i class="ti ti-package f-24"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="mb-1">{{ __('Completed Service') }}</p>
                            <div class="d-flex align-items-center justify-content-between">
                                <h4 class="mb-0">{{ $result['completeService'] }}</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Curent Month Service') }}</h5>
                    </div>
                    <div class="card-body pt-0 table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('ID') }}</th>
                                    <th>{{ __('Vehicle') }}</th>
                                    <th>{{ __('Client') }}</th>
                                    <th>{{ __('Duration') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($result['monthServices'] as $service)
                                    <tr>
                                        <td>
                                            <a
                                                href="{{ route('service.show', \Crypt::encrypt($service->service_id ?? '-')) }}">
                                                {{ servicePrefix() . $service->service_id ?? '-' }}
                                            </a>
                                        </td>
                                        <td>
                                            <a class="avtar avtar-xs customModal" data-size="lg" data-bs-toggle="tooltip"
                                                data-bs-original-title="{{ __('Show') }}" href="#"
                                                data-url="{{ route('vehicle.show', $service->vehicles->vehicle_id ?? '-') }}"
                                                data-title="{{ __('Details') }}">
                                                {{ vehiclePrefix() }}{{ $service->vehicles->vehicle_id ?? '-' }}
                                            </a>
                                        </td>
                                        <td>
                                            {{ $service->clients->name ?? '-' }}
                                        </td>
                                        <td>
                                            {{ dateFormat($service->service_date) }}
                                            {{ timeFormat($service->service_time) }}
                                            <br>
                                            {{ dateFormat($service->due_date) }}
                                            {{ timeFormat($service->due_time) }}
                                        </td>
                                        <td>
                                            @if ($service->status == 'scheduled')
                                                <span class="badge bg-light-primary">
                                                    {{ \App\Models\Service::status()[$service->status] }}
                                                </span>
                                            @elseif($service->status == 'in_progress')
                                                <span class="badge bg-light-secondary">
                                                    {{ \App\Models\Service::status()[$service->status] }}
                                                </span>
                                            @elseif($service->status == 'completed')
                                                <span class="badge bg-light-success">
                                                    {{ \App\Models\Service::status()[$service->status] }}
                                                </span>
                                            @elseif($service->status == 'on_hold' || $service->status == 'pending_parts')
                                                <span class="badge bg-light-warning">
                                                    {{ \App\Models\Service::status()[$service->status] }}
                                                </span>
                                            @else
                                                <span class="badge bg-light-danger">
                                                    {{ \App\Models\Service::status()[$service->status] }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">
                                            {{ __('No Data Found') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Curent Today Service') }}</h5>
                    </div>
                    <div class="card-body pt-0 table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('ID') }}</th>
                                    <th>{{ __('Vehicle') }}</th>
                                    <th>{{ __('Client') }}</th>
                                    <th>{{ __('Duration') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($result['todayServicesList'] as $service)
                                    <tr>
                                        <td>
                                            <a
                                                href="{{ route('service.show', \Crypt::encrypt($service->service_id ?? '-')) }}">
                                                {{ servicePrefix() . $service->service_id ?? '-' }}
                                            </a>
                                        </td>
                                        <td>
                                            <a class="avtar avtar-xs customModal" data-size="lg" data-bs-toggle="tooltip"
                                                data-bs-original-title="{{ __('Show') }}" href="#"
                                                data-url="{{ route('vehicle.show', $service->vehicles->vehicle_id ?? '-') }}"
                                                data-title="{{ __('Details') }}">
                                                {{ vehiclePrefix() }}{{ $service->vehicles->vehicle_id ?? '-' }}
                                            </a>
                                        </td>
                                        <td>
                                            {{ $service->clients->name ?? '-' }}
                                        </td>
                                        <td>
                                            {{ dateFormat($service->service_date) }}
                                            {{ timeFormat($service->service_time) }}
                                            <br>
                                            {{ dateFormat($service->due_date) }}
                                            {{ timeFormat($service->due_time) }}
                                        </td>
                                        <td>
                                            @if ($service->status == 'scheduled')
                                                <span class="badge bg-light-primary">
                                                    {{ \App\Models\Service::status()[$service->status] }}
                                                </span>
                                            @elseif($service->status == 'in_progress')
                                                <span class="badge bg-light-secondary">
                                                    {{ \App\Models\Service::status()[$service->status] }}
                                                </span>
                                            @elseif($service->status == 'completed')
                                                <span class="badge bg-light-success">
                                                    {{ \App\Models\Service::status()[$service->status] }}
                                                </span>
                                            @elseif($service->status == 'on_hold' || $service->status == 'pending_parts')
                                                <span class="badge bg-light-warning">
                                                    {{ \App\Models\Service::status()[$service->status] }}
                                                </span>
                                            @else
                                                <span class="badge bg-light-danger">
                                                    {{ \App\Models\Service::status()[$service->status] }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center">
                                            {{ __('No Data Found') }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-12 col-md-12">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <h5 class="mb-1">{{ __('Assigned Service Report') }}</h5>
                            <p class="text-muted mb-2">{{ __('Services Assigned to You in the Last 15 Days') }}</p>
                        </div>
                    </div>
                    <div id="lastAssignService"></div>
                </div>
            </div>
        </div>
    </div>
@endsection
