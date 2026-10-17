<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceService;
use App\Models\Notification;
use App\Models\QuotationItem;
use App\Models\QuotationService;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Models\ServiceType;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class ServiceController extends Controller
{
    public function index()
    {
        if (\Auth::user()->can('manage service')) {
            $user = Auth::user();
            if ($user->type == 'client') {
                $services = Service::where('parent_id', '=', parentId())->where('client', $user->id)->orderBy('id', 'desc')->get();
            } elseif (isAssignedEmployee()) {
                $services = Service::where('parent_id', '=', parentId())->where('assign', $user->id)->orderBy('id', 'desc')->get();
            } else {
                $services = Service::where('parent_id', '=', parentId())->orderBy('id', 'desc')->get();
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        return view('service.index', compact('services'));
    }

    public function create()
    {
        $clients = User::where('parent_id', parentId())->where('type', 'client')->whereNull('client_archived_at')->get()->pluck('name', 'id');
        $clients->prepend(__('Select Client'), '');
        $employees = User::where('parent_id', parentId())->where('type', 'employee')->where('is_active',1)->get()->pluck('name', 'id');
        $employees->prepend(__('Select Employee'), '');
        $types = ServiceType::where('parent_id', parentId())->get()->pluck('type', 'id');
        $types->prepend(__('Select Service type'), '');
        $serviceRates = ServiceType::where('parent_id', parentId())->pluck('rate', 'id');
        $taxes = Tax::where('parent_id', parentId())->pluck('title', 'id');
        $status = Service::status();
        return view('service.create', compact('clients', 'status', 'types', 'employees', 'serviceRates', 'taxes'));
    }

    public function store(Request $request)
    {
        if (\Auth::user()->can('create service')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'vehicle' => ['required', 'integer', \Illuminate\Validation\Rule::exists('vehicles', 'id')->where(fn ($query) => $query->where('parent_id', parentId())->where('client', $request->client)->whereNull('deleted_at'))],
                    'mileage' => 'nullable|integer|min:0|max:2147483647',
                    'client' => \App\Services\ServiceInput::client(),
                    'service_date' => 'nullable|date_format:Y-m-d',
                    'service_time' => 'nullable|date_format:H:i,H:i:s',
                    'due_date' => 'nullable|date_format:Y-m-d',
                    'due_time' => 'nullable|date_format:H:i,H:i:s',
                    'assign' => \App\Services\ServiceInput::assignee(),
                    'status' => ['required',\Illuminate\Validation\Rule::in(array_keys(Service::status()))],
                    'types'=>'sometimes|array|max:100',
                    'types.*.service_type'=>\App\Services\ServiceInput::type(),
                    'types.*.rate'=>'required|numeric|min:0',
                    'types.*.tax'=>'nullable|array',
                    'types.*.tax.*'=>\App\Services\ServiceInput::tax(),
                    'external_labor_amount' => \App\Services\ExternalLabor::RULE,
                    'external_labor_tax_id' => \App\Services\ExternalLabor::taxRules(),

                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $service = new Service();
            $service->service_id = $this->serviceNumber();
            $service->vehicle = $request->vehicle;
            $service->client = $request->client;
            $service->service_date = $request->service_date;
            $service->service_time = $request->service_time;
            $service->due_date = $request->due_date;
            $service->due_time = $request->due_time;
            $service->assign = $request->assign;
            $service->status = $request->status;
            $service->notes = $request->notes;
            $service->external_labor_amount = $request->external_labor_amount ?? 0;
            $service->parent_id = parentId();
            \App\Services\ExternalLabor::selectTax($service, $request->external_labor_tax_id);
            if ($request->filled('mileage')) $service->mileage_at_service=(int)$request->mileage;
            $service->save();
            $serviceTypes = $request->input('types',[]);


            for ($i = 0; $i < count($serviceTypes); $i++) {
                $serviceItem = new ServiceItem();
                $serviceItem->service_id = $service->id;
                $serviceItem->type_id = $serviceTypes[$i]['service_type'];
                $serviceItem->rate = $serviceTypes[$i]['rate'];
                $serviceItem->tax = !empty($serviceTypes[$i]['tax']) ? implode(',', $serviceTypes[$i]['tax']) : '';
                $serviceItem->note = $serviceTypes[$i]['note'];
                $serviceItem->parent_id = parentId();
                $serviceItem->save();
            }


            $invoice = new Invoice();
            $invoice->invoice_id = $this->invoiceNumber();
            $invoice->invoice_date = $request->service_date;
            $invoice->client = $request->client;
            $invoice->service = $service->id;
            $invoice->external_labor_amount = $service->external_labor_amount;
            \App\Services\ExternalLabor::copyTax($service, $invoice);
            $invoice->status = 0;
            $invoice->parent_id = parentId();
            $invoice->save();

            $serviceType = $request->input('types',[]);
            for ($i = 0; $i < count($serviceType); $i++) {
                $invoiceService = new InvoiceService();
                $invoiceService->invoice_id = $invoice->id;
                $invoiceService->tax = !empty($serviceType[$i]['tax']) ? implode(',', (array) $serviceType[$i]['tax']) : null;
                $invoiceService->service_type = $serviceType[$i]['service_type'];
                $invoiceService->rate = $serviceType[$i]['rate'];
                $invoiceService->note = $serviceType[$i]['note'];
                $invoiceService->parent_id = parentId();
                $invoiceService->save();
            }

            $this->updateVehicleMileage($request);

            $setting = settings();

            app(\App\Services\InvoiceCustomerEmail::class)->send($invoice);

            triggerN8n('new_service', [
                'service_id' => $service->id,
                'service_no' => $service->service_id,
                'client_id' => $service->client,
                'client_name' => !empty($service->clients) ? $service->clients->name : '-',
                'client_email' => !empty($service->clients) ? $service->clients->email : '-',
                'client_phone' => !empty($service->clients) ? $service->clients->phone_number : '-',
                'vehicle_id' => $service->vehicle,
                'service_date' => $service->service_date,
                'service_time' => $service->service_time,
                'due_date' => $service->due_date,
                'due_time' => $service->due_time,
                'status' => $service->status,
                'notes' => $service->notes,
                'company_name' => $setting['company_name'],
                'company_email' => $setting['company_email'],
                'company_phone' => $setting['company_phone'],
            ]);

            // Trigger N8n — service assigned to employee
            triggerN8n('assign_service', [
                'service_id' => $service->id,
                'service_no' => $service->service_id,
                'assign_id' => $service->assign,
                'assign_name' => !empty($service->assigns) ? $service->assigns->name : '-',
                'assign_email' => !empty($service->assigns) ? $service->assigns?->email : '-',
                'assign_phone' => !empty($service->assigns) ? $service->assigns->phone_number : '-',
                'client_name' => $service->clients->name,
                'vehicle_id' => $service->vehicle,
                'service_date' => $service->service_date,
                'service_time' => $service->service_time,
                'due_date' => $service->due_date,
                'due_time' => $service->due_time,
                'status' => $service->status,
                'notes' => $service->notes,
                'company_name' => $setting['company_name'],
                'company_email' => $setting['company_email'],
                'company_phone' => $setting['company_phone'],
            ]);
            $module = 'service_create';
            $notification = \App\Services\CentralEmail::template($module);
            $errorMessage = '';

            if (!empty($notification)) {
                $notificationResponse = MessageReplace($notification, $service->id);
                $data['subject'] = $notificationResponse['subject'];
                $data['message'] = $notificationResponse['message'];
                $data['module'] = $module;
                $data['logo'] = $setting['company_logo'];
                $to = $service->clients->email;

                if ($notification->enabled_email == 1) {
                    $response = commonEmailSend($to, $data);
                    if ($response['status'] == 'error') {
                        $errorMessage = $response['message'];
                    }
                }
                if ($notification->enabled_sms == 1) {

                }

            }


            $module = 'service_assign';
            $notification = \App\Services\CentralEmail::template($module);
            $setting = settings();
            $errorMessage = '';

            if (!empty($notification)) {
                $notificationResponse = MessageReplace($notification, $service->id);

                $data['subject'] = $notificationResponse['subject'];
                $data['message'] = $notificationResponse['message'];
                $data['module'] = $module;
                $data['logo'] = $setting['company_logo'];
                $to = $service->assigns?->email;

                if ($notification->enabled_email == 1) {
                    $response = commonEmailSend($to, $data);
                    if ($response['status'] == 'error') {
                        $errorMessage = $response['message'];
                    }
                }
                if ($notification->enabled_sms == 1) {

                }

            }

            return redirect()->route('service.index')->with('success', __('Service successfully created.') . '' . $errorMessage);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }



    public function show($ids)
    {
        $id = Crypt::decrypt($ids);
        abort_unless(auth()->user()->can('show service'),403);
        $service = Service::where('parent_id',parentId())->findOrFail($id);
        abort_if(isAssignedEmployee() && (int)$service->assign!==(int)auth()->id(),403);
        abort_if(auth()->user()->type==='client' && (int)$service->client!==(int)auth()->id(),403);
        $service->load(['photos'=>fn($q)=>$q->where('parent_id',parentId())]);
        $settings = settings();
        $status = Service::status();
        return view('service.show', compact('service', 'settings', 'status'));
    }


    public function edit($id)
    {
        abort_unless(auth()->user()->can('edit service'),403);
        $id = Crypt::decrypt($id);

        $service = Service::with('types')->where('parent_id', parentId())->findOrFail($id);
        abort_if(isAssignedEmployee() && (int)$service->assign!==(int)auth()->id(),403);

        // convert tax string to array
        if ($service->types) {
            foreach ($service->types as $type) {
                $type->tax = !empty($type->tax) ? explode(',', $type->tax) : [];
            }
        }

        $clients = User::where('parent_id', parentId())->where('type', 'client')->get()->pluck('name', 'id');
        $clients->prepend(__('Select Client'), '');

        $employees = User::where('parent_id', parentId())->where('type', 'employee')->where(fn($q)=>$q->where('is_active',1)->when(in_array($service->status,['completed','cancelled'],true),fn($q)=>$q->orWhere('id',$service->assign)))->get()->pluck('name', 'id');
        $employees->prepend(__('Select Employee'), '');

        $types = ServiceType::where('parent_id', parentId())->get()->pluck('type', 'id');

        $status = Service::status();

        $taxes = Tax::where('parent_id', parentId())->pluck('title', 'id');

        return view('service.edit', compact('clients', 'service', 'status', 'types', 'employees', 'taxes'));
    }

    public function update(Request $request, Service $service)
    {
        if (\Auth::user()->can('edit service')) {
            $service->refresh();
            abort_if(isAssignedEmployee() && (int)$service->assign!==(int)auth()->id(),403);
            abort_unless((int) $service->parent_id === (int) parentId(), 403);
            $validator = \Validator::make(
                $request->all(),
                [
                    'vehicle' => ['required', 'integer', \Illuminate\Validation\Rule::exists('vehicles', 'id')->where(fn ($query) => $query->where('parent_id', parentId())->where('client', $request->client)->whereNull('deleted_at'))],
                    'mileage' => 'nullable|integer|min:0|max:2147483647',
                    'client' => \App\Services\ServiceInput::client(),
                    'service_date' => 'nullable|date_format:Y-m-d',
                    'service_time' => 'nullable|date_format:H:i,H:i:s',
                    'due_date' => 'nullable|date_format:Y-m-d',
                    'due_time' => 'nullable|date_format:H:i,H:i:s',
                    'assign' => \App\Services\ServiceInput::assignee($service),
                    'status' => ['required',\Illuminate\Validation\Rule::in(array_keys(Service::status()))],
                    'types.*.rate'=>'required|numeric|min:0',
                    'types.*.tax'=>'nullable|array',
                    'types.*.tax.*'=>\App\Services\ServiceInput::tax(),
                    'external_labor_amount' => \App\Services\ExternalLabor::RULE, 'external_labor_tax_id' => \App\Services\ExternalLabor::taxRules(),
                    'types' => 'sometimes|array|max:100',
                    'types.*.id' => ['nullable','integer',\Illuminate\Validation\Rule::exists('service_items','id')->where(fn($q)=>$q->where('parent_id',parentId())->where('service_id',$service->id))],
                    'types.*.service_type' => ['required','integer',\Illuminate\Validation\Rule::exists('service_types','id')->where('parent_id',parentId())],
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }
            $serviceVehicleChanged=(int)$service->vehicle !== (int)$request->vehicle;
            $service->vehicle = $request->vehicle;
            $service->client = $request->client;
            $service->service_date = $request->service_date;
            $service->service_time = $request->service_time;
            $service->due_date = $request->due_date;
            $service->due_time = $request->due_time;
            $service->assign = $request->assign;
            $service->status = $request->status;
            $service->notes = $request->notes;
            $currentMileage=Vehicle::where('parent_id',parentId())->whereKey($request->vehicle)->value('mileage');
            if ($serviceVehicleChanged || ($request->filled('mileage') && (int)$request->mileage !== (int)$currentMileage)) {
                $service->mileage_at_service=$request->filled('mileage') ? (int)$request->mileage : $currentMileage;
            }
            $service->save();
            if ($request->exists('external_labor_amount') || $request->exists('external_labor_tax_id')) app(\App\Services\ExternalLabor::class)->update($service, $request->exists('external_labor_amount') ? $request->external_labor_amount : $service->external_labor_amount, $request->external_labor_tax_id, $request->exists('external_labor_tax_id'));
            $serviceTypes = $request->input('types',[]);
            for ($i = 0; $i < count($serviceTypes); $i++) {
                $serviceItem = ServiceItem::where('parent_id',parentId())->where('service_id',$service->id)->find($serviceTypes[$i]['id']??null);
                if ($serviceItem == null) {
                    $serviceItem = new serviceItem();
                    $serviceItem->service_id = $service->id;
                }

                $serviceItem->type_id = $serviceTypes[$i]['service_type'];
                $serviceItem->rate = $serviceTypes[$i]['rate'];
                $serviceItem->note = $serviceTypes[$i]['note'];
                $serviceItem->tax = !empty($serviceTypes[$i]['tax']) ? implode(',', $serviceTypes[$i]['tax']) : '';
                $serviceItem->parent_id = parentId();
                $serviceItem->save();
            }
            $this->updateVehicleMileage($request);
            return redirect()->route('service.index')->with('success', __('Service successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    private function updateVehicleMileage(Request $request): void
    {
        // An omitted/empty field leaves the recorded mileage unchanged, including legacy forms.
        if ($request->filled('mileage')) {
            Vehicle::where('parent_id', parentId())->where('client', $request->client)
                ->whereKey($request->vehicle)->update(['mileage' => (int) $request->mileage]);
        }
    }

    public function destroy(Service $service)
    {
        $service->refresh();
        abort_if(isAssignedEmployee() && (int)$service->assign!==(int)auth()->id(),403);
        abort_unless((int)$service->parent_id===(int)parentId()&&auth()->user()->type!=='client',403);
        if (\Auth::user()->can('delete service')) {
            $service->delete();
            return redirect()->route('service.index')->with('success', __('Service successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function serviceNumber()
    {
        $latest = Service::where('parent_id', parentId())->latest()->first();
        if (!$latest) {
            return 1;
        }
        return $latest->service_id + 1;
    }
    public function todayService()
    {
        if (\Auth::user()->can('manage service')) {
            $services = Service::where('parent_id', '=', parentId())->when(isAssignedEmployee(),fn($q)=>$q->where('assign',auth()->id()))->where('service_date', date('Y-m-d'))->get();
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        return view('service.today_service', compact('services'));
    }

    public function type(Request $request)
    {
        abort_unless(auth()->user()->can('manage service'),403);
        $itemData = ServiceType::where('parent_id',parentId())->findOrFail($request->type_id);
        return json_encode($itemData);
    }

    public function serviceTypeDestroy(Request $request)
    {
        if (\Auth::user()->can('delete service type')) {
            if (empty($request->type)) {
                $serviceType = ServiceItem::where('parent_id',parentId())->findOrFail($request->id);
                abort_unless(auth()->user()->can('edit service'),403);
                $service=Service::where('parent_id',parentId())->findOrFail($serviceType->service_id);
                abort_if(isAssignedEmployee() && (int)$service->assign!==(int)auth()->id(),403);
                $serviceType->delete();
            } else {
                if ($request->type == 'qtype') {
                    $serviceType = QuotationService::where('parent_id',parentId())->findOrFail($request->id);
                    $serviceType->delete();
                } elseif ($request->type == 'qitem') {
                    $serviceType = QuotationItem::where('parent_id',parentId())->findOrFail($request->id);
                    $serviceType->delete();
                }
            }
            return response()->json([
                'status' => 'success',
                'msg' => __('successfully deleted.'),
            ]);
        } else {
            return redirect()->back()->with('error', __('Permission Denied!'));
        }
    }

    function invoiceNumber()
    {
        $lastInvoice = Invoice::where('parent_id', parentId())->latest()->first();
        if (!$lastInvoice) {
            return 1;
        }
        return $lastInvoice->invoice_id + 1;
    }

    public function calendar()
    {
        if (\Auth::user()->can('manage service')) {
            if (Auth::user()->type == 'client') {
                $services = Service::where('parent_id', parentId())->where('client', Auth::user()->id)->get();
            } else if (isAssignedEmployee()) {
                $services = Service::where('parent_id', parentId())->where('assign', Auth::user()->id)->get();
            } else {
                $services = Service::where('parent_id', parentId())->get();
            }

            $eventData = $currentMonth = [];
            foreach ($services as $service) {
                if (!$service->service_date) continue; // Undated services have no calendar position.
                $assign_user = User::find($service->assign);
                $event = [
                    'title' => servicePrefix() . $service->service_id,
                    'assign' => ucfirst($assign_user->name ?? "-"),
                    'start' => date("Y-m-d", strtotime($service->service_date)),
                    'end' => date("Y-m-d", strtotime($service->service_date)),
                    'urls' => route('service.show', encrypt($service->id)),
                ];
                $eventData[] = $event;
            }
            return view('service.calendar', compact('services', 'eventData'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

     public function serviceKanban()
    {
        abort_unless(auth()->user()->can('manage service'),403);
        $stages = collect();
        foreach (Service::status() as $key => $label) {
            $stage = new \stdClass();
            $stage->id = $key;
            $stage->title = $label;
            $stage->requests = Service::where('parent_id', parentId())->when(isAssignedEmployee(),fn($q)=>$q->where('assign',auth()->id()))->where('status', $key)->with(['clients', 'assigns'])->get();
            $stages->push($stage);
        }
        return view('service.kanban', compact('stages'));
    }

    public function changeStatus(Request $request)
    {
        abort_unless(auth()->user()->type!=='client' && auth()->user()->can('edit service'),403);
        $request->validate(['applicantId'=>'required|integer','stageId'=>['required',\Illuminate\Validation\Rule::in(array_keys(Service::status()))]]);
        $woRequest = Service::where('parent_id',parentId())->findOrFail($request->applicantId);
        abort_if(isAssignedEmployee() && (int)$woRequest->assign!==(int)auth()->id(),403);
        $woRequest->status = $request->stageId;
        $woRequest->save();
        return response()->json([
            'success' => true,
        ]);
    }

}
