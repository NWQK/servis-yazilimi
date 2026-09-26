    <div class="col-xxl-12 cdx-xxl-100">
        <div class="payment-method card">

            <div class="card-body">
                <ul class="nav nav-tabs profile-tabs border-bottom mb-3 d-print-none" id="myTab" role="tablist">
                    @if ($settings['bank_transfer_payment'] == 'on')
                        <li class="nav-item">
                            <a class="nav-link text-sm active" id="profile-tab-1" data-bs-toggle="tab"
                                href="#bank_transfer" role="tab" aria-selected="true">{{ __('Bank Transfer') }} </a>

                        </li>
                    @endif









                </ul>

                <div class="tab-content">
                    @if ($settings['bank_transfer_payment'] == 'on')
                        <div class="tab-pane fade active show" id="bank_transfer">
                            <div class="row">
                                <div class="col-sm-12">
                                    <div class=" profile-user-box">
                                        <form
                                            action="{{ route('invoice.banktransfer.payment', [encrypt($invoice->id)]) }}"
                                            method="post" class="require-validation" id="bank-payment"
                                            enctype="multipart/form-data">
                                            @csrf
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="card-name-on"
                                                            class="f-w-600 mb-1 text-start">{{ __('Bank Name') }}</label>
                                                        <p>{{ $settings['bank_name'] }}</p>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="card-name-on"
                                                            class="f-w-600 mb-1 text-start">{{ __('Bank Holder Name') }}</label>
                                                        <p>{{ $settings['bank_holder_name'] }}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="card-name-on"
                                                            class="f-w-600 mb-1 text-start">{{ __('Bank Account Number') }}</label>
                                                        <p>{{ $settings['bank_account_number'] }}
                                                        </p>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label for="card-name-on"
                                                            class="f-w-600 mb-1 text-start">{{ __('Bank IFSC Code') }}</label>
                                                        <p>{{ $settings['bank_ifsc_code'] }}
                                                        </p>
                                                    </div>
                                                </div>
                                                @if (!empty($settings['bank_other_details']))
                                                    <div class="col-md-12">
                                                        <div class="form-group">
                                                            <label for="card-name-on"
                                                                class="f-w-600 mb-1 text-start">{{ __('Bank Other Details') }}</label>
                                                            <p>{{ $settings['bank_other_details'] }}
                                                            </p>
                                                        </div>
                                                    </div>
                                                @endif
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <label for="amount"
                                                            class="form-label text-dark">{{ __('Amount') }}</label>
                                                        <input type="number" name="amount"
                                                            class="form-control required" step="0.01"
                                                        value="{{ $invoice->getInvoiceTotalDueAmount() }}"
                                                            placeholder="{{ __('Enter Amount') }}" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <label for="card-name-on"
                                                            class="form-label text-dark">{{ __('Attachment') }}</label>
                                                        <input type="file" name="receipt" id="receipt"
                                                            class="form-control" required>
                                                    </div>
                                                </div>
                                                <div class="col-md-12">
                                                    <div class="form-group">
                                                        <label for="notes"
                                                            class="form-label text-dark">{{ __('Notes') }}</label>
                                                        <input type="text" name="notes" class="form-control "
                                                            value="" placeholder="{{ __('Enter notes') }}">
                                                    </div>
                                                </div>
                                                <div class="col-sm-12 ">
                                                    <input type="submit" value="{{ __('Pay') }}"
                                                        class="btn btn-secondary">
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif












                </div>
            </div>
        </div>
    </div>

    @push('script-page')
        <script>
            $(document).on('click', '.print', function() {
                $('.action').addClass('d-none');
                var printContents = document.getElementById('invoice-print').innerHTML;
                var originalContents = document.body.innerHTML;
                document.body.innerHTML = printContents;
                window.print();
                document.body.innerHTML = originalContents;
                $('.action').removeClass('d-none');
            });
        </script>
    @endpush
