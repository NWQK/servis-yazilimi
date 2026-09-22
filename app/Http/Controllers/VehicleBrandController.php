<?php

namespace App\Http\Controllers;

use App\Models\VehicleBrand;
use App\Models\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleBrandController extends Controller
{

    public function index()
    {
        if (\Auth::user()->can('manage vehicle brand')) {
            $brands = VehicleBrand::where('parent_id', '=', parentId())->orderBy('id', 'desc')->get();
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        return view('vehicle_brand.index', compact('brands'));
    }


    public function create()
    {
        $types = VehicleType::where('parent_id', parentId())->get()->pluck('type','id');
        $types->prepend(__('Select Brand'),'');
        return view('vehicle_brand.create', compact('types'));
    }


    public function store(Request $request)
    {
        if (\Auth::user()->can('create vehicle brand')) {
            $validator = \Validator::make(
                $request->all(), [
                    'name' => 'required',
                    'type' => ['required', Rule::exists('vehicle_types', 'id')->where('parent_id', parentId())],
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }
            $vehicleBrand = new VehicleBrand();
            $vehicleBrand->name = $request->name;
            $vehicleBrand->type = $request->type;
            $vehicleBrand->parent_id = parentId();
            $vehicleBrand->save();
            return redirect()->route('vehicle-brand.index')->with('success', __('Vehicle model successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function show(VehicleBrand $vehicleBrand)
    {
        //
    }


    public function edit(VehicleBrand $vehicleBrand)
    {
        $types = VehicleType::where('parent_id', parentId())->get()->pluck('type','id');
        $types->prepend(__('Select Brand'),'');
        return view('vehicle_brand.edit', compact('types','vehicleBrand'));
    }


    public function update(Request $request, VehicleBrand $vehicleBrand)
    {
        if (\Auth::user()->can('edit vehicle brand')) {
            $validator = \Validator::make(
                $request->all(), [
                    'name' => 'required',
                    'type' => ['required', Rule::exists('vehicle_types', 'id')->where('parent_id', parentId())],
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $vehicleBrand->name = $request->name;
            $vehicleBrand->type = $request->type;
            $vehicleBrand->save();
            return redirect()->route('vehicle-brand.index')->with('success', __('Vehicle model successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function destroy(VehicleBrand $vehicleBrand)
    {
        if (\Auth::user()->can('delete vehicle brand') ) {
            $vehicleBrand->delete();
            return redirect()->route('vehicle-brand.index')->with('success', __('Vehicle model successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
