<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Tax;
use App\Models\Unit;
use Illuminate\Http\Request;

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
                    'quantity' => 'required',
                    'units' => 'required',
                    'purchase_price' => 'required',
                    'sales_price' => 'required',
                    'purchase_date' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $item = new Item();
            $item->title = $request->title;
            $item->item_code = $request->item_code;
            $item->quantity = $request->quantity;
            $item->units = $request->units;
            $item->purchase_price = $request->purchase_price;
            $item->sales_price = $request->sales_price;
            $item->manufacturer_by = $request->manufacturer_by;
            $item->taxs = !empty($request->taxs) ? implode(',', $request->taxs) : '';
            $item->purchase_date = $request->purchase_date;
            $item->warranty_information = $request->warranty_information;
            $item->notes = $request->notes;
            $item->parent_id = parentId();
            $item->save();

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
        if (\Auth::user()->can('create item')) {
            $validator = \Validator::make(
                $request->all(), [
                    'title' => 'required',
                    'item_code' => 'required',
                    'quantity' => 'required',
                    'units' => 'required',
                    'purchase_price' => 'required',
                    'sales_price' => 'required',
                    'purchase_date' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }
            $item->title = $request->title;
            $item->item_code = $request->item_code;
            $item->quantity = $request->quantity;
            $item->units = $request->units;
            $item->purchase_price = $request->purchase_price;
            $item->sales_price = $request->sales_price;
            $item->manufacturer_by = $request->manufacturer_by;
            $item->taxs = !empty($request->taxs) ? implode(',', $request->taxs) : '';
            $item->purchase_date = $request->purchase_date;
            $item->warranty_information = $request->warranty_information;
            $item->notes = $request->notes;
            $item->save();
            return redirect()->route('item.index')->with('success', __('Item successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function destroy(Item $item)
    {
        if (\Auth::user()->can('delete item')) {
            $item->delete();
            return redirect()->route('item.index')->with('success', __('Item successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
