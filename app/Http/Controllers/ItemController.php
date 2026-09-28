<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\ItemCategory;
use Illuminate\Validation\Rule;
use App\Models\Tax;
use App\Models\Unit;
use Illuminate\Http\Request;
use App\Services\InventoryAccounting;

class ItemController extends Controller
{
    public function sale(int $id)
    {
        abort_unless(auth()->user()->can('edit item') && auth()->user()->can('create invoice payment'), 403);
        $item = Item::where('parent_id', parentId())->findOrFail($id);
        return view('item.sale', ['item'=>$item, 'requestKey'=>(string)\Illuminate\Support\Str::uuid()]);
    }

    public function sell(Request $request, int $id, InventoryAccounting $accounting)
    {
        abort_unless(auth()->user()->can('edit item') && auth()->user()->can('create invoice payment'), 403);
        $data = $request->validate(['quantity'=>'required|integer|min:1|max:100000000', 'request_key'=>'required|uuid', 'unit_price'=>'required|numeric|min:0|max:9999999.99']);
        $sale = $accounting->counterSale(parentId(), auth()->id(), $id, (int)$data['quantity'], $data['request_key'], $data['unit_price']);
        return redirect()->route('item.index')->with('success', $sale->quantity.' adet '.$sale->item_title.' satışı kaydedildi. Tahsilat: '.priceFormat($sale->amount));
    }

    public function index(Request $request)
    {
        if (\Auth::user()->can('manage item')) {
            $query = Item::where('parent_id', parentId())->with(['category', 'unit']);
            if ($request->input('category') === 'uncategorized') $query->whereNull('category_id');
            elseif ($request->filled('category')) $query->where('category_id', $request->input('category'));
            $items = $query->orderByDesc('id')->get();
            $categories = ItemCategory::where('parent_id', parentId())->orderBy('name')->pluck('name', 'id');
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        return view('item.index', compact('items', 'categories'));
    }

    public function create()
    {
        $categories = ItemCategory::where('parent_id', parentId())->orderBy('name')->pluck('name', 'id')->prepend('Kategorisiz', '');
        $units = Unit::where('parent_id', parentId())->get()->pluck('unit', 'id');
        $taxs = Tax::where('parent_id', parentId())->get()->pluck('title', 'id');

        return view('item.create', compact('units', 'taxs', 'categories'));
    }

    public function store(Request $request)
    {
        if (\Auth::user()->can('create item')) {
            $validator = \Validator::make(
                $request->all(), [
                    'title' => 'required',
                    'category_id' => ['nullable', 'integer', Rule::exists('item_categories', 'id')->where('parent_id', parentId())],
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
        abort_unless(auth()->user()->can('show item'), 403);
        abort_unless((int) $item->parent_id === (int) parentId(), 404);
        return view('item.show', compact('item'));
    }

    public function edit(Item $item)
    {
        abort_unless(auth()->user()->can('edit item'), 403);
        abort_unless((int) $item->parent_id === (int) parentId(), 404);
        $categories = ItemCategory::where('parent_id', parentId())->orderBy('name')->pluck('name', 'id')->prepend('Kategorisiz', '');
        $units = Unit::where('parent_id', parentId())->get()->pluck('unit', 'id');
        $taxs = Tax::where('parent_id', parentId())->get()->pluck('title', 'id');
        return view('item.edit', compact('units', 'taxs', 'item', 'categories'));
    }

    public function update(Request $request, Item $item)
    {
        if (\Auth::user()->can('edit item')) {
            $validator = \Validator::make(
                $request->all(), [
                    'title' => 'required',
                    'category_id' => ['nullable', 'integer', Rule::exists('item_categories', 'id')->where('parent_id', parentId())],
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
