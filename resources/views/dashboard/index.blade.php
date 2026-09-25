@extends('layouts.app')
@section('page-title')
    {{ __('Dashboard') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item" aria-current="page">{{ __('Dashboard') }}</li>
@endsection
@push('css-page')
    <style>
        #calendar .fc-toolbar-chunk button {
            display: none
        }

        #calendar table {
            font-size: 11px;
            width: 100%;
        }

        /* Calendar compact - cell height ઘટાડો */
        #calendar .fc-daygrid-day {
            height: 55px !important;
            max-height: 55px !important;
        }

        #calendar .fc-daygrid-day-frame {
            min-height: 55px !important;
            max-height: 55px !important;
        }

        #calendar .fc-col-header-cell {
            padding: 3px 0 !important;
            font-size: 10px !important;
        }

        #calendar .fc-daygrid-day-number {
            font-size: 10px !important;
            padding: 2px 3px !important;
        }

        #calendar .fc-toolbar-title {
            font-size: 13px !important;
        }

        #calendar .fc-toolbar.fc-header-toolbar {
            margin-bottom: 5px !important;
        }

        #calendar .fc-event {
            font-size: 9px !important;
            padding: 0px 2px !important;
            line-height: 1.2 !important;
        }

        #calendar .fc-daygrid-body {
            width: 100% !important;
        }

        .card .fc-view-harness {
            height: auto !important;
        }
    </style>
@endpush
@push('script-page')
    <script>
        var eventData = {!! json_encode($eventData) !!};
    </script>
    <script src="{{ asset('assets/js/plugins/index.global.min.js') }}"></script>
    <script src="{{ asset('assets/js/pages/calendar.js') }}"></script>
    <script>
        var options = {
            chart: {
                type: 'area',
                height: 450,
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
                    name: "{{ __('Total Income') }}",
                    data: {!! json_encode($result['incomeExpenseByMonth']['income']) !!},
                    // data: [50,20,50,70,55,80,70,60,40,50,30,60],
                },
                {
                    name: "{{ __('Total Expense') }}",
                    data: {!! json_encode($result['incomeExpenseByMonth']['expense']) !!},
                    // data: [40,10,40,60,50,70,60,50,30,40,20,50],
                }
            ],
            xaxis: {
                categories: {!! json_encode($result['incomeExpenseByMonth']['label']) !!},
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
        var chart = new ApexCharts(document.querySelector('#incomeExpenseByMonth'), options);
        chart.render();
    </script>

    <script>
        var serviceStatusData = @json(array_values($result['serviceStatusChart']));

        var options = {
            chart: {
                type: 'pie',
                height: 350
            },
            series: serviceStatusData,
            labels: [
                @json(__('Scheduled')),
                @json(__('In Progress')),
                @json(__('Completed')),
                @json(__('Pending Parts')),
                @json(__('On Hold')),
                @json(__('Cancelled'))
            ],
            legend: {
                position: 'bottom'
            },
            dataLabels: {
                enabled: true
            },
            colors: [
                '#0d6efd',
                '#6c757d',
                '#198754',
                '#ffc107',
                '#fd7e14',
                '#dc3545'
            ]
        };

        var chart = new ApexCharts(
            document.querySelector("#serviceStatusChart"),
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
                            <div class="avtar bg-light-secondary">
                                <i class="ti ti-users f-24"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="mb-1">{{ __('Total Client') }}</p>
                            <div class="d-flex align-items-center justify-content-between">
                                <h4 class="mb-0">{{ $result['totalClient'] }}</h4>

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
                            <div class="avtar bg-light-primary">
                                <i class="ti ti-history f-24"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="mb-1">{{ __('Curent Month Income') }}</p>
                            <div class="d-flex align-items-center justify-content-between">
                                <h4 class="mb-0">{{ $result['curentMonthIncome'] }}</h4>
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
                            <div class="avtar bg-light-danger">
                                <i class="ti ti-credit-card f-24"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <p class="mb-1">{{ __('Curent Month Expense') }}</p>
                            <div class="d-flex align-items-center justify-content-between">
                                <h4 class="mb-0">{{ $result['curentMonthExpense'] }}
                                </h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Today Services') }}</h5>
                </div>
                <div class="card-body pt-0 table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>{{ __('ID') }}</th>
                                <th>{{ __('Vehicle') }}</th>
                                <th>{{ __('Client') }}</th>
                                <th>{{ __('Service Time') }}</th>
                                <th>{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($result['incomingServices'] as $service)
                                <tr>
                                    <td>
                                        <a href="{{ route('service.show', \Crypt::encrypt($service->service_id)) }}">
                                            {{ servicePrefix() . $service->service_id }}
                                        </a>
                                    </td>

                                    <td>
                                        {{ vehiclePrefix() }}{{ $service->vehicles->vehicle_id ?? '-' }}
                                    </td>

                                    <td>
                                        {{ $service->clients->name ?? '-' }}
                                    </td>

                                    <td>
                                        {{ timeFormat($service->service_time) }}
                                    </td>

                                    <td>
                                        @if ($service->status == 'scheduled')
                                            <span class="badge bg-light-primary">
                                                {{ __('Scheduled') }}
                                            </span>
                                        @elseif($service->status == 'in_progress')
                                            <span class="badge bg-light-secondary">
                                                {{ __('In Progress') }}
                                            </span>
                                        @elseif($service->status == 'completed')
                                            <span class="badge bg-light-success">
                                                {{ __('Completed') }}
                                            </span>
                                        @else
                                            <span class="badge bg-light-warning">
                                                {{ ucfirst(str_replace('_', ' ', $service->status)) }}
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center">
                                        {{ __('No Services Today') }}
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
                    <h5>{{ __('Today Payments') }}</h5>
                </div>

                <div class="card-body pt-0 table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>{{ __('Invoice') }}</th>
                                <th>{{ __('Vehicle') }}</th>
                                <th>{{ __('Payment Time') }}</th>
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
                                        {{ __('No Payments Today') }}
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>


        <!-- Pie Chart -->
        <div class="col-lg-6 d-flex">
            <div class="card w-100 h-100">
                <div class="card-header">
                    <h5>{{ __('Service Status') }}</h5>
                </div>
                <div class="card-body d-flex justify-content-center align-items-center">
                    <div id="serviceStatusChart"></div>
                </div>
            </div>
        </div>

        <!-- Calendar -->
        <div class="col-lg-6 d-flex">
            <div class="card w-100 h-100">
                <div class="card-header">
                    <h5>{{ __('Current Month Calendar') }}</h5>
                </div>
                <div class="card-body">
                    <div id="calendar" class="calendar"></div>
                </div>
            </div>
        </div>

        <!-- Analysis Report -->
        <div class="col-lg-12 mt-4">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-start justify-content-between">
                        <div>
                            <h5 class="mb-1">{{ __('Analysis Report') }}</h5>
                            <p class="text-muted mb-2">{{ __('Income and Expense Overview') }}</p>
                        </div>
                    </div>
                    <div id="incomeExpenseByMonth"></div>
                </div>
            </div>
        </div>

        <div class="modal fade" id="calendar-modal" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header justify-content-between align-items-center">
                        <h3 class="calendar-modal-title f-w-600 text-truncate">{{ __('Modal title') }}</h3>
                        <a href="#" class="avtar avtar-s btn-link-danger btn-pc-default" data-bs-dismiss="modal">
                            <i class="ti ti-x f-20"></i>
                        </a>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="avtar avtar-xs bg-light-secondary">
                                    <i class="ti ti-tools f-20"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-1"><b>{{ __('Service Name') }}</b></h5>
                                <p class="pc-event-title text-muted"></p>
                            </div>
                        </div>
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="avtar avtar-xs bg-light-warning">
                                    <i class="ti ti-user f-20"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-1"><b>{{ __('Assign By') }}</b></h5>
                                <p class="pc-event-assign text-muted"></p>
                            </div>
                        </div>
                        <div class="d-flex">
                            <div class="flex-shrink-0">
                                <div class="avtar avtar-xs bg-light-info">
                                    <i class="ti ti-calendar-event f-20"></i>
                                </div>
                            </div>
                            <div class="flex-grow-1 ms-3">
                                <h5 class="mb-1"><b>{{ __('Scheduled Date') }}</b></h5>
                                <p class="pc-event-date text-muted"></p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between">
                        <ul class="list-inline me-auto mb-0">
                            <li class="list-inline-item align-bottom">
                                <a href="#" id="pc_event_show"
                                    class="avtar avtar-s btn-link-warning btn-pc-default text-warning bg-light-warning"
                                    data-bs-toggle="tooltip" title="Edit">
                                    <i class="ti ti-eye f-18"></i>
                                </a>
                            </li>
                        </ul>
                        <div class="flex-grow-1 text-end">
                            <button type="button" class="btn btn-secondary"
                                data-bs-dismiss="modal">{{ __('Close') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
