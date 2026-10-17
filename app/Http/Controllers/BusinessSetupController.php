<?php
namespace App\Http\Controllers;

use App\Models\{User, VehicleType, VehicleBrand, VehicleCatalogImport, Tax, Unit, ItemCategory, ServiceType};
use App\Services\{InvoiceBilling, DefaultInventoryCatalog, VehicleCatalog};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BusinessSetupController extends Controller
{
    public const STEPS = ['business'=>'İşletme bilgileri','logos'=>'Logolar','vehicles'=>'Marka ve modeller',
        'taxes'=>'Vergiler','units'=>'Ürün birimleri','categories'=>'Ürün kategorileri',
        'services'=>'Servis türleri','team'=>'Çalışanlar','ready'=>'Son kontrol'];

    private function owner(): int
    {
        abort_unless(isBusinessOwner(), 403);
        return (int) parentId();
    }

    public function index(Request $request, string $step = 'business')
    {
        $owner=$this->owner(); abort_unless(isset(self::STEPS[$step]),404);
        $steps=self::STEPS;
        $details=InvoiceBilling::business($owner);
        $logos=DB::table('settings')->where('parent_id',$owner)->whereIn('name',['company_logo','company_light_logo','company_favicon','invoice_logo'])->pluck('value','name');
        $brands=VehicleType::where('parent_id',$owner)->orderBy('type')->get();
        $modelCount=VehicleBrand::where('parent_id',$owner)->count();
        $imports=VehicleCatalogImport::where('parent_id',$owner)->where('active',true)->pluck('category')->all();
        $taxes=Tax::where('parent_id',$owner)->orderBy('title')->get();
        $units=Unit::where('parent_id',$owner)->orderBy('unit')->get();
        $categories=ItemCategory::where('parent_id',$owner)->orderBy('name')->get();
        $types=ServiceType::where('parent_id',$owner)->orderBy('type')->get();
        $employees=User::where('parent_id',$owner)->where('type','employee')->orderBy('name')->get();
        $complete=['business'=>collect(['name','phone','address'])->every(fn($key)=>trim($details[$key] ?? '')!==''),
            'logos'=>!empty($logos['invoice_logo']),'vehicles'=>$brands->isNotEmpty() && $modelCount>0,
            'taxes'=>$taxes->isNotEmpty(),'units'=>$units->isNotEmpty(),'categories'=>$categories->isNotEmpty(),
            'services'=>$types->isNotEmpty(),'team'=>$employees->isNotEmpty()];
        $keys=array_keys($steps);$position=array_search($step,$keys,true);
        $previous=$keys[$position-1] ?? null;$next=$keys[$position+1] ?? null;
        $gender=User::genderList();
        return response()->view('business_setup.index',compact('step','steps','details','logos','brands','modelCount','imports','taxes','units','categories','types','employees','complete','position','previous','next','gender'))->header('Cache-Control','private, no-store');
    }

    public function store(Request $request, string $step)
    {
        $owner=$this->owner();abort_unless(isset(self::STEPS[$step]),404);
        $actions=['business'=>['business'],'logos'=>['logos'],'vehicles'=>['catalog','brand','model'],
            'taxes'=>['defaults','tax'],'units'=>['defaults','unit'],'categories'=>['category'],
            'services'=>['service'],'team'=>['employee'],'ready'=>[]];
        $action=$request->validate(['action'=>['required',Rule::in($actions[$step])]])['action'];
        $permissions=['business'=>['manage company settings'],'logos'=>['manage general settings'],
            'catalog'=>['create vehicle type','create vehicle brand'],'brand'=>['create vehicle type'],'model'=>['create vehicle brand'],
            'defaults'=>['create tax','create unit'],'tax'=>['create tax'],'unit'=>['create unit'],
            'category'=>['create item'],'service'=>['create service type'],'employee'=>['create employee']];
        foreach($permissions[$action] as $permission) abort_unless(auth()->user()->can($permission),403);
        $request->session()->forget('error');
        $result=null;$message='Bilgiler kaydedildi.';
        if($action==='business') $result=app(InvoiceBillingController::class)->business($request);
        elseif($action==='logos') $result=app(SettingController::class)->generalData($request);
        elseif($action==='employee') {
            // The existing employee flow enforces package limits, assigns roles and sends its notifications.
            $request->validate(['name'=>'required|string|max:150','email'=>'required|email|max:254|unique:users,email',
                'phone_number'=>'required|regex:/^5[0-9]{9}$/','password'=>'required|string|min:8|max:255',
                'age'=>'required|integer|between:16,100','joining_date'=>'required|date',
                'gender'=>['required',Rule::in(array_keys(User::genderList()))],'address'=>'required|string|max:1000']);
            if(!\Spatie\Permission\Models\Role::where('parent_id',$owner)->where('name','employee')->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['name'=>'Çalışan rolü bulunamadı. Destek bileti açarak işletme yetkilerinizi kontrol ettirin.']);
            }
            $result=app(EmployeeController::class)->store($request);
        } elseif($action==='defaults') {
            $request->validate(['confirm'=>'accepted']);app(DefaultInventoryCatalog::class)->seed($owner);
            $message='Hazır vergiler ve ürün birimleri eklendi. Mevcut kayıtlar korundu.';
        } elseif($action==='catalog') {
            $data=$request->validate(['category'=>['required',Rule::in(array_keys(VehicleCatalog::CATEGORIES))],'confirm'=>'accepted']);
            $loaded=app(VehicleCatalog::class)->import($owner,$data['category']);
            $message=$loaded['already_loaded'] ? 'Bu liste zaten yüklü; tekrar eklenmedi.' : $loaded['brands'].' marka ve '.$loaded['models'].' model eklendi.';
        } else {
            $rules=match($action) {
                'brand'=>['type'=>'required|string|max:100'],
                'model'=>['name'=>'required|string|max:100','type'=>['required','integer',Rule::exists('vehicle_types','id')->where('parent_id',$owner)]],
                'tax'=>['title'=>'required|string|max:100','rate'=>'required|numeric|between:0,100'],
                'unit'=>['unit'=>'required|string|max:50'],
                'category'=>['name'=>'required|string|max:150'],
                'service'=>['type'=>'required|string|max:150','rate'=>'required|numeric|between:0,999999999','note'=>'nullable|string|max:2000',
                    'tax'=>'nullable|array','tax.*'=>['integer','distinct',Rule::exists('taxes','id')->where('parent_id',$owner)]],
            };
            $data=$request->validate($rules);
            $class=match($action) {'brand'=>VehicleType::class,'model'=>VehicleBrand::class,'tax'=>Tax::class,'unit'=>Unit::class,'category'=>ItemCategory::class,'service'=>ServiceType::class};
            $identity=match($action) {'model','category'=>'name','tax'=>'title','unit'=>'unit',default=>'type'};
            $data[$identity]=trim($data[$identity]);
            if($data[$identity]==='') throw \Illuminate\Validation\ValidationException::withMessages([$identity=>'Bu alan boş bırakılamaz.']);
            $message=DB::transaction(function() use($owner,$class,$data,$identity,$action) {
                User::whereKey($owner)->lockForUpdate()->firstOrFail();
                $query=$class::where('parent_id',$owner)->where($identity,$data[$identity]);
                if($action==='model') $query->where('type',$data['type']);
                if($action==='tax') $query->where('rate',$data['rate']);
                if($query->exists()) return 'Bu kayıt zaten mevcut; tekrar eklenmedi.';
                $record=new $class;
                foreach($data as $key=>$value) $record->$key=$key==='tax' ? implode(',',$value ?? []) : $value;
                if($action==='service' && !isset($data['tax'])) $record->tax='';
                $record->parent_id=$owner;$record->save();return 'Kayıt eklendi.';
            });
        }
        // A failed existing form must stay on its step, rather than advancing the wizard.
        if($result && $request->session()->has('error')) return redirect()->route('business-setup.index',['step'=>$step]);
        $keys=array_keys(self::STEPS);$next=$keys[array_search($step,$keys,true)+1] ?? $step;
        return redirect()->route('business-setup.index',['step'=>$request->boolean('continue') ? $next : $step])->with('success',$message);
    }
}
