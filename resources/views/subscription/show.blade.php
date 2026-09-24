@extends('layouts.app')
@section('page-title')
    {{ __('Subscription') }}
@endsection
@push('script-page')
    <script src="https://js.stripe.com/v3/"></script>
    <script>
        $(document).on('click', '.have_coupon', function() {
            var element = $(this).parent().parent().parent().parent().parent().parent().find('.coupon_div');
            console.log(element);

            if ($(this).is(":checked")) {
                $(element).removeClass('d-none');
            } else {
                $(element).addClass('d-none');
            }
        });

        $(document).on('click', '.packageCouponApplyBtn', function() {
            var element = $(this);
            var couponCode = element.closest('.row').find('.packageCouponCode').val();
            $.ajax({
                url: '{{ route('coupons.apply') }}',
                datType: 'json',
                data: {
                    package: '{{ \Illuminate\Support\Facades\Crypt::encrypt($subscription->id) }}',
                    coupon: couponCode
                },
                success: function(result) {
                    $('.discoutedPrice').text(result.discoutedPrice);
                    if (result != '') {
                        if (result.status == true) {
                            toastrs('success', result.msg, 'success');
                        } else {
                            toastrs('Error', result.msg, 'error');
                        }
                    } else {
                        toastrs('Error', "{{ __('Please enter coupon code.') }}", 'error');
                    }
                }
            })
        });
    </script>
    <script>
        @if ($settings['STRIPE_PAYMENT'] == 'on' && !empty($settings['STRIPE_KEY']) && !empty($settings['STRIPE_SECRET']))
            var stripe_key = Stripe('{{ $settings['STRIPE_KEY'] }}');
            var stripe_elements = stripe_key.elements();
            var strip_css = {
                base: {
                    fontSize: '14px',
                    color: '#32325d',
                },
            };
            var stripe_card = stripe_elements.create('card', {
                style: strip_css
            });
            stripe_card.mount('#card-element');

            var stripe_form = document.getElementById('stripe-payment-form');
            stripe_form.addEventListener('submit', function(event) {
                event.preventDefault();

                var billingDetails = {
                    line1: document.querySelector('[name="state"]')?.value || '',
                    city: document.querySelector('[name="city"]')?.value || '',
                    postal_code: document.querySelector('[name="zipcode"]')?.value || '',
                    country: document.querySelector('[name="country"]')?.value || ''
                };

                stripe_key.createToken(stripe_card).then(function(result) {
                    if (result.error) {
                        $("#stripe_card_errors").html(result.error.message);
                        $.NotificationApp.send("Error", result.error.message, "top-right",
                            "rgba(0,0,0,0.2)", "error");
                    } else {
                        var token = result.token;
                        var stripeForm = document.getElementById('stripe-payment-form');
                        var stripeHiddenData = document.createElement('input');
                        stripeHiddenData.setAttribute('type', 'hidden');
                        stripeHiddenData.setAttribute('name', 'stripeToken');
                        stripeHiddenData.setAttribute('value', token.id);
                        stripeForm.appendChild(stripeHiddenData);
                        stripeForm.submit();
                    }
                });
            });
        @endif
    </script>
    <script src="https://api.ravepay.co/flwv3-pug/getpaidx/api/flwpbf-inline.js"></script>

    <script>
        @if (
            $settings['flutterwave_payment'] == 'on' &&
                !empty($settings['flutterwave_public_key']) &&
                !empty($settings['flutterwave_secret_key']))

            $(document).on("click", "#flutterwavePaymentBtn", function() {
                var discoutedPrice = $('.discoutedPrice').text();
                var currency_symbol = '{{ $settings['CURRENCY_SYMBOL'] }}';
                var amount = discoutedPrice.replace(currency_symbol, "");
                var flutterwaveCallbackURL = "{{ url('subscription/flutterwave/') }}";
                var tx_ref = "RX1_" + Math.floor((Math.random() * 1000000000) + 1);
                var customer_email = '{{ \Auth::user()->email }}';
                var customer_name = '{{ \Auth::user()->name }}';
                var flutterwave_public_key = '{{ $settings['flutterwave_public_key'] }}';
                var nowTim = "{{ date('d-m-Y-h-i-a') }}";
                var currency = '{{ $settings['CURRENCY'] }}';

                if (amount) {
                    var flutterwavePayment = getpaidSetup({
                        txref: tx_ref,
                        PBFPubKey: flutterwave_public_key,
                        amount: amount,
                        currency: currency,
                        customer_name: customer_name,
                        customer_email: customer_email,
                        meta: [{
                            consumer_id: "23",
                            consumer_mac: "92a3-912ba-1192a"
                        }],
                        onclose: function() {},
                        callback: function(result) {
                            var txRef = result.tx.txRef;
                            var redirectUrl = flutterwaveCallbackURL + '/' +
                                '{{ \Illuminate\Support\Facades\Crypt::encrypt($subscription->id) }}' +
                                '/' + txRef;
                            if (result.tx.chargeResponseCode == "00" || result.tx.chargeResponseCode ==
                                "0") {
                                window.location.href = redirectUrl;
                            } else {
                                alert('Payment failed');
                            }
                            flutterwavePayment.close();
                        }
                    });
                } else {
                    alert('Please enter a valid amount');
                }
            });
        @endif
    </script>

    <script src="https://js.paystack.co/v1/inline.js"></script>
    @if (!empty($settings['paystack_payment']) && $settings['paystack_payment'] === 'on')
        <script>
            $(document).ready(function() {
                $(document).on("click", "#subscription_pay_with_paystack", function(e) {
                    e.preventDefault();

                    const $button = $(this);
                    const $paymentForm = $('#paystack-payment-form');
                    const formActionUrl = $paymentForm.attr('action');
                    const formMethod = $paymentForm.attr('method');
                    const formSerializedData = $paymentForm.serialize();

                    const paystackPublicKey = "{{ $settings['paystack_public_key'] }}";
                    const redirectBaseUrl = "{{ url('/subscription/paystack') }}";
                    const encryptedSubscriptionId = "{{ encrypt($subscription->id) }}";

                    $button.prop('disabled', true).text('Processing...');

                    $.ajax({
                        url: formActionUrl,
                        method: formMethod,
                        data: formSerializedData,
                        dataType: 'json',
                        success: function(response) {
                            if (response.flag === 1) {
                                const transactionReference = 'txn_' + Math.floor(Math.random() *
                                    1000000000 + 1);

                                const paystackOptions = {
                                    key: paystackPublicKey,
                                    email: response.email,
                                    amount: response.total_price * 100,
                                    currency: response.currency,
                                    ref: transactionReference,
                                    metadata: {
                                        custom_fields: [{
                                            display_name: "Customer Email",
                                            variable_name: "customer_email",
                                            value: response.email
                                        }]
                                    },
                                    callback: function(paystackResponse) {
                                        window.location.href =
                                            `${redirectBaseUrl}/${paystackResponse.reference}/${encryptedSubscriptionId}?coupon_id=${response.coupon}`;
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
                                show_toastr('Error', response.message, 'msg');
                                $button.prop('disabled', false).text('Pay Now');
                            }
                        },
                        error: function(xhr) {
                            console.error('AJAX Error:', xhr.responseText);
                            show_toastr('Error', 'An unexpected error occurred. Please try again.',
                                'msg');
                            $button.prop('disabled', false).text("{{ __('Pay Now') }}");
                        }
                    });
                });
            });
        </script>
    @endif

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.form/4.3.0/jquery.form.min.js"></script>
    @if (
        $settings['razorpay_payment'] == 'on' &&
            !empty($settings['razorpay_public_key']) &&
            !empty($settings['razorpay_secret_key']))
        <script>
            $(document).on("click", "#pay_with_razorpay", function() {

                $('#razorpay-payment-form').ajaxForm(function(res) {
                    console.log(res.flag);
                    if (res.flag == 1) {

                        var razorPay_callback = '{{ url('/subscription/razorpay') }}';
                        var totalAmount = res.total_price * 100;
                        var coupon_id = res.coupon;
                        var options = {
                            "key": "{{ $settings['razorpay_public_key'] }}", // your Razorpay Key Id
                            "amount": totalAmount,
                            "name": 'subscription',
                            "currency": '{{ $settings['CURRENCY'] }}',
                            "description": "",
                            "handler": function(response) {
                                window.location.href = razorPay_callback + '/' + response
                                    .razorpay_payment_id + '/' +
                                    '{{ \Illuminate\Support\Facades\Crypt::encrypt($subscription->id) }}?coupon_id=' +
                                    coupon_id;
                            },
                            "theme": {
                                "color": "#528FF0"
                            }
                        };
                        var rzp1 = new Razorpay(options);
                        rzp1.open();
                    } else if (res.flag == 2) {
                        toastrs('success', res.msg, 'success');

                        setInterval(function() {
                            window.location.href = "{{ route('subscriptions.index') }}";
                        }, 2000);
                    } else {
                        toastrs('Error', data.message, 'msg');
                    }

                }).submit();
            });
        </script>
    @endif
@endpush
@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('subscriptions.index') }}">{{ __('Subscription') }}</a></li>
    <li class="breadcrumb-item" aria-current="page">{{ __('Details') }}</li>
@endsection
@section('content')
    <div class="row pricing-grid">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <table class="display dataTable cell-border ">
                        <thead>
                            <tr>
                                <th>{{ __('Title') }}</th>
                                <th>{{ __('Amount') }}</th>
                                <th>{{ __('Interval') }}</th>
                                <th>{{ __('User Limit') }}</th>
                                <th>{{ __('Customer Limit') }}</th>
                                <th>{{ __('Agent Limit') }}</th>
                                <th>{{ __('Coupon Applicable') }}</th>
                                <th>{{ __('User Logged History') }}</th>
                                <th>{{ __('Enabled Open Ai Support') }}</th>
                                <th>{{ __('Enabled N8n') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    {{ $subscription->title }}
                                </td>
                                <td>
                                    <b class="discoutedPrice">
                                        {{ subscriptionPaymentSettings()['CURRENCY_SYMBOL'] }}{{ $subscription->package_amount }}</b>
                                </td>
                                <td>{{ __((string) $subscription->interval) }} </td>
                                <td>{{ $subscription->user_limit }} </td>
                                <td>{{ $subscription->customer_limit }} </td>
                                <td>{{ $subscription->agent_limit }} </td>
                                <td>
                                    @if ($subscription->couponCheck() > 0)
                                        <i class="text-success mr-4" data-feather="check-circle"></i>
                                    @else
                                        <i class="text-danger mr-4" data-feather="x-circle"></i>
                                    @endif
                                </td>
                                <td>
                                    @if ($subscription->enabled_logged_history == 1)
                                        <i class="text-success mr-4" data-feather="check-circle"></i>
                                    @else
                                        <i class="text-danger mr-4" data-feather="x-circle"></i>
                                    @endif
                                </td>
                                <td>
                                    @if ($subscription->enabled_openai == 1)
                                        <i class="text-success mr-4" data-feather="check-circle"></i>
                                    @else
                                        <i class="text-danger mr-4" data-feather="x-circle"></i>
                                    @endif
                                </td>
                                <td>
                                    @if ($subscription->enabled_n8n == 1)
                                        <i class="text-success mr-4" data-feather="check-circle"></i>
                                    @else
                                        <i class="text-danger mr-4" data-feather="x-circle"></i>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="row pricing-grid">
        <div class="col-lg-12">
            <div class="row">
                @if ($settings['bank_transfer_payment'] == 'on')
                    <div class="col-sm-6">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center g-2">
                                    <div class="col">
                                        <h5>{{ __('Bank Transfer Payment') }}</h5>
                                    </div>
                                    <div class="col-auto">
                                        @if ($subscription->couponCheck() > 0)
                                            <div class="setting-card action-menu">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input have_coupon"
                                                        id="have_bank_tran_coupon">
                                                    <label
                                                        for="have_bank_tran_coupon">{{ __('Have a Discount Coupon Code?') }}</label>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                            </div>
                            <div class="card-body profile-user-box">
                                <form
                                    action="{{ route('subscription.bank.transfer', \Illuminate\Support\Facades\Crypt::encrypt($subscription->id)) }}"
                                    method="post" class="require-validation" id="bank-payment"
                                    enctype="multipart/form-data">
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="card-name-on"
                                                    class="form-label text-dark">{{ __('Bank Name') }}</label>
                                                <p>{{ $settings['bank_name'] }}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="card-name-on"
                                                    class="form-label text-dark">{{ __('Bank Holder Name') }}</label>
                                                <p>{{ $settings['bank_holder_name'] }}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="card-name-on"
                                                    class="form-label text-dark">{{ __('Bank Account Number') }}</label>
                                                <p>{{ $settings['bank_account_number'] }}</p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label for="card-name-on"
                                                    class="form-label text-dark">{{ __('Bank IFSC Code') }}</label>
                                                <p>{{ $settings['bank_ifsc_code'] }}</p>
                                            </div>
                                        </div>
                                        @if (!empty($settings['bank_other_details']))
                                            <div class="col-md-12">
                                                <div class="form-group">
                                                    <label for="card-name-on"
                                                        class="form-label text-dark">{{ __('Bank Other Details') }}</label>
                                                    <p>{{ $settings['bank_other_details'] }}</p>
                                                </div>
                                            </div>
                                        @endif

                                        <div class="col-md-12 d-none coupon_div">
                                            <div class="form-group">
                                                <label for="card-name-on"
                                                    class="form-label text-dark">{{ __('Coupon Code') }}</label>
                                                <input type="text" name="coupon"
                                                    class="form-control required packageCouponCode"
                                                    placeholder="{{ __('Enter Coupon Code') }}">
                                            </div>
                                        </div>
                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <label for="card-name-on"
                                                    class="form-label text-dark">{{ __('Attachment') }}</label>
                                                <input type="file" name="payment_receipt" id="payment_receipt"
                                                    class="form-control" required>
                                            </div>
                                        </div>
                                        <div class="col-sm-12 ">
                                            <input type="button" value="{{ __('Coupon Apply') }}"
                                                class="btn btn-warning packageCouponApplyBtn d-none coupon_div">
                                            <input type="submit" value="{{ __('Pay') }}" class="btn btn-secondary">
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
                @if ($settings['STRIPE_PAYMENT'] == 'on' && !empty($settings['STRIPE_KEY']) && !empty($settings['STRIPE_SECRET']))
                    <div class="col-sm-6">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center g-2">
                                    <div class="col">
                                        <h5>{{ __('Stripe Payment') }}</h5>
                                    </div>
                                    <div class="col-auto">
                                        @if ($subscription->couponCheck() > 0)
                                            <div class="setting-card action-menu">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input have_coupon"
                                                        id="have_stripe_coupon">
                                                    <label
                                                        for="have_stripe_coupon">{{ __('Have a Discount Coupon Code?') }}</label>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="card-body profile-user-box">
                                <form
                                    action="{{ route('subscription.stripe.payment', \Illuminate\Support\Facades\Crypt::encrypt($subscription->id)) }}"
                                    method="post" class="require-validation" id="stripe-payment-form">
                                    @csrf
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="card-name-on"
                                                    class="form-label text-dark">{{ __('Card Name') }}</label>
                                                <input type="text" name="name" id="card-name-on"
                                                    class="form-control required"
                                                    placeholder="{{ __('Card Holder Name') }}">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="card-name-on"
                                                class="form-label text-dark">{{ __('Card Details') }}</label>
                                            <div id="card-element">
                                            </div>
                                            <div id="stripe_card_errors" role="alert"></div>
                                        </div>

                                        @if ($subscription->couponCheck() > 0)
                                            <div class="col-md-12 d-none coupon_div">
                                                <div class="form-group">
                                                    <label for="card-name-on"
                                                        class="form-label text-dark">{{ __('Coupon Code') }}</label>
                                                    <input type="text" name="coupon"
                                                        class="form-control required packageCouponCode"
                                                        placeholder="{{ __('Enter Coupon Code') }}">
                                                </div>
                                            </div>
                                        @endif
                                        <div class="col-sm-12 mt-15">
                                            @if ($subscription->couponCheck() > 0)
                                                <input type="button" value="{{ __('Coupon Apply') }}"
                                                    class="btn btn-warning packageCouponApplyBtn d-none coupon_div">
                                            @endif
                                            <input type="submit" value="{{ __('Pay') }}" class="btn btn-secondary">
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif

                @if (
                    $settings['paypal_payment'] == 'on' &&
                        !empty($settings['paypal_client_id']) &&
                        !empty($settings['paypal_secret_key']))
                    <div class="col-sm-6">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center g-2">
                                    <div class="col">
                                        <h5>{{ __('Paypal Payment') }}</h5>
                                    </div>
                                    <div class="col-auto">
                                        @if ($subscription->couponCheck() > 0)
                                            <div class="setting-card action-menu">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input have_coupon"
                                                        id="have_paypal_coupon">
                                                    <label
                                                        for="have_paypal_coupon">{{ __('Have a Discount Coupon Code?') }}</label>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>


                            </div>
                            <div class="card-body profile-user-box">
                                <form
                                    action="{{ route('subscription.paypal', \Illuminate\Support\Facades\Crypt::encrypt($subscription->id)) }}"
                                    method="post" class="require-validation">
                                    @csrf
                                    <div class="row">
                                        @if ($subscription->couponCheck() > 0)
                                            <div class="col-md-12 mt-15 d-none coupon_div">
                                                <div class="form-group">
                                                    <label for="card-name-on"
                                                        class="form-label text-dark">{{ __('Coupon Code') }}</label>
                                                    <input type="text" name="coupon"
                                                        class="form-control required packageCouponCode"
                                                        placeholder="{{ __('Enter Coupon Code') }}">
                                                </div>
                                            </div>
                                        @endif
                                        <div class="col-sm-12 mt-15">
                                            @if ($subscription->couponCheck() > 0)
                                                <input type="button" value="{{ __('Coupon Apply') }}"
                                                    class="btn btn-warning packageCouponApplyBtn d-none coupon_div">
                                            @endif
                                            <input type="submit" value="{{ __('Pay') }}" class="btn btn-secondary">
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif

                @if (
                    !empty($settings['flutterwave_payment']) &&
                        $settings['flutterwave_payment'] == 'on' &&
                        !empty($settings['flutterwave_public_key']) &&
                        !empty($settings['flutterwave_secret_key']))
                    <div class="col-sm-6">
                        <div class="card">
                            <div class="card-header">
                                <div class="row align-items-center g-2">
                                    <div class="col">
                                        <h5>{{ __('Flutterwave Payment') }}</h5>
                                    </div>
                                    <div class="col-auto">
                                        @if ($subscription->couponCheck() > 0)
                                            <div class="setting-card action-menu">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input have_coupon"
                                                        id="have_flutterwave_coupon">
                                                    <label
                                                        for="have_flutterwave_coupon">{{ __('Have a Discount Coupon Code?') }}</label>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            <div class="card-body profile-user-box">
                                <form action="#" class="require-validation" method="get">
                                    @csrf
                                    <div class="row">
                                        @if ($subscription->couponCheck() > 0)
                                            <div class="col-md-12 d-none coupon_div">
                                                <div class="form-group">
                                                    <label for="card-name-on"
                                                        class="form-label text-dark">{{ __('Coupon Code') }}</label>
                                                    <input type="text" name="coupon"
                                                        class="form-control required packageCouponCode"
                                                        placeholder="{{ __('Enter Coupon Code') }}">
                                                </div>
                                            </div>
                                        @endif
                                        <div class="col-sm-12">
                                            @if ($subscription->couponCheck() > 0)
                                                <input type="button" value="{{ __('Coupon Apply') }}"
                                                    class="btn btn-warning packageCouponApplyBtn d-none coupon_div">
                                            @endif
                                            <input type="button" value="{{ __('Pay') }}" id="flutterwavePaymentBtn"
                                                class="btn btn-secondary">
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif

                @if (
                    $settings['paystack_payment'] == 'on' &&
                        !empty($settings['paystack_public_key']) &&
                        !empty($settings['paystack_secret_key']))
                    <div class="col-sm-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="d-inline">{{ __('Paystack') }}</h5>
                                @if ($subscription->couponCheck() > 0)
                                    <div class="setting-card action-menu float-end">
                                        <div class="form-group mb-0">
                                            <div class="form-check custom-chek">
                                                <input class="form-check-input have_coupon" type="checkbox"
                                                    value="" id="have_paystack_coupon">
                                                <label class="form-check-label"
                                                    for="have_paystack_coupon">{{ __('Have a Discount Coupon Code?') }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <div class="card-body profile-user-box">
                                <form id="paystack-payment-form" method="POST"
                                    action="{{ route('subscription.pay.with.paystack') }}" class="require-validation">
                                    @csrf
                                    <input type="hidden" name="plan_id"
                                        value="{{ Crypt::encrypt($subscription->id) }}">

                                    <div class="row">
                                        @if ($subscription->couponCheck() > 0)
                                            <div class="col-md-12 coupon_div d-none">
                                                <div class="form-group">
                                                    <label class="form-label text-dark"
                                                        for="coupon">{{ __('Coupon Code') }}</label>
                                                    <input type="text" id="coupon" name="coupon"
                                                        class="form-control required packageCouponCode"
                                                        placeholder="{{ __('Enter Coupon Code') }}">
                                                </div>
                                            </div>
                                        @endif

                                        <div class="col-sm-12">
                                            @if ($subscription->couponCheck() > 0)
                                                <button type="button"
                                                    class="btn btn-warning packageCouponApplyBtn d-none coupon_div">
                                                    {{ __('Coupon Apply') }}
                                                </button>
                                            @endif

                                            <button type="button" class="btn btn-secondary"
                                                id="subscription_pay_with_paystack">
                                                {{ __('Pay') }}
                                            </button>
                                        </div>
                                    </div>
                                </form>

                            </div>
                        </div>
                    </div>
                @endif

                @if (
                    $settings['razorpay_payment'] == 'on' &&
                        !empty($settings['razorpay_public_key']) &&
                        !empty($settings['razorpay_secret_key']))
                    <div class="col-sm-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="d-inline">{{ __('Razorpay Payment') }}</h5>

                                @if ($subscription->couponCheck() > 0)
                                    <div class="setting-card action-menu float-end">
                                        <div class="form-group mb-0">
                                            <div class="form-check custom-chek">
                                                <input class="form-check-input have_coupon" type="checkbox"
                                                    value="" id="have_razorpay_coupon">
                                                <label class="form-check-label"
                                                    for="have_razorpay_coupon">{{ __('Have a Discount Coupon Code?') }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                            <div class="card-body profile-user-box">
                                <form action="{{ route('subscription.pay.with.razorpay') }}" method="post"
                                    class="require-validation" id="razorpay-payment-form">
                                    @csrf
                                    <input type="hidden" name="plan_id"
                                        value="{{ \Illuminate\Support\Facades\Crypt::encrypt($subscription->id) }}">
                                    <div class="row">

                                        @if ($subscription->couponCheck() > 0)
                                            <div class="col-md-12 coupon_div d-none">
                                                <div class="form-group">
                                                    <label for="card-name-on"
                                                        class="form-label text-dark">{{ __('Coupon Code') }}</label>
                                                    <input type="text" name="coupon"
                                                        class="form-control required packageCouponCode"
                                                        placeholder="{{ __('Enter Coupon Code') }}">
                                                </div>
                                            </div>
                                        @endif
                                        <div class="col-sm-12">

                                            @if ($subscription->couponCheck() > 0)
                                                <input type="button" value="{{ __('Coupon Apply') }}"
                                                    class="btn btn-warning packageCouponApplyBtn d-none coupon_div">
                                            @endif
                                            <input type="button" id="pay_with_razorpay" value="{{ __('Pay') }}"
                                                class="btn btn-secondary">
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
