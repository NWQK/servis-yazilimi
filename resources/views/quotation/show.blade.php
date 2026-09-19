@extends('layouts.app')
@section('page-title')
    {{ quotationPrefix() . $quotation->quotation_id . ' ' . __('Details') }}
@endsection
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('quotation.index') }}">
                {{ __('quotation') }}
            </a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">
                {{ quotationPrefix() . $quotation->quotation_id . ' ' . __('Details') }}
            </a>
        </li>
    </ul>
@endsection

@section('content')
    <div class="row" id="quotationPrint">
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
                                    <p class="mb-0">{{ $quotation ? quotationPrefix() . $quotation->quotation_id : '' }}
                                    </p>
                                </div>
                                <div class="col-sm-6 text-sm-end">
                                    <h6>
                                        {{ __('quotation No') }} :
                                        <span
                                            class="text-muted f-w-400">{{ quotationPrefix() . $quotation->quotation_id }}</span>
                                    </h6>
                                    <h6>
                                        {{ __('Created Date') }} :
                                        <span class="text-muted f-w-400">{{ dateFormat($quotation->created_at) }}</span>
                                    </h6>
                                    <h6>
                                        {{ __('quotation Date') }} :
                                        <span
                                            class="text-muted f-w-400">{{ dateFormat($quotation->quotation_date) }}</span>
                                    </h6>


                                    <h6>
                                        {{ __('Status') }} :
                                        <span class="text-muted f-w-400">
                                            @if (isset($quotation->status))
                                                @if ($quotation->status == 'draft')
                                                    <span
                                                        class="badge bg-light-dark">{{ \App\Models\Quotation::statues()[$quotation->status] }}</span>
                                                @elseif($quotation->status == 'sent')
                                                    <span
                                                        class="badge bg-light-warning">{{ \App\Models\Quotation::statues()[$quotation->status] }}</span>
                                                @elseif($quotation->status == 'accepted')
                                                    <span
                                                        class="badge bg-light-success">{{ \App\Models\Quotation::statues()[$quotation->status] }}</span>
                                                @elseif($quotation->status == 'rejected' || $quotation->status == 'cancelled')
                                                    <span
                                                        class="badge bg-light-danger">{{ \App\Models\Quotation::statues()[$quotation->status] }}</span>
                                                @else
                                                    <span
                                                        class="badge bg-light-info">{{ \App\Models\Quotation::statues()[$quotation->status] }}</span>
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
                                <h5>{{ $quotation->clients->name }}</span>
                                </h5>
                                <p class="mb-0">
                                    <span> {{ $quotation->clients->phone_number }}</span>
                                </p>
                                <p class="mb-0">
                                    <span> {{ $quotation->clients->clients->address }} ,<br>
                                        {{ $quotation->clients->clients->city }}
                                        ,{{ $quotation->clients->clients->state }}
                                        ,{{ $quotation->clients->clients->country }},
                                        {{ $quotation->clients->clients->zip }}</span>
                                </p>

                            </div>
                        </div>

                        <div class="col-12">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    @if (!empty($quotation->types) && count($quotation->types) > 0)
                                        <thead class="bg-light-dark">
                                            <tr>
                                                <th>{{ __('Service Type') }}</th>
                                                <th>{{ __('Description') }}</th>
                                                <th>{{ __('Tax') }}</th>
                                                <th></th>
                                                <th>{{ __('Rate') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($quotation->types as $servicetype)
                                                @php
                                                    $service = App\Models\ServiceType::find($servicetype->service_type);
                                                    $serviceTaxIds = !empty($servicetype->tax)
                                                        ? explode(',', $servicetype->tax)
                                                        : [];
                                                    $serviceTaxes = !empty($serviceTaxIds)
                                                        ? App\Models\Tax::whereIn('id', $serviceTaxIds)->get()
                                                        : collect();
                                                @endphp
                                                <tr>
                                                    <td>{{ !empty($service->type) ? $service->type : '-' }}</td>
                                                    <td>{{ $servicetype->note }}</td>
                                                    <td>
                                                        <div class="col">
                                                            @foreach ($serviceTaxes as $sTax)
                                                                @php $sTaxPrice = ($sTax->rate / 100) * $servicetype->rate; @endphp
                                                                {{ __('Tax') }} : {{ $sTax->title }} <br>
                                                                {{ __('Rate') }} : {{ $sTax->rate . '%' }} <br>
                                                                {{ __('Price') }} : {{ priceFormat($sTaxPrice) }}
                                                                <hr>
                                                            @endforeach
                                                        </div>
                                                    </td>
                                                    <td></td>
                                                    <td>{{ priceFormat($servicetype->rate) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    @endif


                                    <thead class="bg-light-dark">
                                        <tr>
                                            <th>{{ __('Items') }}</th>
                                            <th>{{ __('Price') }}</th>
                                            <th>{{ __('Tax') }}</th>
                                            <th>{{ __('Description') }}</th>
                                            <th>{{ __('Total') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $totalQuantity = 0;
                                            $totalRate = 0;
                                            $totalAmount = 0;
                                            $totalTaxPrice = 0;
                                            $totalDiscount = 0;
                                            $taxesData = [];
                                        @endphp

                                        @foreach ($quotation->items as $item)
                                            @php
                                                $tax = !empty($item->items) ? $item->items->taxs : 0;
                                                $taxes = \App\Models\Item::taxes($tax);

                                                $totalQuantity += $item->quantity;
                                                $totalRate += $item->amount;
                                                $totalDiscount += $item->discount;

                                                foreach ($taxes as $taxe) {
                                                    $taxDataPrice = \App\Models\Invoice::taxRate(
                                                        $taxe->rate,
                                                        $item->amount,
                                                        $item->quantity,
                                                    );
                                                    if (array_key_exists($taxe->title, $taxesData)) {
                                                        $taxesData[$taxe->title] =
                                                            $taxesData[$taxe->title] + $taxDataPrice;
                                                    } else {
                                                        $taxesData[$taxe->title] = $taxDataPrice;
                                                    }
                                                }

                                            @endphp
                                            <tr>
                                                <td>
                                                    {{ __('Code') }} :
                                                    {{ !empty($item->items) ? $item->items->item_code : '-' }}
                                                    <br>
                                                    {{ __('Item') }} :
                                                    {{ !empty($item->items) ? $item->items->title : '-' }}
                                                    <br>
                                                    {{ __('Quantity') }} : {{ $item->quantity }}
                                                </td>
                                                <td>{{ priceFormat($item->amount) }} </td>

                                                <td>
                                                    <div class="col">
                                                        @foreach ($taxes as $tax)
                                                            @php
                                                                $taxPrice = \App\Models\Invoice::taxRate(
                                                                    $tax->rate,
                                                                    $item->amount,
                                                                    $item->quantity,
                                                                );
                                                                $totalTaxPrice += $taxPrice;
                                                            @endphp
                                                            {{ __('Tax') }} : {{ $tax->title }} <br>
                                                            {{ __('Rate') }} : {{ $tax->rate . '%' }} <br>
                                                            {{ __('Price') }} : {{ priceFormat($taxPrice) }}
                                                            <hr>
                                                        @endforeach
                                                    </div>
                                                </td>


                                                <td>{{ $item->description }} </td>
                                                <td class="text-right">
                                                    {{ priceFormat($item->amount * $item->quantity) }}
                                                </td>
                                                @php
                                                    $totalQuantity += $item->quantity;
                                                    $totalRate += $item->amount;
                                                    $totalDiscount += $item->discount;
                                                    $totalAmount += $item->amount * $item->quantity;
                                                @endphp

                                            </tr>
                                        @endforeach
                                    </tbody>
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
                                                    @if ($quotation->items->count() > 0)

                                                        <tr>
                                                            <th>{{ __('Sub Total') }} <span
                                                                    class="text-muted text-sm">({{ __('Item') }})</span>
                                                                :
                                                            </th>
                                                            <td>{{ priceFormat(number_format($quotation->getQuotationSubTotalAmount(), 2)) }}
                                                            </td>
                                                        </tr>
                                                        @if (!empty($taxesData))
                                                            @foreach ($taxesData as $taxName => $taxPrice)
                                                                <tr>
                                                                    <th>{{ $taxName }} :</th>
                                                                    <td>{{ priceFormat(number_format($taxPrice, 2)) }}</td>
                                                                </tr>
                                                            @endforeach
                                                        @endif
                                                        <tr>
                                                            <th>{{ __('Item Total') }} :</th>
                                                            <td>{{ priceFormat(number_format($quotation->getQuotationTotalAmount(), 2)) }}
                                                            </td>
                                                        </tr>
                                                    @endif
                                                    @if ($quotation->types->count() > 0)
                                                        <tr>
                                                            <th>{{ __('Sub Total') }} <span
                                                                    class="text-muted text-sm">({{ __('Service') }})</span>
                                                                :
                                                            </th>
                                                            <td>{{ priceFormat(number_format($quotation->getQuotationServiceSubTotalAmount(), 2)) }}
                                                            </td>
                                                        </tr>
                                                        @php
                                                            $serviceTaxesData = [];
                                                            foreach ($quotation->types as $servicetype) {
                                                                if (empty($servicetype->tax)) {
                                                                    continue;
                                                                }
                                                                $taxIds = explode(',', $servicetype->tax);
                                                                $sTaxes = App\Models\Tax::whereIn('id', $taxIds)->get();
                                                                foreach ($sTaxes as $sTax) {
                                                                    $sTaxPrice =
                                                                        ($sTax->rate / 100) * $servicetype->rate;
                                                                    if (
                                                                        array_key_exists(
                                                                            $sTax->title,
                                                                            $serviceTaxesData,
                                                                        )
                                                                    ) {
                                                                        $serviceTaxesData[$sTax->title] += $sTaxPrice;
                                                                    } else {
                                                                        $serviceTaxesData[$sTax->title] = $sTaxPrice;
                                                                    }
                                                                }
                                                            }
                                                        @endphp
                                                        @if (!empty($serviceTaxesData))
                                                            @foreach ($serviceTaxesData as $taxName => $taxPrice)
                                                                <tr>
                                                                    <th>{{ $taxName }}
                                                                        :</th>
                                                                    <td>{{ priceFormat(number_format($taxPrice, 2)) }}</td>
                                                                </tr>
                                                            @endforeach
                                                        @endif
                                                        <tr>
                                                            <th>{{ __('Service Total') }} :</th>
                                                            <td>{{ priceFormat(number_format($quotation->getQuotationServiceAmount(), 2)) }}
                                                            </td>
                                                        </tr>
                                                    @endif
                                                    <tr>
                                                        <th>{{ __('Grand Total') }} :</th>
                                                        <td class="h5">
                                                            {{ priceFormat(number_format($quotation->getQuotationAllTotalAmount(), 2)) }}
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
            var quotationPrintContents = document.getElementById('quotationPrint').innerHTML;
            var originalContents = document.body.innerHTML;
            document.body.innerHTML = quotationPrintContents;
            $('.del_div').addClass('d-none');
            window.print();
            document.body.innerHTML = originalContents;
            $('.del_div').removeClass('d-none');
        });
    </script>
@endpush
