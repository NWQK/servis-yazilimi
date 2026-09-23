<?php

namespace App\Http\Controllers;

use App\Models\ItemCategory;
use App\Services\InventoryAccounting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemCategoryController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->can('manage item'), 403);
        $categories = ItemCategory::where('parent_id', parentId())->withCount('items')->orderBy('name')->get();
        return view('item_category.index', compact('categories'));
    }

    public function create()
    {
        abort_unless(auth()->user()->can('create item'), 403);
        return view('item_category.form', ['category' => new ItemCategory()]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->can('create item'), 403);
        return $this->save($request, new ItemCategory());
    }

    public function edit(ItemCategory $itemCategory)
    {
        abort_unless(auth()->user()->can('edit item'), 403);
        abort_unless((int) $itemCategory->parent_id === (int) parentId(), 404);
        return view('item_category.form', ['category' => $itemCategory]);
    }

    public function update(Request $request, ItemCategory $itemCategory)
    {
        abort_unless(auth()->user()->can('edit item'), 403);
        abort_unless((int) $itemCategory->parent_id === (int) parentId(), 404);
        return $this->save($request, $itemCategory);
    }

    private function save(Request $request, ItemCategory $category)
    {
        return app(InventoryAccounting::class)->transaction(parentId(), function () use ($request, $category) {
            $request->merge(['name' => trim((string) $request->input('name'))]);
            $data = $request->validate(['name' => ['required', 'string', 'max:150',
                Rule::unique('item_categories')->where('parent_id', parentId())->ignore($category->id)]]);
            $category->fill($data);
            $category->parent_id = parentId();
            $category->save();
            return redirect()->route('item-category.index')->with('success', 'Ürün kategorisi kaydedildi.');
        });
    }

    public function destroy(ItemCategory $itemCategory)
    {
        abort_unless(auth()->user()->can('delete item'), 403);
        abort_unless((int) $itemCategory->parent_id === (int) parentId(), 404);
        return app(InventoryAccounting::class)->transaction(parentId(), function () use ($itemCategory) {
            if ($itemCategory->items()->exists()) {
                return back()->with('error', 'Bu kategoride ürünler var. Silmeden önce ürünleri başka kategoriye taşıyın.');
            }
            $itemCategory->delete();
            return redirect()->route('item-category.index')->with('success', 'Ürün kategorisi silindi.');
        });
    }
}
