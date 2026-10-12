<?php
namespace App\Http\Controllers;
use App\Models\{Supplier, Item};
use App\Services\InventoryAccounting;
use Illuminate\Http\Request;
class SupplierController extends Controller
{
    private function authorizeSupplier(string $permission, ?Supplier $supplier = null): void
    {
        abort_unless(auth()->user()->type !== 'client' && auth()->user()->can($permission), 403);
        if ($supplier) abort_unless((int)$supplier->parent_id === (int)parentId(), 404);
    }
    public function index()
    {
        $this->authorizeSupplier('manage item');
        return view('supplier.index', ['suppliers'=>Supplier::where('parent_id', parentId())->withCount('items')->orderBy('company_name')->get()]);
    }
    public function create()
    {
        $this->authorizeSupplier('create item');
        return view('supplier.form', ['supplier'=>new Supplier()]);
    }
    public function store(Request $request)
    {
        $this->authorizeSupplier('create item');
        return $this->save($request, new Supplier());
    }
    public function show(Supplier $supplier)
    {
        $this->authorizeSupplier('manage item', $supplier);
        return view('supplier.show', compact('supplier'));
    }
    public function edit(Supplier $supplier)
    {
        $this->authorizeSupplier('edit item', $supplier);
        return view('supplier.form', compact('supplier'));
    }
    public function update(Request $request, Supplier $supplier)
    {
        $this->authorizeSupplier('edit item', $supplier);
        return $this->save($request, $supplier);
    }
    private function save(Request $request, Supplier $supplier)
    {
        $data = $request->validate([
            'company_name'=>'required|string|max:150', 'contact_name'=>'nullable|string|max:150',
            'phone'=>['nullable','string','regex:/\A[2-5][0-9]{9}\z/'], 'email'=>'nullable|email|max:254',
        ], ['phone.regex'=>'Telefonu başında 0 veya +90 olmadan 10 hane olarak yazın.']);
        return app(InventoryAccounting::class)->transaction(parentId(), function () use ($data, $supplier) {
            $supplier->fill($data); $supplier->parent_id = parentId(); $supplier->save();
            return redirect()->route('supplier.index')->with('success', 'Tedarikçi kaydedildi.');
        });
    }
    public function destroy(Supplier $supplier)
    {
        $this->authorizeSupplier('delete item', $supplier);
        return app(InventoryAccounting::class)->transaction(parentId(), function () use ($supplier) {
            Item::where('parent_id', parentId())->where('supplier_id', $supplier->id)->update(['supplier_id'=>null]);
            $supplier->delete();
            return redirect()->route('supplier.index')->with('success', 'Tedarikçi silindi. Ürünler ve satın alım geçmişi korundu.');
        });
    }
}
