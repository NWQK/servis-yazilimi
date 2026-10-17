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
        abort_unless(auth()->user()->type === 'owner',403);
        try { $subscriptionId = \Illuminate\Support\Facades\Crypt::decrypt($id); }
        catch (\Illuminate\Contracts\Encryption\DecryptException $e) { abort(404); }
        abort_unless(is_scalar($subscriptionId) && ctype_digit((string)$subscriptionId),404);
        $subscription = Subscription::findOrFail($subscriptionId);
        $validator = \Validator::make(
            $request->all(),
            [
                'payment_receipt' => 'required|file|mimes:pdf,jpg,jpeg,png|max:8192',
                'name' => 'nullable|string|max:255',
                'coupon' => 'nullable|string|max:100',
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
        abort_unless(auth()->user()->type === 'super admin', 403);
        \App\Models\User::where('type','owner')->findOrFail($user_id);
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
        abort_unless(auth()->user()->type === 'super admin', 403);
        abort_unless(in_array($status, ['accept', 'reject'], true), 422);
        $initial = PackageTransaction::where('payment_type','Bank Transfer')->findOrFail($id);
        app(\App\Services\InventoryAccounting::class)->transaction((int)$initial->user_id, function () use ($id, $status) {
            $order = PackageTransaction::where('payment_type','Bank Transfer')->lockForUpdate()->findOrFail($id);
            \App\Models\User::where('type','owner')->findOrFail($order->user_id);
            $result = $status === 'accept' ? 'Success' : 'Reject';
            if ($order->payment_status === $result) return;
            abort_unless($order->payment_status === 'Pending', 409, 'Bu ödeme talebi daha önce sonuçlandırıldı.');
            if ($status === 'accept') {
                $subscription = Subscription::findOrFail($order->subscription_id);
                assignManuallySubscription($subscription->id, $order->user_id);
            } else {
                $couponHistory = CouponHistory::where('package', $order->subscription_id)->where('user_id', $order->user_id)->latest()->first();
                if ($couponHistory) $couponHistory->delete();
            }
            $order->payment_status = $result;
            $order->save();
            \App\Services\AdminAudit::record('subscription.payment_reviewed',$order->user_id,[],['transaction_id'=>$order->id,'result'=>$result]);
        });

        return redirect()
            ->back()
            ->with('success', __('Subscription payment status is ' . $status));
    }














}
