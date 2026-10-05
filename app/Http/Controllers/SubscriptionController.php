<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\CouponHistory;
use App\Models\PackageTransaction;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class SubscriptionController extends Controller
{

    public function index()
    {
        if (\Auth::user()->can('manage pricing packages')) {
            $subscriptions = Subscription::orderBy('vehicle_limit')->get();
            $capacity = auth()->user()->type === 'owner' ? app(\App\Services\VehicleCapacity::class)->summary(auth()->user()) : null;

            return view('subscription.index', compact('subscriptions', 'capacity'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    public function create()
    {
        abort_unless(auth()->user()->type === 'super admin' && auth()->user()->can('create pricing packages'), 403);
        $intervals = Subscription::intervals();

        return view('subscription.create', compact('intervals'));
    }


    public function store(Request $request)
    {
        abort_unless(auth()->user()->type === 'super admin', 403);
        if (\Auth::user()->can('create pricing packages')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'title' => 'required|string|max:150|unique:subscriptions,title',
                    'package_amount' => 'required|numeric|min:0|max:9999999999.99',
                    'interval' => 'required|in:Monthly,Quarterly,Yearly,Unlimited',
                    'vehicle_limit' => 'required|integer|in:50,500,1000,2000,3000|unique:subscriptions,vehicle_limit',
                    'vehicle_block_amount' => 'nullable|numeric|min:0.01|max:9999999999.99',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $subscription = new Subscription();
            $subscription->title = $request->title;
            $subscription->interval = $request->interval;
            $subscription->package_amount = $request->package_amount;
            $subscription->user_limit = 0;
            $subscription->client_limit = 0;
            $subscription->employee_limit = 0;
            $subscription->vehicle_limit = $request->vehicle_limit;
            $subscription->vehicle_block_amount = (int) $request->vehicle_limit === 3000 ? $request->vehicle_block_amount : null;
            $subscription->enabled_logged_history = isset($request->enabled_logged_history) ? 1 : 0;
            $subscription->enabled_openai = isset($request->enabled_openai) ? 1 : 0;
            $subscription->enabled_n8n = isset($request->enabled_n8n) ? 1 : 0;
            $subscription->save();

            return redirect()->route('subscriptions.index')->with('success', __('Subscription successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    public function show($ids)
    {
        if (\Auth::user()->can('buy pricing packages')) {
            $id = Crypt::decrypt($ids);
            $subscription = Subscription::find($id);
            $settings = subscriptionPaymentSettings();

            return view('subscription.show', compact('subscription', 'settings'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    public function edit(subscription $subscription)
    {
        abort_unless(auth()->user()->type === 'super admin' && auth()->user()->can('edit pricing packages'), 403);
        $intervals = Subscription::intervals();

        return view('subscription.edit', compact('intervals', 'subscription'));
    }


    public function update(Request $request, subscription $subscription)
    {
        abort_unless(auth()->user()->type === 'super admin', 403);
        if (\Auth::user()->can('edit pricing packages')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'title' => 'required|string|max:150|unique:subscriptions,title,' . $subscription->id,
                    'package_amount' => 'required|numeric|min:0|max:9999999999.99',
                    'interval' => 'required|in:Monthly,Quarterly,Yearly,Unlimited',
                    'vehicle_limit' => ['nullable', 'integer', 'in:50,500,1000,2000,3000', \Illuminate\Validation\Rule::unique('subscriptions', 'vehicle_limit')->ignore($subscription->id)],
                    'vehicle_block_amount' => 'nullable|numeric|min:0.01|max:9999999999.99',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $subscription->title = $request->title;
            $subscription->interval = $request->interval;
            $subscription->package_amount = $request->package_amount;
            if ($request->filled('vehicle_limit')) {
                $subscription->vehicle_limit = $request->vehicle_limit;
                $subscription->user_limit = $subscription->client_limit = $subscription->employee_limit = 0;
            }
            $subscription->vehicle_block_amount = (int) $subscription->vehicle_limit === 3000 ? $request->vehicle_block_amount : null;
            $subscription->enabled_logged_history = isset($request->enabled_logged_history) ? 1 : 0;
            $subscription->enabled_openai = isset($request->enabled_openai) ? 1 : 0;
            $subscription->enabled_n8n = isset($request->enabled_n8n) ? 1 : 0;
            $subscription->save();

            return redirect()->route('subscriptions.index')->with('success', __('Subscription successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    public function destroy(subscription $subscription)
    {
        abort_unless(auth()->user()->type === 'super admin', 403);
        if (\App\Models\User::where('subscription', $subscription->id)->exists() || $subscription->vehicle_limit !== null) {
            return back()->with('error', 'Kullanılan veya standart araç paketleri silinemez. Ücret ve özelliklerini düzenleyebilirsiniz.');
        }
        if (\Auth::user()->can('delete pricing packages')) {
            $subscription->delete();

            return redirect()->route('subscriptions.index')->with('success', __('Subscription successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function transaction()
    {
        if (\Auth::user()->can('manage pricing transation')) {
            if (\Auth::user()->type == 'super admin') {
                $transactions = PackageTransaction::orderBy('created_at', 'DESC')->get();
            } else {
                $transactions = PackageTransaction::where('user_id', \Auth::user()->id)->orderBy('created_at', 'DESC')->get();
            }
            $settings = settings();
            return view('subscription.transaction', compact('transactions', 'settings'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


}
