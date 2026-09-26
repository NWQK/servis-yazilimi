<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\CouponHistory;
use App\Models\PackageTransaction;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

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














}
