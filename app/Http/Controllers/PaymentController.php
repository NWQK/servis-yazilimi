<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\CouponHistory;
use App\Models\PackageTransaction;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Srmklive\PayPal\Services\PayPal as PayPalClient;

class PaymentController extends Controller
{
    public function paymentSettings()
    {
        $paymentSetting = subscriptionPaymentSettings();
        return $paymentSetting;
    }

    public function subscriptionBankTransfer(Request $request, $id)
    {
        $subscriptionId = \Illuminate\Support\Facades\Crypt::decrypt($id);
        $validator = \Validator::make(
            $request->all(),
            [
                'payment_receipt' => 'required',
            ]
        );
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }

        if (!empty($request->payment_receipt)) {
            $recieptFilenameWithExt = $request->file('payment_receipt')->getClientOriginalName();
            $recieptFilename = pathinfo($recieptFilenameWithExt, PATHINFO_FILENAME);
            $recieptExtension = $request->file('payment_receipt')->getClientOriginalExtension();
            $recieptFileName = $recieptFilename . '_' . time() . '.' . $recieptExtension;

            $dir = storage_path('upload/payment_receipt');
            $image_path = $dir . $recieptFilenameWithExt;


            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
            $request->file('payment_receipt')->storeAs('upload/payment_receipt/', $recieptFileName);
            $data['receipt_url'] = $recieptFileName;
        }

        $coupon = $request->coupon;
        $subscription = Subscription::find($subscriptionId);

        $amount = Coupon::couponApply($subscriptionId, $coupon);
        $packageTransId = uniqid('', true);

        $data['holder_name'] = $request->name;
        $data['subscription_id'] = $subscription->id;
        $data['amount'] = $amount;
        $data['subscription_transactions_id'] = $packageTransId;
        $data['payment_type'] = 'Bank Transfer';
        $data['status'] = 'Pending';
        PackageTransaction::transactionData($data);

        if ($subscription->couponCheck() > 0 && !empty($request->coupon)) {
            $couhis['coupon'] = $request->coupon;
            $couhis['package'] = $subscription->id;
            CouponHistory::couponData($couhis);
        }
        return redirect()
            ->back()
            ->with('success', __('Subscription payment successfully completed.'));
    }

    public function subscriptionManualAssignPackage(Request $request, $id, $user_id)
    {
        $subscriptionId = \Illuminate\Support\Facades\Crypt::decrypt($id);

        $coupon = $request->coupon;
        $subscription = Subscription::find($subscriptionId);

        $amount = Coupon::couponApply($subscriptionId, $coupon);
        $packageTransId = uniqid('', true);

        $data['user_id'] = $user_id;
        $data['holder_name'] = $request->name;
        $data['subscription_id'] = $subscription->id;
        $data['amount'] = $amount;
        $data['subscription_transactions_id'] = $packageTransId;
        $data['payment_type'] = 'Manually Assign By Super admin';
        $data['status'] = 'Success';
        $data['receipt_url'] = '';
        $order = PackageTransaction::transactionData($data);
        $assignPlan = assignManuallySubscription($subscriptionId, $user_id);

        return redirect()
            ->back()
            ->with('success', __('Subscription payment successfully completed.'));
    }

    public function subscriptionBankTransferAction($id, $status)
    {
        $order = PackageTransaction::find($id);
        if ($status == 'accept') {
            $subscription = Subscription::find($order->subscription_id);
            $assignPlan = assignManuallySubscription($subscription->id, $order->user_id);
            if (!empty($order)) {
                $order->payment_status = 'Success';
                $order->save();
            }
        } else {

            $order->payment_status = 'Reject';
            $order->save();

            $couponHistory = CouponHistory::where('package', $id)->where('user_id', $order->user_id)->latest()->first();
            if (!empty($couponHistory)) {
                $couponHistory->delete();
            }
        }

        return redirect()
            ->back()
            ->with('success', __('Subscription payment status is ' . $status));
    }
    public function subscriptionPaypal(Request $request, $id)
    {

        $subscriptionId = \Illuminate\Support\Facades\Crypt::decrypt($id);
        $price = Coupon::couponApply($subscriptionId, $request->coupon);
        $paypalSetting = $this->paymentSettings();

        if ($paypalSetting['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => isset($paypalSetting['paypal_client_id']) ? $paypalSetting['paypal_client_id'] : '',
                'paypal.live.client_secret' => isset($paypalSetting['paypal_secret_key']) ? $paypalSetting['paypal_secret_key'] : '',
                'paypal.mode' => isset($paypalSetting['paypal_mode']) ? $paypalSetting['paypal_mode'] : '',
                'paypal.currency' => isset($paypalSetting['CURRENCY']) ? $paypalSetting['CURRENCY'] : '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => isset($paypalSetting['paypal_client_id']) ? $paypalSetting['paypal_client_id'] : '',
                'paypal.sandbox.client_secret' => isset($paypalSetting['paypal_secret_key']) ? $paypalSetting['paypal_secret_key'] : '',
                'paypal.mode' => isset($paypalSetting['paypal_mode']) ? $paypalSetting['paypal_mode'] : '',
                'paypal.currency' => isset($paypalSetting['CURRENCY']) ? $paypalSetting['CURRENCY'] : '',
            ]);
        }

        try {
            $provider = new PayPalClient;
            $provider->setApiCredentials(config('paypal'));

            $token = $provider->getAccessToken();

            $response = $provider->createOrder([
                "intent" => "CAPTURE",
                "application_context" => [
                    "return_url" => route('subscription.paypal.status', [$subscriptionId, 'success'], ['coupon' => $request->coupon]),
                    "cancel_url" => route('subscription.paypal.status', [$subscriptionId, 'cancel'], ['coupon' => $request->coupon]),
                ],
                "purchase_units" => [
                    0 => [
                        "amount" => [
                            "currency_code" => isset($paypalSetting['CURRENCY']) ? $paypalSetting['CURRENCY'] : '',
                            "value" => $price
                        ]
                    ]
                ]
            ]);
            if (isset($response['id']) && $response['id'] != null) {
                // redirect to approve href
                foreach ($response['links'] as $links) {
                    if ($links['rel'] == 'approve') {
                        return redirect()->away($links['href']);
                    }
                }
                return redirect()
                    ->back()
                    ->with('error', __('Something went wrong.'));
            } else {
                return redirect()
                    ->back()
                    ->with('error', $response['message'] ?? 'Something went wrong.');
            }
        } catch (\Exception $exception) {
            return redirect()
                ->back()
                ->with('error', $exception->getMessage());
        }
    }

    public function subscriptionPaypalStatus(Request $request, $subscriptionId, $status)
    {
        if ($status == 'success') {
            $paypalSetting = $this->paymentSettings();

            if ($paypalSetting['paypal_mode'] == 'live') {
                config([
                    'paypal.live.client_id' => isset($paypalSetting['paypal_client_id']) ? $paypalSetting['paypal_client_id'] : '',
                    'paypal.live.client_secret' => isset($paypalSetting['paypal_secret_key']) ? $paypalSetting['paypal_secret_key'] : '',
                    'paypal.mode' => isset($paypalSetting['paypal_mode']) ? $paypalSetting['paypal_mode'] : '',
                    'paypal.currency' => isset($paypalSetting['CURRENCY']) ? $paypalSetting['CURRENCY'] : '',
                ]);
            } else {
                config([
                    'paypal.sandbox.client_id' => isset($paypalSetting['paypal_client_id']) ? $paypalSetting['paypal_client_id'] : '',
                    'paypal.sandbox.client_secret' => isset($paypalSetting['paypal_secret_key']) ? $paypalSetting['paypal_secret_key'] : '',
                    'paypal.mode' => isset($paypalSetting['paypal_mode']) ? $paypalSetting['paypal_mode'] : '',
                    'paypal.currency' => isset($paypalSetting['CURRENCY']) ? $paypalSetting['CURRENCY'] : '',
                ]);
            }

            $provider = new PayPalClient;
            $provider->setApiCredentials(config('paypal'));
            $provider->getAccessToken();
            $response = $provider->capturePaymentOrder($request['token']);
            if (isset($response['status']) && $response['status'] == 'COMPLETED') {
                $coupon = $request->coupon;
                $subscription = Subscription::find($subscriptionId);
                $amount = Coupon::couponApply($subscriptionId, $coupon);
                $packageTransId = uniqid('', true);

                $data['holder_name'] = $request->name;
                $data['subscription_id'] = $subscription->id;
                $data['amount'] = $amount;
                $data['subscription_transactions_id'] = $packageTransId;
                $data['payment_type'] = 'Paypal';
                PackageTransaction::transactionData($data);

                if ($subscription->couponCheck() > 0 && !empty($request->coupon)) {
                    $couhis['coupon'] = $request->coupon;
                    $couhis['package'] = $subscription->id;
                    CouponHistory::couponData($couhis);
                }

                assignSubscription($subscription->id);

                return redirect()
                    ->back()
                    ->with('success', __('Subscription payment successfully completed.'));
            } else {
                return redirect()
                    ->back()
                    ->with('error', $response['message'] ?? __('Something went wrong.'));
            }
        } else {
            return redirect()
                ->back()
                ->with('error', __('Transaction failed.'));
        }
    }

    public function subscriptionFlutterwave(Request $request, $subscription_id, $txRef)
    {
        $subscriptionId = Crypt::decrypt($subscription_id);
        $subscription = Subscription::find($subscriptionId);
        $this->subscriptionData = $subscription;

        $paymentSetting = $this->paymentSettings();
        $result    = array();

        $coupon = $request->coupon;
        $amount = Coupon::couponApply($subscriptionId, $coupon);
        if ($subscription) {
            try {
                $detail = array(
                    'txref' => $txRef,
                    'SECKEY' => $paymentSetting['flutterwave_secret_key'],
                );
                $URL = "https://api.ravepay.co/flwv3-pug/getpaidx/api/v2/verify";
                $headersData = array('Content-Type' => 'application/json');
                $bodyData = \Unirest\Request\Body::json($detail);

                $responseData = \Unirest\Request::post($URL, $headersData, $bodyData);
                if (!empty($responseData)) {
                    $responseData = json_decode($responseData->raw_body, true);
                }

                if (isset($responseData['status']) && $responseData['status'] == 'success') {
                    $packageTransId = uniqid('', true);
                    $data['holder_name'] = $request->name;
                    $data['subscription_id'] = $subscription->id;
                    $data['amount'] = $amount;
                    $data['subscription_transactions_id'] = $packageTransId;
                    $data['payment_type'] = 'Futterwave';
                    PackageTransaction::transactionData($data);

                    if ($subscription->couponCheck() > 0 && !empty($request->coupon)) {
                        $couhis['coupon'] = $request->coupon;
                        $couhis['package'] = $subscription->id;
                        CouponHistory::couponData($couhis);
                    }
                    assignSubscription($subscription->id);

                    return redirect()
                        ->back()
                        ->with('success', __('Subscription payment successfully completed.'));
                } else {
                    return redirect()
                        ->back()
                        ->with('error', $responseData['message'] ?? __('Something went wrong.'));
                }
            } catch (\Exception  $e) {
                return redirect()
                    ->back()
                    ->with('error', $e->getMessage());
            }
        }
    }

    public function subscriptionPaystack(Request $request)
    {
        $payment_setting = $this->paymentSettings();
        $currency   = $payment_setting['CURRENCY'] ?? 'off';

        $planID = Crypt::decrypt($request->plan_id);
        $plan   = Subscription::find($planID);
        $coupon = $request->coupon;

        if (!$plan) {
            return redirect()->route('subscriptions.index')->with('error', __('Subscription is deleted.'));
        }

        $price = Coupon::couponApply($planID, $coupon);

        if ($price <= 0) {
            $packageTransId = uniqid('', true);

            PackageTransaction::transactionData([
                'holder_name'                  => $request->name,
                'subscription_id'             => $planID,
                'amount'                       => $price,
                'subscription_transactions_id' => $packageTransId,
                'payment_type'                => 'Paystack',
            ]);

            if ($plan->couponCheck() > 0 && $coupon) {
                CouponHistory::couponData([
                    'coupon'  => $coupon,
                    'package' => $planID,
                ]);
            }

            assignSubscription($planID);

            return [
                'msg'  => __("Subscription successfully upgraded."),
                'flag' => 2,
            ];
        }

        return [
            'email'       => auth()->user()->email,
            'total_price' => $price,
            'currency'    => $currency,
            'flag'        => 1,
            'coupon'      => $coupon,
        ];
    }


    public function subscriptionPaystackStatus(Request $request, $pay_id, $plan)
    {
        $payment_setting = $this->paymentSettings();
        $secret_key = $payment_setting['paystack_secret_key'] ?? '';
        $planID     = Crypt::decrypt($plan);
        $subscription = Subscription::find($planID);

        if (!$subscription) {
            return redirect()->route('subscriptions.index')->with('error', __('Subscription is deleted.'));
        }

        try {
            $verifyUrl = "https://api.paystack.co/transaction/verify/$pay_id";
            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => $verifyUrl,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $secret_key,
                ],
            ]);

            $response = curl_exec($ch);
            curl_close($ch);

            $result = $response ? json_decode($response, true) : [];

            if (!($result['status'] ?? false)) {
                return redirect()->back()->with('error', __('Transaction Unsuccessful'));
            }

            $price           = Coupon::couponApply($planID, $request->coupon_id);
            $packageTransId  = uniqid('', true);

            PackageTransaction::transactionData([
                'holder_name'                  => $request->name,
                'subscription_id'             => $planID,
                'amount'                       => $price,
                'subscription_transactions_id' => $packageTransId,
                'payment_type'                => 'Paystack',
            ]);

            if ($subscription->couponCheck() > 0 && !empty($request->coupon)) {
                CouponHistory::couponData([
                    'coupon'  => $request->coupon,
                    'package' => $planID,
                ]);
            }

            $assignPlan = assignSubscription($planID);

            return $assignPlan['is_success']
                ? redirect()->route('subscriptions.index')->with('success', __('Subscription activated successfully.'))
                : redirect()->route('subscriptions.index')->with('error', $assignPlan['error']);
        } catch (\Exception $e) {
            return redirect()->route('subscriptions.index')->with('error', __('Transaction has failed.'));
        }
    }

    public function subscriptionPayWithRazorpay(Request $request)
        {
        $razorpaySetting = $this->paymentSettings();
        $planID = decrypt($request->plan_id);
        $plan = Subscription::find($planID);
        $authUser = \Auth::user();
        $coupon_id = '';
        if ($plan) {

            $price = Coupon::couponApply($planID, $request->coupon);
            $packageTransId = uniqid('', true);
            if (isset($request->coupon) && !empty($request->coupon)) {
                if ($plan->couponCheck() > 0 && !empty($request->coupon)) {
                    $couhis['coupon'] = $request->coupon;
                    $couhis['package'] = $plan->id;
                    CouponHistory::couponData($couhis);
                }
            }

            if ($price <= 0) {
                $data['holder_name'] = $request->name;
                $data['subscription_id'] = $plan->id;
                $data['amount'] = $price;
                $data['subscription_transactions_id'] = $packageTransId;
                $data['payment_type'] = 'Razorpay';
                PackageTransaction::transactionData($data);

                if ($plan->couponCheck() > 0 && !empty($request->coupon)) {
                    $couhis['coupon'] = $request->coupon;
                    $couhis['package'] = $plan->id;
                    CouponHistory::couponData($couhis);
                }

                $assignPlan = assignSubscription($plan->id);
                if ($assignPlan['is_success']) {
                    return redirect()->route('subscriptions.index')->with('success', __('Subscription activate successfully.'));
                } else {
                    return redirect()->route('subscriptions.index')->with('error', __($assignPlan['error']));
                }
            }

            $res_data['email'] = \Auth::user()->email;
            $res_data['total_price'] = $price;
            $res_data['currency'] = $razorpaySetting['CURRENCY'];
            $res_data['flag'] = 1;
            $res_data['coupon'] = $coupon_id;

            return $res_data;
        } else {
        }
    }

    public function getPaymentStatus(Request $request, $pay_id, $plan)
    {
        $payment = $this->paymentSettings();

        try {
            $planID = decrypt($plan);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Invalid plan!'));
        }

        $subscription = Subscription::find($planID);

        if (!$subscription) {
            return redirect()->back()->with('error', __('Plan not found!'));
        }

        try {
            $packageTransId = time();
            $amount = Coupon::couponApply($planID, $request->coupon);

            $ch = curl_init('https://api.razorpay.com/v1/payments/' . $pay_id);

            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_USERPWD, $payment['razorpay_public_key'] . ':' . $payment['razorpay_secret_key']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $paymentResponse = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                Log::error('Razorpay payment fetch error: ' . $curlError);
                return redirect()->back()->with('error', __('Payment verification failed!'));
            }

            $response = json_decode($paymentResponse);

            if (!$response || !isset($response->status)) {
                Log::error('Invalid Razorpay response: ' . $paymentResponse);
                return redirect()->back()->with('error', __('Invalid payment response!'));
            }

            if ($response->status == 'authorized' && empty($response->captured)) {

                $capture = curl_init('https://api.razorpay.com/v1/payments/' . $pay_id . '/capture');

                curl_setopt($capture, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($capture, CURLOPT_USERPWD, $payment['razorpay_public_key'] . ':' . $payment['razorpay_secret_key']);
                curl_setopt($capture, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($capture, CURLOPT_POSTFIELDS, http_build_query([
                    'amount'   => $response->amount,
                    'currency' => $response->currency ?? 'INR',
                ]));

                $captureResponse = curl_exec($capture);
                $captureError = curl_error($capture);
                curl_close($capture);

                if ($captureError) {
                    Log::error('Razorpay capture error: ' . $captureError);
                    return redirect()->back()->with('error', __('Payment authorized but capture failed!'));
                }

                $captureData = json_decode($captureResponse);

                if (!$captureData || !isset($captureData->status)) {
                    Log::error('Invalid Razorpay capture response: ' . $captureResponse);
                    return redirect()->back()->with('error', __('Invalid capture response!'));
                }

                if ($captureData->status != 'captured') {
                    Log::error('Razorpay payment not captured: ' . $captureResponse);
                    return redirect()->back()->with('error', __('Payment capture failed!'));
                }

                $response = $captureData;
            }

            if ($response->status == 'captured' && !empty($response->captured)) {

                $data = [];
                $data['holder_name'] = $request->name;
                $data['subscription_id'] = $subscription->id;
                $data['amount'] = $amount;
                $data['subscription_transactions_id'] = $packageTransId;
                $data['payment_type'] = 'Razorpay';

                if (isset($response->card) && isset($response->card->last4)) {
                    $data['payment_method_details']['card']['last4'] = $response->card->last4;
                } else {
                    $data['payment_method_details']['method'] = $response->method ?? 'unknown';

                    if (($response->method ?? '') == 'netbanking') {
                        $data['payment_method_details']['bank'] = $response->bank ?? null;
                    }

                    if (($response->method ?? '') == 'upi') {
                        $data['payment_method_details']['vpa'] = $response->vpa ?? null;
                    }

                    if (($response->method ?? '') == 'wallet') {
                        $data['payment_method_details']['wallet'] = $response->wallet ?? null;
                    }
                }

                $data['transaction_id'] = $response->id;

                PackageTransaction::transactionData($data);

                if ($subscription->couponCheck() > 0 && !empty($request->coupon)) {
                    $couhis = [];
                    $couhis['coupon'] = $request->coupon;
                    $couhis['package'] = $subscription->id;
                    CouponHistory::couponData($couhis);
                }

                $assignPlan = assignSubscription($subscription->id);

                if ($assignPlan['is_success']) {
                    return redirect()
                        ->route('subscriptions.index')
                        ->with('success', __('Plan activated Successfully!'));
                }

                return redirect()->back()->with('error', __($assignPlan['error']));
            }

            Log::error('Razorpay payment failed/not captured: ' . json_encode($response));

            return redirect()->back()->with('error', __('Transaction has been failed!'));
        } catch (\Exception $e) {
            Log::error('Razorpay getPaymentStatus error: ' . $e->getMessage());

            return redirect()->back()->with('error', __('Something went wrong!'));
        }
    }
}
