<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{

    public function index()
    {
        if (\Auth::user()->can('manage unit')) {
            $units = Unit::where('parent_id', '=', parentId())->orderBy('id', 'desc')->get();
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        return view('unit.index', compact('units'));
    }


    public function create()
    {
        return view('unit.create');
    }


    public function store(Request $request)
    {
        if (\Auth::user()->can('create unit')) {
            $validator = \Validator::make(
                $request->all(), [
                    'unit' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }
            $unit = new Unit();
            $unit->unit = $request->unit;
            $unit->parent_id = parentId();
            $unit->save();
            return redirect()->route('unit.index')->with('success', __('Unit successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function show(Unit $unit)
    {
        //
    }


    public function edit(Unit $unit)
    {
        return view('unit.edit', compact('unit'));
    }


    public function update(Request $request, Unit $unit)
    {
        if (\Auth::user()->can('edit unit')) {
            $validator = \Validator::make(
                $request->all(), [
                    'unit' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $unit->unit = $request->unit;
            $unit->save();
            return redirect()->route('unit.index')->with('success', __('Unit successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function destroy(Unit $unit)
    {
        if (\Auth::user()->can('delete unit')) {
            $unit->delete();
            return redirect()->route('unit.index')->with('success', __('Unit successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
