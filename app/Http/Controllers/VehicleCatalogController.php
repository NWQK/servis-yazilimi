<?php
namespace App\Http\Controllers;

use App\Services\VehicleCatalog;
use App\Models\VehicleCatalogImport;
use Illuminate\Http\Request;

class VehicleCatalogController extends Controller
{
    public function create(VehicleCatalog $catalog)
    {
        $this->authorizeImport();
        $categories=VehicleCatalog::CATEGORIES;
        $loaded=VehicleCatalogImport::where('parent_id',parentId())->where('active',true)->pluck('category')->all();
        $counts=[];
        $selectedCategory = array_key_exists((string) request()->query('category'), $categories) ? request()->query('category') : null;
        foreach ($categories as $key=>$label) { $data=$catalog->dataset($key); $counts[$key]=['brands'=>count($data),'models'=>array_sum(array_map('count',$data))]; }
        return view('vehicle_type.catalog',compact('categories','loaded','counts','selectedCategory'));
    }

    public function store(Request $request, VehicleCatalog $catalog)
    {
        $this->authorizeImport();
        $data=$request->validate(['category'=>'required|in:'.implode(',',array_keys(VehicleCatalog::CATEGORIES)), 'confirm'=>'accepted']);
        $result=$catalog->import((int)parentId(),$data['category']);
        $message=$result['already_loaded'] ? 'Bu hazır liste zaten yüklü.' : $result['brands'].' marka ve '.$result['models'].' model eklendi. Mevcut kayıtlar korundu.';
        return redirect()->route('vehicle-type.index')->with('success',$message);
    }

    public function destroy(Request $request, int $import, VehicleCatalog $catalog)
    {
        abort_unless(auth()->user()->type !== 'client' && auth()->user()->can('delete vehicle type') && auth()->user()->can('delete vehicle brand'),403);
        $request->validate(['confirm'=>'accepted']);
        $result=$catalog->undo((int)parentId(),$import);
        $message=$result['brands'].' marka ve '.$result['models'].' model kaldırıldı.';
        if ($result['retained']) { $message.=' Araçlarda kullanılan, düzenlenen veya başka bir hazır listede bulunan '.$result['retained'].' kayıt korundu.'; }
        return redirect()->route('vehicle-type.index')->with('success',$message);
    }

    private function authorizeImport(): void
    {
        abort_unless(auth()->user()->type !== 'client' && auth()->user()->can('create vehicle type') && auth()->user()->can('create vehicle brand'),403);
    }
}
