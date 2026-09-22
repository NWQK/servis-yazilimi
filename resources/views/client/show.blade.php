@extends('layouts.app')
@php
    $profile = asset(Storage::url('upload/profile/'));
@endphp
@section('page-title')
    {{ __('Employee') }}
@endsection
@section('breadcrumb')
    <li class="breadcrumb-item">
        <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
    </li>
    <li class="breadcrumb-item" aria-current="page">
        {{ __('Employee') }}
    </li>
@endsection
@push('css-page')
    <style>
        #employeewizard .wizard-nav .nav-link.active {
            background: var(--bs-secondary) !important;
            color: #fff !important;
            border-color: var(--bs-secondary) !important;
        }

        #employeewizard #theme-progress-bar {
            background: var(--bs-secondary) !important;
            transition: width .3s ease, background-color .3s ease;
        }

        #employeewizard .wizard-nav .nav-link.done {
            background: var(--bs-success) !important;
            color: #fff !important;
        }

        #employeewizard .wizard-nav .nav-link {
            transition: all .3s ease;
        }
    </style>
@endpush
@section('content')
    <div class="row">
        <div class="col-12">
            <div id="employeewizard" class="form-wizard row justify-content-center">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body p-3">
                            <ul class="nav nav-pills nav-justified wizard-nav">
                                <li class="nav-item">
                                    <a href="#detailPane" data-bs-toggle="tab" class="nav-link active">
                                        {{ __('Client Detail') }}
                                    </a>
                                </li>

                                <li class="nav-item">
                                    <a href="#vehiclePane" data-bs-toggle="tab" class="nav-link">
                                        {{ __('Vehicles') }}
                                    </a>
                                </li>

                                <li class="nav-item">
                                    <a href="#servicePane" data-bs-toggle="tab" class="nav-link">
                                        {{ __('Services') }}
                                    </a>
                                </li>

                                <li class="nav-item">
                                    <a href="#quotationPane" data-bs-toggle="tab" class="nav-link">
                                        {{ __('Quotations') }}
                                    </a>
                                </li>

                                <li class="nav-item">
                                    <a href="#invoicePane" data-bs-toggle="tab" class="nav-link">
                                        {{ __('Invoices') }}
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div class="tab-content">
                                <div id="bar" class="progress mb-3" style="height:7px;">
                                    <div class="bar progress-bar progress-bar-striped progress-bar-animated"
                                        id="theme-progress-bar" style="background-color: var(--bs-secondary) !important;">
                                    </div>
                                </div>
                                <div class="tab-pane show active" id="detailPane">

                                    <div class="row">

                                        <div class="col-md-6">
                                            <h6>{{ __('Client ID') }}</h6>
                                            <p>{{ clientPrefix() . $client->client_id }}</p>
                                        </div>

                                        <div class="col-md-6">
                                            <h6>{{ __('Name') }}</h6>
                                            <p>{{ $user->name }}</p>
                                        </div>

                                        <div class="col-md-6">
                                            <h6>{{ __('Email') }}</h6>
                                            <p>{{ $user->email }}</p>
                                        </div>

                                        <div class="col-md-6">
                                            <h6>{{ __('Phone') }}</h6>
                                            <p>{{ $user->phone_number }}</p>
                                        </div>

                                        <div class="col-md-6">
                                            <h6>{{ __('Gender') }}</h6>
                                            <p>{{ $client->gender }}</p>
                                        </div>

                                        <div class="col-md-6">
                                            <h6>{{ __('Address') }}</h6>
                                            <p>{{ $client->address }}</p>
                                        </div>

                                        <div class="col-md-6">
                                            <h6>{{ __('City') }}</h6>
                                            <p>{{ $client->city }}</p>
                                        </div>

                                        <div class="col-md-6">
                                            <h6>{{ __('State') }}</h6>
                                            <p>{{ $client->state }}</p>
                                        </div>

                                        <div class="col-md-6">
                                            <h6>{{ __('Country') }}</h6>
                                            <p>{{ $client->country }}</p>
                                        </div>

                                        <div class="col-md-6">
                                            <h6>{{ __('Zip Code') }}</h6>
                                            <p>{{ $client->zip_code }}</p>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane" id="vehiclePane">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Vehicle ID') }}</th>
                                                <th>{{ __('Model') }}</th>
                                                <th>{{ __('License Plate') }}</th>
                                                <th>{{ __('Color') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($vehicles as $vehicle)
                                                <tr>
                                                    <td>
                                                        <a class="avtar avtar-xs customModal" data-size="lg"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Show') }}" href="#"
                                                            data-url="{{ route('vehicle.show', $vehicle->id) }}"
                                                            data-title="{{ __('Details') }}">
                                                            {{ vehiclePrefix() . $vehicle->vehicle_id }} </a>
                                                    </td>
                                                    <td>{{ $vehicle->display_name }}</td>
                                                    <td>{{ $vehicle->license_plate }}</td>
                                                    <td>{{ $vehicle->color }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center">
                                                        {{ __('No Vehicle Found') }}
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="tab-pane" id="servicePane">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Service ID') }}</th>
                                                <th>{{ __('Vehicle') }}</th>
                                                <th>{{ __('Service Date') }}</th>
                                                <th>{{ __('Due Date') }}</th>
                                                <th>{{ __('Status') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($services as $service)
                                                <tr>
                                                    <td>
                                                        <a
                                                            href="{{ route('service.show', \Crypt::encrypt($service->service_id)) }}">
                                                            {{ servicePrefix() . $service->service_id }}
                                                        </a>

                                                    </td>
                                                    <td>{{ $service->vehicles->license_plate ?? '-' }}</td>
                                                    <td>{{ dateFormat($service->service_date) }}</td>
                                                    <td>{{ dateFormat($service->due_date) }}</td>
                                                    <td>{{ ucfirst(str_replace('_', ' ', $service->status)) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center">
                                                        {{ __(' No Service Found') }}
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="tab-pane" id="quotationPane">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Quotation ID') }}</th>
                                                <th>{{ __('Vehicle') }}</th>
                                                <th>{{ __('Date') }}</th>
                                                <th>{{ __('Status') }}</th>
                                                <th>{{ __('Total') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($quotations as $quotation)
                                                <tr>
                                                    <td>
                                                        <a
                                                            href="{{ route('quotation.show', \Crypt::encrypt($quotation->quotation_id)) }}">
                                                            {{ quotationPrefix() . $quotation->quotation_id }}
                                                        </a>
                                                    </td>
                                                    <td>{{ $quotation->vehicles->license_plate ?? '-' }}</td>
                                                    <td>{{ dateFormat($quotation->quotation_date) }}</td>
                                                    <td>{{ ucfirst($quotation->status) }}</td>
                                                    <td>{{ priceFormat($quotation->getQuotationAllTotalAmount()) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center">
                                                        {{ __(' No Quotation Found') }}
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="tab-pane" id="invoicePane">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>{{ __('Invoice ID') }}</th>
                                                <th>{{ __('Date') }}</th>
                                                <th>{{ __('Status') }}</th>
                                                <th>{{ __('Total') }}</th>
                                                <th>{{ __('Due') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($invoices as $invoice)
                                                <tr>
                                                    <td>
                                                        <a
                                                            href="{{ route('invoice.show', \Crypt::encrypt($invoice->invoice_id)) }}">
                                                            {{ invoicePrefix() . $invoice->invoice_id }}
                                                        </a>
                                                    </td>
                                                    <td>{{ dateFormat($invoice->invoice_date) }}</td>
                                                    <td>{{ \App\Models\Invoice::statues()[$invoice->status] ?? '-' }}</td>
                                                    <td>{{ priceFormat($invoice->getInvoiceAllTotalAmount()) }}</td>
                                                    <td>{{ priceFormat($invoice->getInvoiceTotalDueAmount()) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="5" class="text-center">
                                                        {{ __('No Invoice Found') }}
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                <div class="d-flex wizard justify-content-between flex-wrap gap-2 mt-3">
                                    <div class="first">
                                        <a href="javascript:void(0);" class="btn btn-secondary">{{ __('First') }}</a>
                                    </div>
                                    <div class="d-flex">
                                        <div class="previous me-2">
                                            <a href="javascript:void(0);"
                                                class="btn btn-secondary">{{ __('Back To Previous') }}</a>
                                        </div>
                                        <div class="next">
                                            <a href="javascript:void(0);"
                                                class="btn btn-secondary">{{ __('Next Step') }}</a>
                                        </div>
                                    </div>
                                    <div class="last">
                                        <a href="javascript:void(0);" class="btn btn-secondary">{{ __('Finish') }}</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('script-page')
    <script src="{{ asset('assets/js/plugins/wizard.min.js') }}"></script>
    <script>
        new Wizard('#employeewizard', {
            validate: false,
            progress: true
        });
    </script>
@endpush
