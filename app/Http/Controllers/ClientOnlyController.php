<?php
namespace App\Http\Controllers;

use App\Models\{Client, Subscription, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class ClientOnlyController extends Controller
{
    public function create()
    {
        abort_unless(auth()->user()->type !== 'client' && auth()->user()->can('create client'),403);
        return view('client.simple',['customer'=>null,'profile'=>null]);
    }

    private function validated(Request $request, ?User $customer = null): array
    {
        return $request->validate([
            'name'=>'required|string|max:255', 'email'=>['nullable','email','max:255'],
            'phone_number'=>['required','string','regex:/\A5[0-9]{9}\z/'],
            'city'=>'nullable|string|max:255', 'state'=>'nullable|string|max:255',
            'zip_code'=>'nullable|string|max:255', 'address'=>'nullable|string|max:1000', 'notes'=>'nullable|string|max:5000',
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->type !== 'client' && auth()->user()->can('create client'),403);
        $data=$this->validated($request);
        app(\App\Services\ClientRegistration::class)->create((int) parentId(), $data);
        return redirect()->route('client.index')->with('success','Müşteri kaydı oluşturuldu.');
    }

    public function update(Request $request, int $id)
    {
        abort_unless(auth()->user()->type !== 'client' && auth()->user()->can('edit client'),403);
        $customer=User::where('parent_id',parentId())->where('type','client')->whereNull('client_archived_at')->findOrFail($id);
        $data=$this->validated($request,$customer);
        DB::transaction(function () use ($customer,$data) {
            $customer->update(['name'=>$data['name'],'email'=>$data['email'] ?? null,'phone_number'=>$data['phone_number']]);
            $customer->clients()->firstOrFail()->update(array_intersect_key($data,array_flip(['city','state','zip_code','address','notes'])));
        });
        return redirect()->route('client.index')->with('success','Müşteri bilgileri güncellendi.');
    }
}
