<?php

namespace App\Http\Controllers;

use App\Models\VehicleType;
use Illuminate\Http\Request;

class VehicleTypeController extends Controller
{

    public function index()
    {
        if (\Auth::user()->can('manage vehicle type')) {
            $types = VehicleType::where('parent_id', '=', parentId())->orderBy('id', 'desc')->get();
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        return view('vehicle_type.index', compact('types'));
    }


    public function create()
    {
          return view('vehicle_type.create');
    }


    public function store(Request $request)
    {
        if (\Auth::user()->can('create vehicle type')) {
            $validator = \Validator::make(
                $request->all(), [
                    'type' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }
            $vehicleType = new VehicleType();
            $vehicleType->type = $request->type;
            $vehicleType->parent_id = parentId();
            $vehicleType->save();
            return redirect()->route('vehicle-type.index')->with('success', __('Vehicle type successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function show(VehicleType $vehicleType)
    {
        //
    }


    public function edit(VehicleType $vehicleType)
    {
        return view('vehicle_type.edit',compact('vehicleType'));
    }


    public function update(Request $request, VehicleType $vehicleType)
    {
        if (\Auth::user()->can('edit vehicle type')) {
            $validator = \Validator::make(
                $request->all(), [
                    'type' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }
            $vehicleType->type = $request->type;
            $vehicleType->save();
            return redirect()->route('vehicle-type.index')->with('success', __('Vehicle type successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function destroy(VehicleType $vehicleType)
    {
        if (\Auth::user()->can('delete vehicle type') ) {
            $vehicleType->delete();
            return redirect()->route('vehicle-type.index')->with('success', __('Vehicle type successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
