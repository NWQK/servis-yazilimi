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
            $subscriptions = Subscription::get();

            return view('subscription.index', compact('subscriptions'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    public function create()
    {
        $intervals = Subscription::intervals();

        return view('subscription.create', compact('intervals'));
    }


    public function store(Request $request)
    {
        if (\Auth::user()->can('create pricing packages')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'title' => 'required|unique:subscription,title',
                    'package_amount' => 'required',
                    'interval' => 'required',
                    'user_limit' => 'required',
                    'client_limit' => 'required',
                    'employee_limit' => 'required',
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
            $subscription->user_limit = $request->user_limit;
            $subscription->client_limit = $request->client_limit;
            $subscription->employee_limit = $request->employee_limit;
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
        $intervals = Subscription::intervals();

        return view('subscription.edit', compact('intervals', 'subscription'));
    }


    public function update(Request $request, subscription $subscription)
    {

        if (\Auth::user()->can('edit pricing packages')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'title' => 'required|unique:subscriptions,title,' . $subscription->id,
                    'package_amount' => 'required',
                    'interval' => 'required',
                    'user_limit' => 'required',
                    'client_limit' => 'required',
                    'employee_limit' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $subscription->title = $request->title;
            $subscription->interval = $request->interval;
            $subscription->package_amount = $request->package_amount;
            $subscription->user_limit = $request->user_limit;
            $subscription->client_limit = $request->client_limit;
            $subscription->employee_limit = $request->employee_limit;
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
