<?php

namespace App\Http\Controllers;

use App\Models\{ItemCategory, Tax, Unit};
use App\Services\DefaultInventoryCatalog;
use Illuminate\Http\Request;

class InventorySetupController extends Controller
{
    public function index()
    {
        abort_unless(auth()->user()->type === 'owner' && auth()->user()->can('manage item'), 403);
        $taxCount = Tax::where('parent_id', auth()->id())->count();
        $unitCount = Unit::where('parent_id', auth()->id())->count();
        $categoryCount = ItemCategory::where('parent_id', auth()->id())->count();
        return view('item.setup', compact('taxCount', 'unitCount', 'categoryCount'));
    }

    public function defaults(Request $request, DefaultInventoryCatalog $catalog)
    {
        abort_unless(auth()->user()->type === 'owner' && auth()->user()->can('manage item') &&
            auth()->user()->can('create tax') && auth()->user()->can('create unit'), 403);
        $request->validate(['confirm' => 'accepted']);
        $catalog->seed(auth()->id());
        return redirect()->route('inventory.setup')->with('success', 'Hazır vergi ve birim ayarları eklendi. Mevcut ayarlarınız korundu.');
    }
}
