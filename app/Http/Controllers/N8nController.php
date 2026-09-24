<?php

namespace App\Http\Controllers;

use App\Models\N8n;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class N8nController extends Controller
{

    public function index()
    {
        $user = auth()->user();

        if (!$user->can('manage n8n')) {
            return back()->with('error', __('Permission denied'));
        }

        $parentId = parentId();

        $authUser = \App\Models\User::select('subscription')
            ->find($parentId);

        if (!$authUser) {
            return back()->with('error', __('User not found'));
        }

        $subscription = \App\Models\Subscription::find($authUser->subscription);
        $pricingFeature = getSettingsValByIdName(1, 'pricing_feature');

        $canViewN8n =
            $user->type !== 'super admin' &&
           ($pricingFeature !== 'off' ||
            optional($subscription)->enabled_n8n == 1);

        if (!$canViewN8n) {
            return back()->with('error', __('Permission denied'));
        }

        $n8ns = N8n::where('parent_id', $parentId)->orderBy('id', 'desc')->get();
        return view('n8n.index', compact('n8ns'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!Auth::user()->can('create n8n')) {
            return redirect()->back()->with('error', __('Permission denied'));
        }
        $method = N8n::method();
        $modules = N8n::module();
        return view('n8n.create', compact('method', 'modules'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!Auth::user()->can('create n8n')) {
            return redirect()->back()->with('error', __('Permission denied'));
        }
        $exists = N8n::where('module', $request->module)
            ->where('parent_id', parentId())
            ->exists();

        if ($exists) {
            return redirect()->back()->with('error', __('This module already exists.'));
        }
        $n8n = new N8n();
        $n8n->module = $request->module;
        $n8n->method = $request->method;
        $n8n->url = $request->url;
        $n8n->status = !empty($request->status) ? $request->status : 0;
        $n8n->parent_id = parentId();
        $n8n->save();

        return redirect()->back()->with('success', __('N8n is created successfully'));
    }

    /**
     * Display the specified resource.
     */
    public function show(N8n $N8n)
    {
        //
    }


    public function edit(N8n $n8n)
    {
        if (!Auth::user()->can('edit n8n')) {
            return redirect()->back()->with('error', __('Permission denied'));
        }
        $method = N8n::method();
        $modules = N8n::module();
        return view('n8n.edit', compact('n8n', 'method', 'modules'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, N8n $n8n)
    {
        if (!Auth::user()->can('edit n8n')) {
            return redirect()->back()->with('error', __('Permission denied'));
        }
        $n8n->module = $request->module;
        $n8n->method = $request->method;
        $n8n->url = $request->url;
        $n8n->parent_id = parentId();
        $n8n->status = !empty($request->status) ? $request->status : 0;
        $n8n->save();

        return redirect()->back()->with('success', __('N8n is updated successfully'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(N8n $n8n)
    {
        if (!Auth::user()->can('delete n8n')) {
            return redirect()->back()->with('error', __('Permission denied'));
        }

        $n8n->delete();
        return redirect()->back()->with('success', __('N8n is deleted successfully'));
    }
}
