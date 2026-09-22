<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\InvoiceService;
use App\Models\Notification;
use App\Models\Quotation;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Models\ServiceType;
use App\Models\Subscription;
use App\Models\Tax;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleBrand;
use App\Models\VehicleType;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use App\Models\VehicleQrCode;
use App\Services\VehicleQrPool;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{

    public function index()
    {
        if (\Auth::user()->can('manage client')) {
            $clients = User::where('parent_id', '=', parentId())->where('type', 'client')->orderBy('id', 'desc')->get();
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        return view('client.index', compact('clients'));
    }


    public function create()
    {
        abort_unless(auth()->user()->type !== 'client' && auth()->user()->can('create client') && auth()->user()->can('create vehicle'), 403);
        app(VehicleQrPool::class)->replenish(parentId());
        $qrCodes = VehicleQrCode::where('parent_id', parentId())->available()->whereNotNull('printed_at')->orderBy('id')->get();
        $types = VehicleType::where('parent_id', parentId())->get()->pluck('type', 'id');
        $types->prepend(__('Select Brand'), '');
        $employees = User::where('parent_id', parentId())->where('type', 'employee')->get()->pluck('name', 'id');
        $employees->prepend(__('Select Employee'), '');
        $status = Service::status();
        $taxes = Tax::where('parent_id', parentId())->pluck('title', 'id');
        $serviceTypes = ServiceType::where('parent_id', parentId())->get()->pluck('type', 'id');
        $serviceTypes->prepend(__('Select Service type'), '');
        $serviceRates = ServiceType::where('parent_id', parentId())->pluck('rate', 'id');

        return view('client.create', compact(
            'qrCodes',
            'types',
            'employees',
            'status',
            'taxes',
            'serviceTypes',
            'serviceRates'
        ));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->type !== 'client' && auth()->user()->can('create vehicle'), 403);
        if (!\Auth::user()->can('create client')) {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $validator = \Validator::make($request->all(), [
            'name'         => 'required',
            'email'        => 'nullable|email|max:255|unique:users',
            'phone_number' => 'required',
            'address'      => 'nullable|string',
            'state'        => 'nullable|string|max:255',
            'city'         => 'nullable|string|max:255',
            'zip_code'     => 'nullable|string|max:255',
            'notes'        => 'nullable|string',
            'type' => ['required', Rule::exists('vehicle_types', 'id')->where('parent_id', parentId())],
            'brand' => ['required', Rule::exists('vehicle_brands', 'id')->where('parent_id', parentId())->where('type', $request->type)],
            'qr_code_id' => 'required|integer',
            'color'        => 'nullable|string|max:255',
            'license_plate' => 'required',
            'engine_type'  => 'nullable|string|max:255',
            'engine_no'    => 'nullable|string|max:255',
            'chassis_no'   => 'nullable|string|max:255',
            'fuel_type'    => 'nullable|string|max:255',
            'mileage'      => 'nullable|integer|min:0|max:2147483647',
            'last_service_date' => 'nullable|date',
            'next_service_due_date' => 'nullable|date',
            'insurance_details' => 'nullable|string',
            'vehicle_notes' => 'nullable|string',
            'assign'       => 'required',
            'service_date' => 'required|date',
            'service_time' => 'required',
            'due_date'     => 'required|date',
            'due_time'     => 'required',
            'status'       => 'required',
            'types'              => 'required|array|min:1',
            'types.*.service_type' => 'required',
            'types.*.rate'         => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput()->with('error', $validator->errors()->first());
        }

        $pricing_feature_settings = getSettingsValByIdName(1, 'pricing_feature');
        if ($pricing_feature_settings == 'on') {
            $authUser    = User::find(parentId());
            $totalClient = $authUser->totalCLient();
            $subscription = Subscription::find($authUser->subscription);
            if ($totalClient >= $subscription->client_limit && $subscription->client_limit != 0) {
                return redirect()->back()->with('error', __('Your client limit is over, please upgrade your subscription.'));
            }
        }

        $userRole = Role::where('name', 'client')->where('parent_id', parentId())->first();
        $vehicle = app(VehicleQrPool::class)->createVehicle(parentId(), (int) $request->qr_code_id,
            function () use ($request, $userRole, &$user, &$client, &$service, &$invoice) {
                $user                    = new User();
                $user->name              = $request->name;
                $user->email             = $request->filled('email') ? $request->email : null;
                $user->phone_number      = $request->phone_number;
                // Customer registration does not collect or distribute login credentials.
                $user->password          = \Hash::make(\Illuminate\Support\Str::random(64));
                $user->type              = $userRole->name;
                $user->profile           = 'avatar.png';
                $user->lang              = 'english';
                $user->email_verified_at = null;
                $user->parent_id         = parentId();
                $user->save();
                $user->assignRole($userRole);
                $client             = new Client();
                $client->client_id  = $this->clientNumber();
                $client->user_id    = $user->id;
                $client->city       = $request->city;
                $client->state      = $request->state;
                $client->zip_code   = $request->zip_code;
                $client->address    = $request->address;
                $client->notes      = $request->notes;
                $client->parent_id  = parentId();
                $client->save();
                $vehicle                      = new Vehicle();
                $vehicle->vehicle_id          = $this->vehicleNumber();
                $vehicle->client              = $user->id;
                $vehicle->type                = $request->type;
                $vehicle->brand               = $request->brand;
                $vehicle->color               = $request->color;
                $vehicle->license_plate       = $request->license_plate;
                $vehicle->engine_type         = $request->engine_type;
                $vehicle->engine_no           = $request->engine_no;
                $vehicle->fuel_type           = $request->fuel_type;
                $vehicle->chassis_no          = $request->chassis_no;
                $vehicle->mileage             = $request->mileage;
                $vehicle->last_service_date   = $request->last_service_date;
                $vehicle->next_service_due_date = $request->next_service_due_date;
                $vehicle->insurance_details   = $request->insurance_details;
                $vehicle->status              = $request->vehicle_status ?? 'active';
                $vehicle->notes               = $request->vehicle_notes;
                $vehicle->parent_id           = parentId();
                $vehicle->save();
                $service               = new Service();
                $service->service_id   = $this->serviceNumber();
                $service->vehicle      = $vehicle->id;
                $service->client       = $user->id;
                $service->service_date = $request->service_date;
                $service->service_time = $request->service_time;
                $service->due_date     = $request->due_date;
                $service->due_time     = $request->due_time;
                $service->assign       = $request->assign;
                $service->status       = $request->status;
                $service->notes        = $request->service_notes;
                $service->parent_id    = parentId();
                $service->save();
                foreach ($request->types as $type) {
                    $serviceItem           = new ServiceItem();
                    $serviceItem->service_id = $service->id;
                    $serviceItem->type_id  = $type['service_type'];
                    $serviceItem->rate     = $type['rate'];
                    $serviceItem->tax      = !empty($type['tax']) ? implode(',', (array) $type['tax']) : '';
                    $serviceItem->note     = $type['note'] ?? '';
                    $serviceItem->parent_id = parentId();
                    $serviceItem->save();
                }
                $invoice               = new Invoice();
                $invoice->invoice_id   = $this->invoiceNumber();
                $invoice->invoice_date = $request->service_date;
                $invoice->client       = $user->id;
                $invoice->service      = $service->id;
                $invoice->status       = 0;
                $invoice->parent_id    = parentId();
                $invoice->save();
                foreach ($request->types as $type) {
                    $invoiceService               = new InvoiceService();
                    $invoiceService->invoice_id   = $invoice->id;
                    $invoiceService->tax          = !empty($type['tax']) ? implode(',', (array) $type['tax']) : null;
                    $invoiceService->service_type = $type['service_type'];
                    $invoiceService->rate         = $type['rate'];
                    $invoiceService->note         = $type['note'] ?? '';
                    $invoiceService->parent_id    = parentId();
                    $invoiceService->save();
                }
                return $vehicle;
        });
        $setting      = settings();
        $errorMessage = '';
        triggerN8n('create_client', [
            'client_id'    => clientPrefix() . $client->client_id,
            'name'         => $user->name,
            'email'        => $user->email,
            'phone'        => $user->phone_number,
            'role'         => $userRole->name,
            'address'      => $request->address,
            'city'         => $request->city,
            'state'        => $request->state,
            'zip_code'     => $request->zip_code,
            'company_name' => $setting['company_name'],
            'company_email' => $setting['company_email'],
            'company_phone' => $setting['company_phone'],
        ]);
        $clientNotification = Notification::where('parent_id', parentId())->where('module', 'client_create')->first();
        if (!empty($clientNotification)) {
            $resp = MessageReplace($clientNotification, $user->id);
            $data = [
                'subject' => $resp['subject'],
                'message' => $resp['message'],
                'module'  => 'client_create',
                'logo'    => $setting['company_logo'],
            ];
            if ($clientNotification->enabled_email == 1 && !empty($user->email)) {
                $r = commonEmailSend($user->email, $data);
                if ($r['status'] == 'error') $errorMessage = $r['message'];
            }
            if ($clientNotification->enabled_sms == 1 && !empty(getSettingsValByName('twilio_sid'))) {
                send_twilio_msg($user->phone_number, $resp['sms_message']);
            }
            if ($clientNotification->enabled_whatsapp == 1 && !empty($user->phone_number)) {
                sendWhatshappSms(['to' => $user->phone_number, 'body' => $resp['sms_message']]);
            }
        }
        $vehicleNotification = Notification::where('parent_id', parentId())->where('module', 'vehicle_create')->first();
        if (!empty($vehicleNotification)) {
            $resp = MessageReplace($vehicleNotification, $vehicle->id);
            $data = [
                'subject' => $resp['subject'],
                'message' => $resp['message'],
                'module'  => 'vehicle_create',
                'logo'    => $setting['company_logo'],
            ];
            if ($vehicleNotification->enabled_email == 1 && !empty($user->email)) {
                $r = commonEmailSend($user->email, $data);
                if ($r['status'] == 'error') $errorMessage = $r['message'];
            }
            if ($vehicleNotification->enabled_sms == 1 && !empty(getSettingsValByName('twilio_sid'))) {
                send_twilio_msg($user->phone_number, $resp['sms_message']);
            }
            if ($vehicleNotification->enabled_whatsapp == 1 && !empty($user->phone_number)) {
                sendWhatshappSms(['to' => $user->phone_number, 'body' => $resp['sms_message']]);
            }
        }
        triggerN8n('new_service', [
            'service_id'    => $service->id,
            'service_no'    => $service->service_id,
            'client_id'     => $user->id,
            'client_name'   => $user->name,
            'client_email'  => $user->email,
            'client_phone'  => $user->phone_number,
            'vehicle_id'    => $vehicle->id,
            'service_date'  => $service->service_date,
            'service_time'  => $service->service_time,
            'due_date'      => $service->due_date,
            'due_time'      => $service->due_time,
            'status'        => $service->status,
            'notes'         => $service->notes,
            'company_name'  => $setting['company_name'],
            'company_email' => $setting['company_email'],
            'company_phone' => $setting['company_phone'],
        ]);
        triggerN8n('assign_service', [
            'service_id'    => $service->id,
            'service_no'    => $service->service_id,
            'assign_id'     => $service->assign,
            'assign_name'   => optional($service->assigns)->name ?? '-',
            'assign_email'  => optional($service->assigns)->email ?? '-',
            'assign_phone'  => optional($service->assigns)->phone_number ?? '-',
            'client_name'   => $user->name,
            'vehicle_id'    => $vehicle->id,
            'service_date'  => $service->service_date,
            'service_time'  => $service->service_time,
            'due_date'      => $service->due_date,
            'due_time'      => $service->due_time,
            'status'        => $service->status,
            'notes'         => $service->notes,
            'company_name'  => $setting['company_name'],
            'company_email' => $setting['company_email'],
            'company_phone' => $setting['company_phone'],
        ]);
        $serviceCreateNotif = Notification::where('parent_id', parentId())->where('module', 'service_create')->first();
        if (!empty($serviceCreateNotif)) {
            $resp = MessageReplace($serviceCreateNotif, $service->id);
            $data = ['subject' => $resp['subject'], 'message' => $resp['message'], 'module' => 'service_create', 'logo' => $setting['company_logo']];
            if ($serviceCreateNotif->enabled_email == 1 && !empty($user->email)) {
                $r = commonEmailSend($user->email, $data);
                if ($r['status'] == 'error') $errorMessage = $r['message'];
            }
            if ($serviceCreateNotif->enabled_sms == 1 && !empty(getSettingsValByName('twilio_sid'))) {
                send_twilio_msg($user->phone_number, $resp['sms_message']);
            }
            if ($serviceCreateNotif->enabled_whatsapp == 1 && !empty($user->phone_number)) {
                sendWhatshappSms(['to' => $user->phone_number, 'body' => $resp['sms_message']]);
            }
        }
        $serviceAssignNotif = Notification::where('parent_id', parentId())->where('module', 'service_assign')->first();
        if (!empty($serviceAssignNotif)) {
            $resp = MessageReplace($serviceAssignNotif, $service->id);
            $data = ['subject' => $resp['subject'], 'message' => $resp['message'], 'module' => 'service_assign', 'logo' => $setting['company_logo']];
            $assignUser = $service->assigns;
            if (!empty($assignUser)) {
                if ($serviceAssignNotif->enabled_email == 1) {
                    $r = commonEmailSend($assignUser->email, $data);
                    if ($r['status'] == 'error') $errorMessage = $r['message'];
                }
                if ($serviceAssignNotif->enabled_sms == 1 && !empty(getSettingsValByName('twilio_sid'))) {
                    send_twilio_msg($assignUser->phone_number, $resp['sms_message']);
                }
                if ($serviceAssignNotif->enabled_whatsapp == 1 && !empty($assignUser->phone_number)) {
                    sendWhatshappSms(['to' => $assignUser->phone_number, 'body' => $resp['sms_message']]);
                }
            }
        }
        return redirect()->route('client.index')
            ->with('success', __('Client, Vehicle & Service successfully created.') . ($errorMessage ? '<br>' . $errorMessage : ''));
    }

    public function show($id)
    {
        $id = decrypt($id);
        $user = User::with('clients', 'clients.vehicles', 'clients.services', 'clients.quotations', 'clients.invoices')->findOrFail($id);
        $client = $user->clients;
        $vehicles = Vehicle::where('client', $user->id)->get();
        $services = Service::with(['vehicles', 'clients'])->where('client', $user->id)->get();
        $quotations = Quotation::with(['vehicles'])->where('client_id', $user->id)->get();
        $invoices = Invoice::with(['services'])->where('client', $user->id)->get();
        return view('client.show', compact('user', 'client', 'vehicles', 'services', 'quotations', 'invoices'));
    }

    public function edit($id)
    {
        $id = decrypt($id);
        $user = User::findOrFail($id);
        $client = $user->clients;
        $vehicle = Vehicle::where('client', $user->id)->first();
        $service = Service::where('client', $user->id)->with('types')->first();
        $gender = User::genderList();
        $types = VehicleType::where('parent_id', parentId())->pluck('type', 'id');
        $types->prepend(__('Select Brand'), '');
        $employees = User::where('parent_id', parentId())->where('type', 'employee')->pluck('name', 'id');
        $employees->prepend(__('Select Employee'), '');
        $status = Service::status();
        $taxes = Tax::where('parent_id', parentId())->pluck('title', 'id');
        $serviceTypes = ServiceType::where('parent_id', parentId())->pluck('type', 'id');
        $serviceTypes->prepend(__('Select Service Type'), '');
        $serviceRates = ServiceType::where('parent_id', parentId())->pluck('rate', 'id');
        $brands = VehicleBrand::where('parent_id', parentId())->where('type', $vehicle->type ?? null)->pluck('name', 'id');
        return view('client.edit', compact('client', 'user', 'vehicle', 'service', 'gender', 'types', 'employees', 'status', 'taxes', 'serviceTypes', 'serviceRates', 'brands'));
    }

    public function update(Request $request, $id)
    {
        if (\Auth::user()->can('edit client')) {
            $validator = \Validator::make($request->all(), [
                'name'          => 'required',
                'email'         => 'nullable|email|max:255|unique:users,email,' . $id,
                'phone_number'  => 'required',
                'address'       => 'nullable|string',
                'state'         => 'nullable|string|max:255',
                'city'          => 'nullable|string|max:255',
                'zip_code'      => 'nullable|string|max:255',
                'notes'         => 'nullable|string',
                'type' => ['required', Rule::exists('vehicle_types', 'id')->where('parent_id', parentId())],
                'brand' => ['required', Rule::exists('vehicle_brands', 'id')->where('parent_id', parentId())->where('type', $request->type)],
                'color'         => 'nullable|string|max:255',
                'license_plate' => 'required',
                'engine_type'   => 'nullable|string|max:255',
                'engine_no'     => 'nullable|string|max:255',
                'fuel_type'     => 'nullable|string|max:255',
                'chassis_no'    => 'nullable|string|max:255',
                'mileage'       => 'nullable|integer|min:0|max:2147483647',
                'last_service_date' => 'nullable|date',
                'next_service_due_date' => 'nullable|date',
                'insurance_details' => 'nullable|string',
                'vehicle_notes' => 'nullable|string',
                'assign'        => 'required',
                'service_date'  => 'required',
                'service_time'  => 'required',
                'due_date'      => 'required',
                'due_time'      => 'required',
                'status'        => 'required',
            ]);
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }
            $user = User::findOrFail($id);
            $user->update([
                'name'         => $request->name,
                'email'        => $request->filled('email') ? $request->email : null,
                'phone_number' => $request->phone_number,
            ]);
            $client = Client::where('user_id', $id)->first();
            if ($client) {
                $client->update([
                    'state'     => $request->state,
                    'city'      => $request->city,
                    'zip_code'  => $request->zip_code,
                    'address'   => $request->address,
                    'notes'     => $request->notes,
                ]);
            }
            $vehicle = Vehicle::where('client', $id)->first();
            if ($vehicle) {
                $vehicle->update([
                    'type'                  => $request->type,
                    'brand'                 => $request->brand,
                    'color'                 => $request->color,
                    'license_plate'         => $request->license_plate,
                    'engine_type'           => $request->engine_type,
                    'engine_no'             => $request->engine_no,
                    'fuel_type'             => $request->fuel_type,
                    'chassis_no'            => $request->chassis_no,
                    'mileage'               => $request->mileage,
                    'last_service_date'     => $request->last_service_date,
                    'next_service_due_date' => $request->next_service_due_date,
                    'insurance_details'     => $request->insurance_details,
                    'notes'                 => $request->vehicle_notes,
                ]);
            }
            $service = Service::where('client', $id)->first();
            if ($service) {
                $service->update([
                    'assign'       => $request->assign,
                    'service_date' => $request->service_date,
                    'service_time' => $request->service_time,
                    'due_date'     => $request->due_date,
                    'due_time'     => $request->due_time,
                    'status'       => $request->status,
                    'notes'        => $request->service_notes,
                ]);
                ServiceItem::where('service_id', $service->id)->delete();
                if (!empty($request->types)) {
                    foreach ($request->types as $type) {
                        ServiceItem::create([
                            'service_id' => $service->id,
                            'type_id'    => $type['service_type'],
                            'rate'       => $type['rate'],
                            'tax'        => !empty($type['tax']) ? implode(',', $type['tax']) : '',
                            'note'       => $type['note'] ?? '',
                            'parent_id'  => parentId(),
                        ]);
                    }
                }
            }
            return redirect()->route('client.index')->with('success', __('Client successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }

    public function destroy($id)
    {
        if (\Auth::user()->can('delete client')) {
            $user = User::find($id);
            $user->delete();
            $client = Client::where('user_id', $id)->delete();

            return redirect()->route('client.index')->with('success', __('Client successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function clientNumber()
    {
        $latest = Client::where('parent_id', parentId())->latest()->first();
        if (!$latest) {
            return 1;
        }
        return $latest->client_id + 1;
    }

    public function getVehicle($clientId)
    {
        $vehicles = Vehicle::where('client', $clientId)->get();
        $vehicleData = [];
        foreach ($vehicles as $vehicle) {
            $veh['id'] = $vehicle->id;
            $veh['name'] = vehiclePrefix() . $vehicle->vehicle_id . ' | ' . $vehicle->display_name . ' | ' . $vehicle->license_plate;
            $vehicleData[] = $veh;
        }

        return response()->json($vehicleData);
    }

    public function getService($clientId)
    {

        $services = Service::where('client', $clientId)->get();
        $serviceData = [];
        foreach ($services as $service) {
            $serv['id'] = $service->id;
            $serv['name'] = servicePrefix() . $service->service_id . ' | ' . $service->vehicles->license_plate;
            $serviceData[] = $serv;
        }
        return response()->json($serviceData);
    }

    public function vehicleNumber()
    {
        $latest = Vehicle::where('parent_id', parentId())->latest()->first();
        if (!$latest) {
            return 1;
        }
        return $latest->vehicle_id + 1;
    }

    public function serviceNumber()
    {
        $latest = Service::where('parent_id', parentId())->latest()->first();
        if (!$latest) {
            return 1;
        }
        return $latest->service_id + 1;
    }

    function invoiceNumber()
    {
        $lastInvoice = Invoice::where('parent_id', parentId())->latest()->first();
        if (!$lastInvoice) {
            return 1;
        }
        return $lastInvoice->invoice_id + 1;
    }
}
