<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Tax;
use App\Models\Unit;
use Illuminate\Http\Request;
use App\Services\InventoryAccounting;

class ItemController extends Controller
{

    public function index()
    {
        if (\Auth::user()->can('manage item')) {
            $items = Item::where('parent_id', '=', parentId())->orderBy('id', 'desc')->get();
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        return view('item.index', compact('items'));
    }

    public function create()
    {
        $units = Unit::where('parent_id', parentId())->get()->pluck('unit', 'id');
        $taxs = Tax::where('parent_id', parentId())->get()->pluck('title', 'id');

        return view('item.create', compact('units', 'taxs'));
    }

    public function store(Request $request)
    {
        if (\Auth::user()->can('create item')) {
            $validator = \Validator::make(
                $request->all(), [
                    'title' => 'required',
                    'item_code' => 'required',
                    'quantity' => 'required|integer|min:0|max:100000000',
                    'units' => 'required',
                    'purchase_price' => 'required|numeric|min:0|max:9999999.99',
                    'sales_price' => 'required|numeric|min:0|max:9999999.99',
                    'purchase_date' => 'required|date',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $attributes = $request->only((new Item())->getFillable());
            $attributes['taxs'] = !empty($request->taxs) ? implode(',', $request->taxs) : '';
            app(InventoryAccounting::class)->savePurchase(parentId(), $attributes);

            return redirect()->route('item.index')->with('success', __('Item successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function show(Item $item)
    {
        return view('item.show', compact('item'));
    }

    public function edit(Item $item)
    {
        $units = Unit::where('parent_id', parentId())->get()->pluck('unit', 'id');
        $taxs = Tax::where('parent_id', parentId())->get()->pluck('title', 'id');
        return view('item.edit', compact('units', 'taxs', 'item'));
    }

    public function update(Request $request, Item $item)
    {
        if (\Auth::user()->can('edit item')) {
            $validator = \Validator::make(
                $request->all(), [
                    'title' => 'required',
                    'item_code' => 'required',
                    'quantity' => 'required|integer|min:0|max:100000000',
                    'units' => 'required',
                    'purchase_price' => 'required|numeric|min:0|max:9999999.99',
                    'sales_price' => 'required|numeric|min:0|max:9999999.99',
                    'purchase_date' => 'required|date',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }
            $attributes = $request->only((new Item())->getFillable());
            $attributes['taxs'] = !empty($request->taxs) ? implode(',', $request->taxs) : '';
            app(InventoryAccounting::class)->savePurchase(parentId(), $attributes, $item->id);
            return redirect()->route('item.index')->with('success', __('Item successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function destroy(Item $item)
    {
        if (\Auth::user()->can('delete item')) {
            app(InventoryAccounting::class)->transaction(parentId(), function () use ($item) {
                Item::where('parent_id', parentId())->lockForUpdate()->findOrFail($item->id)->delete();
            });
            return redirect()->route('item.index')->with('success', __('Item successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
