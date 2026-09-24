@extends('layouts.app')
@section('page-title')
    {{ invoicePrefix() . $invoice?->invoice_id ?? '-' . ' ' . __('Details') }}
@endsection
@php
    $admin_logo = getSettingsValByName('company_logo');
@endphp
@section('breadcrumb')
    <ul class="breadcrumb mb-0">
        <li class="breadcrumb-item">
            <a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a>
        </li>
        <li class="breadcrumb-item">
            <a href="{{ route('invoice.index') }}">
                {{ __('Invoice') }}
            </a>
        </li>
        <li class="breadcrumb-item active">
            <a href="#">
                {{ invoicePrefix() . $invoice?->invoice_id ?? '-' . ' ' . __('Details') }}
            </a>
        </li>
    </ul>
@endsection
@section('content')
    <div class="row" id="invoicePrint">
        <div class="col-sm-12">
            <div class="d-print-none card mb-3">
                <div class="card-body p-3">
                    <ul class="list-inline ms-auto mb-0 d-flex justify-content-end flex-wrap">
                        <li class="list-inline-item align-bottom me-2">
                            @can('create invoice payment')
                                @if ($invoice->status != 2)
                                    <a href="#" class="avtar avtar-s btn-link-secondary customModal"
                                        data-bs-toggle="tooltip" data-bs-original-title="{{ __('Add Payment') }}" data-size="md"
                                        data-url="{{ route('invoice.payment', $invoice->id) }}"
                                        data-title="{{ __('Add Payment') }}">
                                        <i class="ph-duotone ph-credit-card f-22"></i>
                                    </a>
                                @endif
                            @endcan
                        </li>
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
                                    <p class="mb-0">{{ $invoice ? invoicePrefix() . $invoice->invoice_id : '' }}</p>
                                </div>
                                <div class="col-sm-6 text-sm-end">
                                    <h6>
                                        {{ __('Invoice No') }} :
                                        <span
                                            class="text-muted f-w-400">{{ invoicePrefix() . $invoice?->invoice_id ?? '-' }}</span>
                                    </h6>
                                    <h6>
                                        {{ __('Created Date') }} :
                                        <span
                                            class="text-muted f-w-400">{{ dateFormat($invoice->created_at ?? '-') }}</span>
                                    </h6>
                                    <h6>
                                        {{ __('Invoice Date') }} :
                                        <span
                                            class="text-muted f-w-400">{{ dateFormat($invoice->invoice_date ?? '-') }}</span>
                                    </h6>
                                    <h6>
                                        {{ __('Status') }} :
                                        <span class="text-muted f-w-400">
                                            @if (isset($invoice->status))
                                                @if ($invoice->status == 0)
                                                    <span
                                                        class="badge text-bg-danger">{{ \App\Models\Invoice::statues()[$invoice->status] }}</span>
                                                @elseif($invoice->status == 1)
                                                    <span
                                                        class="badge text-bg-warning">{{ \App\Models\Invoice::statues()[$invoice->status] }}</span>
                                                @elseif($invoice->status == 2)
                                                    <span
                                                        class="badge text-bg-success">{{ \App\Models\Invoice::statues()[$invoice->status] }}</span>
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
                                <h5>{{ $invoice->clients->name ?? '-' }}</span>
                                </h5>
                                <p class="mb-0">
                                    <span> {{ $invoice->clients->phone_number ?? '-' }}</span>
                                </p>
                                <p class="mb-0">
                                    <span> {{ $invoice->clients->clients->address ?? '-' }} <br>
                                        {{ $invoice->clients->clients->city ?? '-' }},
                                        {{ $invoice->clients->clients->state ?? '-' }},
                                        {{ $invoice->clients->clients->country ?? '-' }},
                                        {{ $invoice->clients->clients->zip ?? '-' }}</span>
                                </p>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    @if ($invoice?->types?->isNotEmpty())
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
                                            @foreach ($invoice->types as $servicetype)
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
                                                                @php
                                                                    $sTaxPrice = App\Models\Service::taxRate(
                                                                        $sTax->rate,
                                                                        $servicetype->rate,
                                                                    );
                                                                @endphp
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
                                    @if ($invoice?->items?->isNotEmpty())
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
                                            @foreach ($invoice->items as $item)
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
                                                        {{ $item->item_snapshot['item_code'] ?? $item->items?->item_code ?? '-' }}
                                                        <br>
                                                        {{ __('Item') }} :
                                                        {{ $item->item_title }}
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
                                                    @if ($invoice?->items?->count() > 0)
                                                        <tr>
                                                            <th>{{ __('Sub Total') }} <span
                                                                    class="text-muted text-sm">({{ __('Item') }})</span>
                                                                :
                                                            </th>
                                                            <td>{{ priceFormat(number_format($invoice->getInvoiceSubTotalAmount(), 2)) }}
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
                                                            <td>{{ priceFormat(number_format($invoice->getInvoiceItemAmount(), 2)) }}
                                                            </td>
                                                        </tr>
                                                        <tr>
                                                            <th>{{ __('Sub Total') }} <span
                                                                    class="text-muted text-sm">({{ __('Service') }})</span>
                                                                :
                                                            </th>
                                                            <td>{{ priceFormat(number_format($invoice->getInvoiceServiceSubTotalAmount(), 2)) }}
                                                            </td>
                                                        </tr>
                                                    @endif
                                                    @php
                                                        $serviceTaxesData = [];
                                                        foreach ($invoice?->types ?? collect() as $servicetype) {
                                                            if (empty($servicetype?->tax)) {
                                                                continue;
                                                            }
                                                            $taxIds = explode(',', $servicetype?->tax);
                                                            $sTaxes = App\Models\Tax::whereIn('id', $taxIds)->get();
                                                            foreach ($sTaxes as $sTax) {
                                                                $sTaxPrice = App\Models\Service::taxRate(
                                                                    $sTax?->rate,
                                                                    $servicetype?->rate,
                                                                );
                                                                if (array_key_exists($sTax->title, $serviceTaxesData)) {
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
                                                        <td>{{ priceFormat(number_format($invoice?->getInvoiceServiceAmount(), 2)) }}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>{{ __('Grand Total') }} :</th>
                                                        <td class="h5">
                                                            {{ priceFormat(number_format($invoice?->getInvoiceAllTotalAmount(), 2)) }}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>{{ __('Paid Amount') }} :</th>
                                                        <td class="h5">
                                                            {{ priceFormat(number_format($invoice?->getInvoiceAllTotalAmount() - $invoice?->getInvoiceTotalDueAmount(), 2)) }}
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>{{ __('Due Amount') }} :</th>
                                                        <td class="h5">
                                                            {{ priceFormat(number_format($invoice?->getInvoiceTotalDueAmount(), 2)) }}
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
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>{{ __('Payment History') }}</h5>
                </div>
                <div class="card-body">
                    <div class="dt-responsive table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>{{ __('Transaction Id') }}</th>
                                    <th>{{ __('Payment Date') }}</th>
                                    <th>{{ __('Amount') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Notes') }}</th>
                                    @can('delete invoice payment')
                                        <th class="text-right action">{{ __('Action') }}</th>
                                    @endcan
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoice?->payments ?? collect() as $payment)
                                    <tr role="row">
                                        <td>{{ $payment->transaction_id }} </td>
                                        <td>{{ dateFormat($payment->payment_date) }} </td>
                                        <td>{{ priceFormat($payment->amount) }} </td>
                                        <td>
                                            @if ($payment->payment_status == 'pending')
                                                <span
                                                    class="d-inline badge text-bg-warning text-capitalize ">{{ __((string) $payment->payment_status) }}</span>
                                            @elseif($payment->payment_status == 'succeeded' || $payment->payment_status == 'success')
                                                <span
                                                    class="d-inline badge text-bg-success text-capitalize">{{ __((string) $payment->payment_status) }}</span>
                                            @else
                                                <span
                                                    class="d-inline badge text-bg-danger text-capitalize">{{ __((string) $payment->payment_status) }}</span>
                                            @endif
                                        </td>
                                        <td>{{ !empty($payment->description) ? $payment->description : '' }} </td>
                                        @can('delete invoice payment')
                                            <td class="text-right action">
                                                <div class="cart-action">
                                                    @if (\Auth::user()->type == 'owner' && $payment->payment_status == 'pending')
                                                        <a class="avtar avtar-xs btn-link-success text-success"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Accept') }}"
                                                            href="{{ route('invoice.bank.transfer.action', [$payment->id, 'accept']) }}">
                                                            <i data-feather="user-check"></i></a>

                                                        <a class="avtar avtar-xs btn-link-danger text-danger"
                                                            data-bs-toggle="tooltip"
                                                            data-bs-original-title="{{ __('Reject') }}"
                                                            href="{{ route('invoice.bank.transfer.action', [$payment->id, 'reject']) }}">
                                                            <i data-feather="user-x"></i></a>
                                                    @endif
                                                    {!! Form::open([
                                                        'method' => 'POST',
                                                        'class' => 'd-inline',
                                                        'route' => ['invoice.payment.destroy', $invoice->id, $payment->id],
                                                    ]) !!}
                                                    <a class="avtar avtar-xs btn-link-danger d-print-none text-danger confirm_dialog"
                                                        data-bs-toggle="tooltip"
                                                        data-bs-original-title="{{ __('Detete') }}" href="#"> <i
                                                            data-feather="trash-2"></i></a>
                                                    {!! Form::close() !!}
                                                </div>
                                            </td>
                                        @endcan
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
@push('script-page')
    <script>
        $(document).on('click', '.invoiceStatusChange', function() {
            var invoiceStatus = this.value;
            var invoiceUrl = $(this).data('url');
            $.ajax({
                url: invoiceUrl + '?status=' + invoiceStatus,
                type: 'GET',
                cache: false,
                success: function(data) {
                    location.reload();
                },
            });
        });
        $(document).on('click', '.print', function() {
            var invoicePrintContents = document.getElementById('invoicePrint').innerHTML;
            var originalContents = document.body.innerHTML;
            document.body.innerHTML = invoicePrintContents;
            $('.del_div').addClass('d-none');
            window.print();
            document.body.innerHTML = originalContents;
            $('.del_div').removeClass('d-none');
        });
    </script>

    @if (\Auth::user()->type == 'client')
        <script src="https://js.stripe.com/v3/"></script>
        @if (
            $invoicePaymentSettings['STRIPE_PAYMENT'] == 'on' &&
                !empty($invoicePaymentSettings['STRIPE_KEY']) &&
                !empty($invoicePaymentSettings['STRIPE_SECRET']))
            <script type="text/javascript">
                let stripeCardInstance = null;
                $(document).on('click', '.stripe_payment_tab', function() {
                    if (stripeCardInstance) {
                        stripeCardInstance.unmount();
                        $('#card-element').html('');
                    }
                    const stripe = Stripe('{{ $invoicePaymentSettings['STRIPE_KEY'] }}');
                    const elements = stripe.elements();
                    const style = {
                        base: {
                            fontSize: '14px',
                            color: '#32325d',
                        },
                    };
                    stripeCardInstance = elements.create('card', {
                        style: style
                    });
                    stripeCardInstance.mount('#card-element');
                    const stripeForm = document.getElementById('stripe-payment');
                    if (!stripeForm.dataset.handlerAttached) {
                        stripeForm.addEventListener('submit', function(event) {
                            event.preventDefault();
                            const billingDetails = {
                                line1: document.querySelector('[name="state"]')?.value || '',
                                city: document.querySelector('[name="city"]')?.value || '',
                                postal_code: document.querySelector('[name="zipcode"]')?.value || '',
                                country: document.querySelector('[name="country"]')?.value || ''
                            };
                            stripe.createToken(stripeCardInstance).then(function(result) {
                                if (result.error) {
                                    $("#stripe_card_errors").html(result.error.message);
                                    $.NotificationApp.send("Error", result.error.message, "top-right",
                                        "rgba(0,0,0,0.2)", "error");
                                } else {
                                    const token = result.token;
                                    const hiddenInput = document.createElement('input');
                                    hiddenInput.setAttribute('type', 'hidden');
                                    hiddenInput.setAttribute('name', 'stripeToken');
                                    hiddenInput.setAttribute('value', token.id);
                                    stripeForm.appendChild(hiddenInput);
                                    stripeForm.submit();
                                }
                            });
                        });
                        stripeForm.dataset.handlerAttached = "true";
                    }
                });
            </script>
        @endif
        {{-- ************************* flutterwave payment script ************************* --}}
        @if (
            $invoicePaymentSettings['flutterwave_payment'] == 'on' &&
                !empty($invoicePaymentSettings['flutterwave_public_key']) &&
                !empty($invoicePaymentSettings['flutterwave_secret_key']))
            <script src="https://api.ravepay.co/flwv3-pug/getpaidx/api/flwpbf-inline.js"></script>
            <script>
                $(document).on("click", "#flutterwavePaymentBtn", function() {
                    var amount = $('.amount').val().trim();
                    if (!amount || amount <= 0) {
                        alert('Please enter a valid amount');
                        return;
                    }

                    var tx_ref = "RX1_" + Math.floor((Math.random() * 1000000000) + 1);
                    var customer_email = '{{ \Auth::user()->email }}';
                    var customer_name = '{{ \Auth::user()->name }}';
                    var flutterwave_public_key = '{{ $invoicePaymentSettings['flutterwave_public_key'] }}';
                    var currency = '{{ $invoicePaymentSettings['CURRENCY'] }}';

                    var flutterwavePayment = getpaidSetup({
                        txref: tx_ref,
                        PBFPubKey: flutterwave_public_key,
                        amount: amount,
                        currency: currency,
                        customer_email: customer_email,
                        customer_name: customer_name,
                        meta: [{
                            metaname: "payment_id",
                            metavalue: "id"
                        }],
                        onclose: function() {},
                        callback: function(result) {
                            console.log(result);
                            console.log(result.tx.chargeResponseCode == "00" || result.tx.chargeResponseCode ==
                                "0");


                            if (result.tx.chargeResponseCode == "00" || result.tx.chargeResponseCode == "0") {
                                var txRef = result.tx.txRef;
                                var redirectUrl =
                                    "{{ url('invoice/flutterwave') }}/{{ encrypt($invoice->id) }}/" +
                                    txRef + "?amount=" + amount;
                                window.location.href = redirectUrl;
                            } else {
                                alert('Payment failed');
                            }
                            flutterwavePayment.close();
                        }
                    });
                });
            </script>
        @endif

        {{-- ************************* paystack payment script ************************* --}}
        <script src="{{ asset('assets/js/plugins/jquery.form.min.js') }}"></script>
        <script src="https://js.paystack.co/v1/inline.js"></script>
        @if (isset($invoicePaymentSettings['paystack_payment']) && $invoicePaymentSettings['paystack_payment'] == 'on')
            <script>
                $(document).ready(function() {
                    $(document).on("click", "#paystackPaymentBtn", function(e) {
                        e.preventDefault();

                        const $button = $(this);
                        const $paymentForm = $('#paystack-payment-form');
                        const formActionUrl = $paymentForm.attr('action');
                        const formMethod = $paymentForm.attr('method');
                        const formSerializedData = $paymentForm.serialize();

                        const paystackPublicKey = "{{ $invoicePaymentSettings['paystack_public_key'] }}";
                        const invoiceId = "{{ encrypt($invoice->id) }}";

                        const redirectTemplate =
                            "{{ route('invoice.paystack', ['pay_id' => 'PAY_ID', 'id' => 'INVOICE_ID']) }}";

                        $button.prop('disabled', true).text('Processing...');

                        $.ajax({
                            url: formActionUrl,
                            method: formMethod,
                            data: formSerializedData,
                            dataType: 'json',
                            success: function(res) {
                                if (res.flag === 1) {
                                    const transactionReference = 'pay_ref_' + Math.floor(Math.random() *
                                        1000000000 + 1);

                                    const paystackOptions = {
                                        key: paystackPublicKey,
                                        email: res.email,
                                        amount: res.total_price * 100,
                                        currency: res.currency,
                                        ref: transactionReference,
                                        callback: function(response) {
                                            const redirectUrl = redirectTemplate
                                                .replace('PAY_ID', response.reference)
                                                .replace('INVOICE_ID', invoiceId);

                                            window.location.href = redirectUrl;
                                        },
                                        onClose: function() {
                                            alert(
                                                'Payment popup was closed without completing.'
                                            );
                                            $button.prop('disabled', false).text('Pay Now');
                                        }
                                    };

                                    const paymentHandler = PaystackPop.setup(paystackOptions);
                                    paymentHandler.openIframe();
                                } else {
                                    show_toastr(res.flag === 2 ? 'Warning' : 'Error', res.message,
                                        'msg');
                                    $button.prop('disabled', false).text('Pay Now');
                                }
                            },
                            error: function(xhr) {
                                console.error('AJAX Error:', xhr.responseText);
                                show_toastr('Error', 'An unexpected error occurred. Please try again.',
                                    'msg');
                                $button.prop('disabled', false).text('Pay Now');
                            }
                        });
                    });
                });
            </script>
        @endif
    @endif
@endpush
