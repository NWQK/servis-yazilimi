@extends('layouts.app')
@section('page-title')
    {{ __('Dashboard') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item" aria-current="page">{{ __('Dashboard') }}</li>
@endsection
@push('script-page')
    <script>
        var labels = @json($result['vehicleServiceGraph']['label']);
        var services = @json($result['vehicleServiceGraph']['service']);
        var serviceTypes = @json($result['vehicleServiceGraph']['serviceTypes']);

        var options = {
            chart: {
                type: 'bar',
                height: 450,
                toolbar: {
                    show: false
                }
            },

            colors: ['#2ca58d'],

            dataLabels: {
                enabled: false
            },

            legend: {
                show: true,
                position: 'top'
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
                name: "Total Services",
                data: services
            }],

            tooltip: {
                custom: function({
                    dataPointIndex
                }) {

                    return `
            <div style="padding:10px;min-width:250px;">
                <div style="font-weight:600;margin-bottom:5px;">
                    ${labels[dataPointIndex]}
                </div>

                <div>
                    <b>Total Services:</b>
                    ${services[dataPointIndex]}
                </div>

                <div style="margin-top:5px;">
                    <b>Service Types:</b><br>
                    ${serviceTypes[dataPointIndex]}
                </div>
            </div>
        `;
                }
            },
            xaxis: {
                categories: labels,
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

        var chart = new ApexCharts(
            document.querySelector('#lastService'),
            options
        );

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
                                <i class="ti ti-package f-24"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="mb-1">{{ __('Total Vehicle') }}</p>
                            <div class="d-flex align-items-center justify-content-between">
                                <h4 class="mb-0">{{ $result['totalVehicle'] }}</h4>
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
                                <i class="ti ti-history f-24"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="mb-1">{{ __('Month Payment') }}</p>
                            <div class="d-flex align-items-center justify-content-between">
                                <h4 class="mb-0">{{ $result['MonthPayment'] }}</h4>
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
                                @if (!empty($result['latestCompletedService']))
                                    <div class="float-end">
                                        <small class="text-muted d-block mt-2">
                                            <strong>

                                                {{ vehiclePrefix() }}
                                                {{ $result['latestCompletedService']->vehicles->vehicle_id ?? '-' }}
                                                <small class="text-success">
                                                    {{ __('Completed On') }} :
                                                    {{ dateFormat($result['latestCompletedService']->due_date) }}
                                                </small>
                                            </strong>
                                        </small>
                                    </div>
                                @endif
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
                        <h5>{{ __('Incomming Service') }}</h5>
                    </div>
                    <div class="card-body pt-0 table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('ID') }}</th>
                                    <th>{{ __('Vehicle') }}</th>
                                    <th>{{ __('Duration') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                            <tbody>
                                @forelse($result['incomingServices'] as $service)
                                    <tr>
                                        <td>
                                            <a href="{{ route('service.show', \Crypt::encrypt($service->service_id)) }}">
                                                {{ servicePrefix() . $service->service_id ?? '-' }}
                                            </a>
                                        </td>
                                        <td>
                                            <a class="avtar avtar-xs customModal" data-size="lg" data-bs-toggle="tooltip"
                                                data-bs-original-title="{{ __('Show') }}" href="#"
                                                data-url="{{ route('vehicle.show', $service->vehicles->vehicle_id ?? '-') }}"
                                                data-title="{{ __('Details') }}">
                                                {{ vehiclePrefix() }}{{ $service->vehicles->vehicle_id ?? '-' }}</a>
                                        </td>

                                        <td>
                                            {{ dateFormat($service->service_date) }}
                                            -
                                            {{ timeFormat($service->service_time) }}
                                            <br>
                                            {{ dateFormat($service->due_date) }}
                                            -
                                            {{ timeFormat($service->due_time) }}
                                        </td>
                                        <td>
                                            @if ($service->status == 'scheduled')
                                                <span class="badge bg-light-primary">{{ __('Scheduled') }}</span>
                                            @elseif($service->status == 'in_progress')
                                                <span class="badge bg-light-secondary">{{ __('In Progress') }}</span>
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
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header">
                        <h5>{{ __('Curent Month Payment') }}</h5>
                    </div>
                    <div class="card-body pt-0 table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('Invoice') }}</th>
                                    <th>{{ __('Vehicle') }}</th>
                                    <th>{{ __('Payment Date') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($result['monthPayments'] as $payment)
                                    <tr>
                                        <td>
                                            <a
                                                href="{{ route('invoice.show', \Crypt::encrypt($payment->invoice->invoice_id ?? '-')) }}">
                                                {{ invoicePrefix() }}
                                                {{ $payment->invoice->invoice_id ?? '-' }}
                                            </a>
                                        </td>
                                        <td>
                                            <a class="avtar avtar-xs customModal" data-size="lg" data-bs-toggle="tooltip"
                                                data-bs-original-title="{{ __('Show') }}" href="#"
                                                data-url="{{ route('vehicle.show', $payment->invoice->services->vehicles->vehicle_id ?? '-') }}"
                                                data-title="{{ __('Details') }}">
                                                {{ vehiclePrefix() }}
                                                {{ $payment->invoice->services->vehicles->vehicle_id ?? '-' }}
                                            </a>

                                        </td>
                                        <td>
                                            {{ dateFormat($payment->payment_date) }}
                                        </td>
                                        <td>
                                            {{ priceFormat($payment->amount) }}
                                        </td>
                                        <td>
                                            @if ($payment->payment_status == 'success')
                                                <span class="badge bg-light-success">
                                                    {{ __('Success') }}
                                                </span>
                                            @else
                                                <span class="badge bg-light-danger">
                                                    {{ __('Failed') }}
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
                            <h5 class="mb-1">{{ __('Service & Vehicle Report') }}</h5>
                            <p class="text-muted mb-2">{{ __('Month Vised Services Per Vehicle') }}</p>
                        </div>
                    </div>
                    <div id="lastService"></div>
                </div>
            </div>
        </div>
    </div>
@endsection
