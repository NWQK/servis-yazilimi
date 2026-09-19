<?php

namespace App\Http\Controllers;

use App\Models\ServiceType;
use App\Models\Tax;
use Illuminate\Http\Request;

class ServiceTypeController extends Controller
{

    public function index()
    {
        if (\Auth::user()->can('manage service type')) {
            $types = ServiceType::where('parent_id', '=', parentId())->orderBy('id', 'desc')->get();
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        return view('service_type.index', compact('types'));
    }


    public function create()
    {
        $taxes = Tax::where('parent_id', parentId())
            ->get()
            ->mapWithKeys(function ($tax) {
                return [$tax->id => $tax->title . ' (' . $tax->rate . '%)'];
            });
        return view('service_type.create', compact('taxes'));
    }


    public function store(Request $request)
    {
        if (\Auth::user()->can('create service type')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'type' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }
            $serviceType = new ServiceType();
            $serviceType->type = $request->type;
            $serviceType->rate = $request->rate;
            $serviceType->note = $request->note;
            $serviceType->tax = !empty($request->tax) ? implode(',', $request->tax) : '';
            $serviceType->parent_id = parentId();
            $serviceType->save();
            return redirect()->route('service-type.index')->with('success', __('Service type successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function show(ServiceType $serviceType)
    {
        //
    }


    public function edit($id)
    {
        $taxes = Tax::where('parent_id', parentId())
            ->get()
            ->mapWithKeys(function ($tax) {
                return [$tax->id => $tax->title . ' (' . $tax->rate . '%)'];
            });
        $serviceType = ServiceType::find($id);
        $tax = !empty($serviceType->tax) ? explode(',', $serviceType->tax) : '-';
        return view('service_type.edit', compact('serviceType', 'taxes', 'tax'));
    }


    public function update(Request $request, ServiceType $serviceType)
    {
        if (\Auth::user()->can('edit service type')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'type' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }
            $serviceType->type = $request->type;
            $serviceType->rate = $request->rate;
            $serviceType->tax = !empty($request->tax) ? implode(',', $request->tax) : '';
            $serviceType->note = $request->note;
            $serviceType->save();
            return redirect()->route('service-type.index')->with('success', __('Service type successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function destroy(ServiceType $serviceType)
    {
        if (\Auth::user()->can('delete service type')) {
            $serviceType->delete();
            return redirect()->route('service-type.index')->with('success', __('Service type successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
