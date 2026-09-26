@extends('layouts.app')
@section('page-title')
    {{ __('Subscription') }}
@endsection
@push('script-page')

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

    </script>
    <script src="https://api.ravepay.co/flwv3-pug/getpaidx/api/flwpbf-inline.js"></script>

    <script>

    </script>





    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.form/4.3.0/jquery.form.min.js"></script>

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









            </div>
        </div>
    </div>
@endsection
