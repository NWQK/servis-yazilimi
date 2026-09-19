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
            } elseif ($user->type == 'employee') {
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
        $clients = User::where('parent_id', parentId())->where('type', 'client')->get()->pluck('name', 'id');
        $clients->prepend(__('Select Client'), '');
        $employees = User::where('parent_id', parentId())->where('type', 'employee')->get()->pluck('name', 'id');
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
                    'vehicle' => 'required',
                    'client' => 'required',
                    'service_date' => 'required',
                    'service_time' => 'required',
                    'due_date' => 'required',
                    'due_time' => 'required',
                    'assign' => 'required',
                    'status' => 'required',

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
            $service->parent_id = parentId();
            $service->save();
            $serviceTypes = $request->types;


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
            $invoice->status = 0;
            $invoice->parent_id = parentId();
            $invoice->save();

            $serviceType = $request->types;
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

            $setting = settings();

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
                'assign_email' => !empty($service->assigns) ? $service->assigns->email : '-',
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
            $notification = Notification::where('parent_id', parentId())->where('module', $module)->first();
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
                    $twilio_sid = getSettingsValByName('twilio_sid');
                    if (!empty($twilio_sid)) {
                        send_twilio_msg($service->clients->phone_number, $notificationResponse['sms_message']);
                    }
                }
                if ($notification->enabled_whatsapp == 1 && !empty($service->clients->phone_number)) {
                    sendWhatshappSms([
                        'to' => $service->clients->phone_number,
                        'body' => $notificationResponse['sms_message']
                    ]);
                }
            }


            $module = 'service_assign';
            $notification = Notification::where('parent_id', parentId())->where('module', $module)->first();
            $setting = settings();
            $errorMessage = '';

            if (!empty($notification)) {
                $notificationResponse = MessageReplace($notification, $service->id);

                $data['subject'] = $notificationResponse['subject'];
                $data['message'] = $notificationResponse['message'];
                $data['module'] = $module;
                $data['logo'] = $setting['company_logo'];
                $to = $service->assigns->email;

                if ($notification->enabled_email == 1) {
                    $response = commonEmailSend($to, $data);
                    if ($response['status'] == 'error') {
                        $errorMessage = $response['message'];
                    }
                }
                if ($notification->enabled_sms == 1) {
                    $twilio_sid = getSettingsValByName('twilio_sid');
                    if (!empty($twilio_sid)) {
                        send_twilio_msg($service->assigns->phone_number, $notificationResponse['sms_message']);
                    }
                }
                if ($notification->enabled_whatsapp == 1 && !empty($service->assigns->phone_number)) {
                    sendWhatshappSms([
                        'to' => $service->assigns->phone_number,
                        'body' => $notificationResponse['sms_message']
                    ]);
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
        $service = Service::find($id);
        $settings = settings();
        $status = Service::status();
        return view('service.show', compact('service', 'settings', 'status'));
    }


    public function edit($id)
    {
        $id = Crypt::decrypt($id);

        $service = Service::with('types')->where('id', $id)->first();

        // convert tax string to array
        if ($service->types) {
            foreach ($service->types as $type) {
                $type->tax = !empty($type->tax) ? explode(',', $type->tax) : [];
            }
        }

        $clients = User::where('parent_id', parentId())->where('type', 'client')->get()->pluck('name', 'id');
        $clients->prepend(__('Select Client'), '');

        $employees = User::where('parent_id', parentId())->where('type', 'employee')->get()->pluck('name', 'id');
        $employees->prepend(__('Select Employee'), '');

        $types = ServiceType::where('parent_id', parentId())->get()->pluck('type', 'id');

        $status = Service::status();

        $taxes = Tax::where('parent_id', parentId())->pluck('title', 'id');

        return view('service.edit', compact('clients', 'service', 'status', 'types', 'employees', 'taxes'));
    }

    public function update(Request $request, Service $service)
    {
        if (\Auth::user()->can('edit service')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'vehicle' => 'required',
                    'client' => 'required',
                    'service_date' => 'required',
                    'service_time' => 'required',
                    'due_date' => 'required',
                    'due_time' => 'required',
                    'assign' => 'required',
                    'status' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }
            $service->vehicle = $request->vehicle;
            $service->client = $request->client;
            $service->service_date = $request->service_date;
            $service->service_time = $request->service_time;
            $service->due_date = $request->due_date;
            $service->due_time = $request->due_time;
            $service->assign = $request->assign;
            $service->status = $request->status;
            $service->notes = $request->notes;
            $service->save();
            $serviceTypes = $request->types;
            for ($i = 0; $i < count($serviceTypes); $i++) {
                $serviceItem = ServiceItem::find($serviceTypes[$i]['id']);
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
            return redirect()->route('service.index')->with('success', __('Service successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function destroy(Service $service)
    {
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
            $services = Service::where('parent_id', '=', parentId())->where('service_date', date('Y-m-d'))->get();
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        return view('service.today_service', compact('services'));
    }

    public function type(Request $request)
    {
        $itemData = ServiceType::find($request->type_id);
        return json_encode($itemData);
    }

    public function serviceTypeDestroy(Request $request)
    {
        if (\Auth::user()->can('delete service type')) {
            if (empty($request->type)) {
                $serviceType = ServiceItem::find($request->id);
                $serviceType->delete();
            } else {
                if ($request->type == 'qtype') {
                    $serviceType = QuotationService::find($request->id);
                    $serviceType->delete();
                } elseif ($request->type == 'qitem') {
                    $serviceType = QuotationItem::find($request->id);
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
            } else if (Auth::user()->type == 'employee') {
                $services = Service::where('parent_id', parentId())->where('assign', Auth::user()->id)->get();
            } else {
                $services = Service::where('parent_id', parentId())->get();
            }

            $eventData = $currentMonth = [];
            foreach ($services as $service) {
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
        $stages = collect();
        foreach (Service::status() as $key => $label) {
            $stage = new \stdClass();
            $stage->id = $key;
            $stage->title = $label;
            $stage->requests = Service::where('parent_id', parentId())->where('status', $key)->with(['clients', 'assigns'])->get();
            $stages->push($stage);
        }
        return view('service.kanban', compact('stages'));
    }

    public function changeStatus(Request $request)
    {
        $woRequest = Service::findOrFail($request->applicantId);
        $woRequest->status = $request->stageId;
        $woRequest->save();
        return response()->json([
            'success' => true,
        ]);
    }

}
