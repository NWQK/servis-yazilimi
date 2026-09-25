@extends('layouts.app')
@section('page-title')
    {{ servicePrefix() . ($service?->service_id ?? '') . ' ' . __('Details') }}
@endsection
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('service.index') }}">
                {{ __('service') }}
            </a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">
                {{ servicePrefix() . ($service?->service_id ?? '') . ' ' . __('Details') }}
            </a>
        </li>
    </ul>
@endsection
@section('content')

    <div class="row" id="servicePrint">
        <div class="col-sm-12">
            <div class="d-print-none card mb-3">
                <div class="card-body p-3">
                    <ul class="list-inline ms-auto mb-0 d-flex justify-content-end flex-wrap">
                        <li class="list-inline-item align-bottom me-2">
                            <a href="javascript:void(0);" class="avtar avtar-s btn-link-secondary print"
                                data-bs-toggle="tooltip" data-bs-original-title="{{ __('Download') }}">
                                <i class="ph-duotone ph-printer f-22"></i>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="row align-items-center g-3">
                                <div class="col-sm-6">
                                    <div class="d-flex align-items-center mb-2 navbar-brand img-fluid invoice-logo">
                                        <img src="{{ asset(Storage::url('upload/logo/')) . '/' . (isset($admin_logo) && !empty($admin_logo) ? $admin_logo : 'logo.png') }}"
                                            class="img-fluid brand-logo" alt="images" />
                                    </div>
                                    <p class="mb-0">
                                        {{ servicePrefix() . ($service?->service_id ?? '') . ' ' . __('Details') }}</p>
                                </div>
                                <div class="col-sm-6 text-sm-end">
                                    <h6>
                                        {{ __('service No') }} :
                                        <span
                                            class="text-muted f-w-400">{{ $service ? servicePrefix() . $service->service_id : '-' }}</span>
                                    </h6>
                                    <h6>
                                        {{ __('Created Date') }} :
                                        <span
                                            class="text-muted f-w-400">{{ dateFormat($service->created_at ?? '-') }}</span>
                                    </h6>
                                    <h6>
                                        {{ __('service Date') }} :
                                        <span
                                            class="text-muted f-w-400">{{ dateFormat($service->service_date ?? '-') }}</span>
                                    </h6>
                                    <h6>
                                        {{ __('Status') }} :
                                        <span class="text-muted f-w-400">
                                            @if (isset($service->status))
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
                                            @endif
                                        </span>
                                    </h6>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="border rounded p-3">
                                <h6 class="mb-0">{{ __('From') }}:</h6>
                                <h5>{{ !empty($settings['company_name']) ? $settings['company_name'] : ' - ' }}</h5>
                                <p class="mb-0">
                                    {{ !empty($settings['company_phone']) ? $settings['company_phone'] : '-' }}</p>
                                <p class="mb-0">
                                    {{ !empty($settings['company_email']) ? $settings['company_email'] : '-' }}</p>
                                <p class="mb-0">
                                    {{ !empty($settings['company_address']) ? $settings['company_address'] : '-' }}</p>

                            </div>
                        </div>
                        <div class="col-sm-6">
                            <div class="border rounded p-3">
                                <h6 class="mb-0">{{ __('To') }}:</h6>
                                <h5>{{ $service->clients->name ?? '-' }}</span>
                                </h5>
                                <p class="mb-0">
                                    <span> {{ $service->clients->phone_number ?? '-' }}</span>
                                </p>
                                <p class="mb-0">
                                    <span> {{ $service->clients->clients->address ?? '-' }} ,<br>
                                        {{ $service->clients->clients->city ?? '-' }}
                                        ,{{ $service->clients->clients->state ?? '-' }}
                                        ,{{ $service->clients->clients->country ?? '-' }},
                                        {{ $service->clients->clients->zip ?? '-' }}</span>
                                </p ?? '-'>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    @if (!empty($service->types) && count($service->types) > 0)
                                        <thead class="bg-light-dark">
                                            <tr>
                                                <th>{{ __('Service Type') }}</th>
                                                <th>{{ __('Description') }}</th>
                                                <th>{{ __('Tax') }}</th>
                                                <th></th>
                                                <th></th>
                                                <th>{{ __('Rate') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($service->types as $servicetype)
                                                @php
                                                    $serviceType = App\Models\ServiceType::find($servicetype->type_id);
                                                @endphp
                                                <tr>
                                                    <td>
                                                        {{ !empty($serviceType->type) ? $serviceType->type : '-' }}
                                                    </td>
                                                    <td>{{ $servicetype->note }}</td>
                                                    <td>
                                                        @php
                                                            $taxIds = !empty($servicetype->tax)
                                                                ? explode(',', $servicetype->tax)
                                                                : [];
                                                            $taxData = App\Models\Tax::whereIn('id', $taxIds)->get();
                                                        @endphp

                                                        @foreach ($taxData as $tax)
                                                            {{ $tax->title }} ({{ $tax->rate }}%) <br>
                                                        @endforeach
                                                    </td>
                                                    <td></td>
                                                    <td></td>
                                                    <td>{{ priceFormat($servicetype->rate) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    @endif
                                </table>
                            </div>
                            <div class="text-start">
                                <hr class="mb-2 mt-1 border-secondary border-opacity-50" />
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <div class="rounded p-3 bg-light-secondary">
                                <div class="row justify-content-end">
                                    <div class="col-auto">
                                        <div class="table-responsive">
                                            <table class="table table-borderless text-end mb-0">
                                                <tbody>
                                                    @php
                                                        $subtotal = optional($service)->types?->sum('rate') ?? 0;
                                                        $taxBreakdown = $service
                                                            ? $service->getServiceTaxBreakdown()
                                                            : [];
                                                        $totalTaxAmount = $service
                                                            ? $service->getServiceTotalTaxAmount()
                                                            : 0;
                                                        $grandTotal = $subtotal + $totalTaxAmount + (float) $service->external_labor_amount;
                                                    @endphp
                                                    <tr>
                                                        <th>{{ __('Subtotal') }} :</th>
                                                        <td colspan="2">{{ priceFormat($subtotal) }}</td>
                                                    </tr>

                                                    @if ($service->external_labor_amount > 0)
                                                        <tr><th>Harici işçilik :</th><td colspan="2">{{ priceFormat($service->external_labor_amount) }}</td></tr>
                                                    @endif
                                                    @if (!empty($taxBreakdown))
                                                        @foreach ($taxBreakdown as $tax)
                                                            <tr>
                                                                <th>{{ $tax['title'] }} ({{ $tax['rate'] }}%) :</th>
                                                                <td colspan="2">{{ priceFormat($tax['price']) }}</td>
                                                            </tr>
                                                        @endforeach
                                                    @else
                                                        <tr>
                                                            <th>{{ __('Tax') }} :</th>
                                                            <td colspan="2">{{ priceFormat(0) }}</td>
                                                        </tr>
                                                    @endif
                                                    <tr class="border-top">
                                                        <th class="h5">{{ __('Total') }} :</th>
                                                        <td colspan="2" class="h5">{{ priceFormat($grandTotal) }}
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
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
    <script>
        $(document).on('click', '.print', function() {
            var servicePrintContents = document.getElementById('servicePrint').innerHTML;
            var originalContents = document.body.innerHTML;
            document.body.innerHTML = servicePrintContents;
            $('.del_div').addClass('d-none');
            window.print();
            document.body.innerHTML = originalContents;
            $('.del_div').removeClass('d-none');
        });
    </script>
@endpush
