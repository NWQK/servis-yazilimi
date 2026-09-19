<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Notification;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\QuotationService;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Models\ServiceType;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class QuotationController extends Controller
{
    public function index()
    {
        if (Auth::user()->can('manage quotation')) {

            if (Auth::user()->type == 'client') {
                $user = Auth::user();
                $quotations = Quotation::where('parent_id', '=', parentId())->where('client_id', $user->id)->orderBy('id', 'desc')->get();
                return view('quotation.index', compact('quotations'));
            } else {

                $quotations = Quotation::where('parent_id', '=', parentId())->orderBy('id', 'desc')->get();
                return view('quotation.index', compact('quotations'));
            }
        } else {
            return redirect()->back()->with('erorr', __('Permission Denied.'));
        }
    }

    public function create()
    {
        $clients = User::where('parent_id', parentId())->where('type', 'client')->get()->pluck('name', 'id');
        $clients->prepend(__('Select Client'), '');

        $status = Quotation::statues();

        $types = ServiceType::where('parent_id', parentId())->get()->pluck('type', 'id');
        $types->prepend(__('Select Service type'), '');

        $invoiceItems = Item::where('parent_id', parentId())->get()->pluck('title', 'id');
        $invoiceItems->prepend(__('Select Item'), '');

        $taxes = Tax::where('parent_id', parentId())->pluck('title', 'id');

        return view('quotation.create', compact('clients', 'status', 'types', 'invoiceItems', 'taxes'));
    }


    public function store(Request $request)
    {
        if (\Auth::user()->can('create quotation')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'quotation_date' => 'required',
                    'client' => 'required',
                    'vehicle' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $quotation = new Quotation();
            $quotation->quotation_id = $this->quotationNumber();
            $quotation->quotation_date = $request->quotation_date;
            $quotation->client_id = $request->client;
            $quotation->vehicle_id = $request->vehicle;
            $quotation->status = $request->status;
            $quotation->convert_service = 0;
            $quotation->notes = $request->notes;
            $quotation->parent_id = parentId();
            $quotation->save();

            $quotationTypes = $request->types;

            for ($i = 0; $i < count($quotationTypes); $i++) {
                $serviceType = new QuotationService();
                $serviceType->quotation_id = $quotation->id;
                $serviceType->tax = !empty($quotationTypes[$i]['tax']) ? implode(',', (array) $quotationTypes[$i]['tax']) : null;
                $serviceType->service_type = $quotationTypes[$i]['service_type'];
                $serviceType->rate = $quotationTypes[$i]['rate'];
                $serviceType->note = $quotationTypes[$i]['note'];
                $serviceType->parent_id = parentId();
                $serviceType->save();
            }

            $quotationItems = $request->items;
            for ($i = 0; $i < count($quotationItems); $i++) {
                $items = new QuotationItem();
                $items->quotation_id = $quotation->id;
                $items->item = $quotationItems[$i]['item'];
                $items->quantity = $quotationItems[$i]['quantity'];
                $items->amount = $quotationItems[$i]['amount'];
                $items->tax = !empty($quotationItems[$i]['tax']) ? implode(',', (array) $quotationItems[$i]['tax']) : null;
                $items->description = $quotationItems[$i]['description'];
                $items->parent_id = parentId();
                $items->save();
            }

            return redirect()->route('quotation.index')->with('success', __('Quotation successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }



    public function show($ids)
    {
        $id = Crypt::decrypt($ids);
        $quotation = Quotation::find($id);
        $settings = settings();
        $status = Quotation::statues();
        return view('quotation.show', compact('quotation', 'settings', 'status'));
    }


    public function edit($id)
    {

        $id = Crypt::decrypt($id);
        $quotation = Quotation::where('id', $id)->first();
        $clients = User::where('parent_id', parentId())->where('type', 'client')->get()->pluck('name', 'id');
        $clients->prepend(__('Select Client'), '');

        $status = Quotation::statues();
        $types = ServiceType::where('parent_id', parentId())->get()->pluck('type', 'id');
        $types->prepend(__('Select Service type'), '');

        $invoiceItems = Item::where('parent_id', parentId())->get()->pluck('title', 'id');
        $invoiceItems->prepend(__('Select Item'), '');
        $taxes = Tax::where('parent_id', parentId())->pluck('title', 'id');
        return view('quotation.edit', compact('clients', 'quotation', 'status', 'types', 'invoiceItems', 'taxes'));
    }

    public function update(Request $request, $id)
    {
        if (\Auth::user()->can('edit quotation')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'quotation_date' => 'required',
                    'client_id' => 'required',
                    'vehicle' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $quotation = Quotation::find($id);
            $quotation->quotation_date = $request->quotation_date;
            $quotation->client_id = $request->client_id;
            $quotation->vehicle_id = $request->vehicle;
            $quotation->status = $request->status;
            $quotation->notes = $request->notes;
            $quotation->parent_id = parentId();
            $quotation->save();

            // Update or create types
            foreach ($request->types ?? [] as $qType) {
                if (!empty($qType['id'])) {
                    QuotationService::where('id', $qType['id'])
                        ->where('quotation_id', $quotation->id)
                        ->update([
                            'service_type' => $qType['service_type'],
                            'rate' => $qType['rate'],
                            'tax' => !empty($qType['tax']) ? implode(',', $qType['tax']) : '',
                            'note' => $qType['note'],
                            'parent_id' => parentId(),
                        ]);
                } else {
                    QuotationService::create([
                        'quotation_id' => $quotation->id,
                        'service_type' => $qType['service_type'],
                        'rate' => $qType['rate'],
                        'tax' => !empty($qType['tax']) ? implode(',', $qType['tax']) : '',
                        'note' => $qType['note'],
                        'parent_id' => parentId(),
                    ]);
                }
            }

            // Delete removed types
            $submittedTypeIds = collect($request->types ?? [])->pluck('id')->filter()->toArray();
            QuotationService::where('quotation_id', $quotation->id)
                ->whereNotIn('id', $submittedTypeIds)
                ->delete();

            // Update or create items
            foreach ($request->items ?? [] as $qItem) {
                if (!empty($qItem['id'])) {
                    $quotation->items()->where('id', $qItem['id'])->update([
                        'item' => $qItem['item'],
                        'quantity' => $qItem['quantity'],
                        'amount' => $qItem['amount'],
                        'tax' => !empty($qItem['tax']) ? implode(',', $qItem['tax']) : '',
                        'description' => $qItem['description'] ?? null,
                        'parent_id' => parentId(),
                    ]);
                } else {
                    $quotation->items()->create([
                        'item' => $qItem['item'],
                        'quantity' => $qItem['quantity'],
                        'amount' => $qItem['amount'],
                        'tax' => !empty($qItem['tax']) ? implode(',', $qItem['tax']) : '',
                        'description' => $qItem['description'] ?? null,
                        'parent_id' => parentId(),
                    ]);
                }
            }

            // Delete removed items
            $submittedItemIds = collect($request->items ?? [])->pluck('id')->filter()->toArray();
            $quotation->items()->whereNotIn('id', $submittedItemIds)->delete();

            return redirect()->route('quotation.index')->with('success', __('Quotation successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }




    public function destroy($id)
    {
        if (\Auth::user()->can('delete quotation')) {

            $quotation = Quotation::find($id);
            $quotation->delete();
            QuotationItem::where('quotation_id', '=', $quotation->id)->delete();
            QuotationService::where('quotation_id', '=', $quotation->id)->delete();
            return redirect()->route('quotation.index')->with('success', __('Quotation successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function quotationNumber()
    {
        $latest = Quotation::where('parent_id', parentId())->latest()->first();
        if (!$latest) {
            return 1;
        }
        return $latest->quotation_id + 1;
    }



    public function convert($id)
    {
        if (\Auth::user()->can('convert quotation')) {
            $quotation = Quotation::findOrFail($id);

            $employees = User::where('parent_id', parentId())->where('type', 'employee')->get()->pluck('name', 'id');
            $employees->prepend(__('Select Employee'), '');
            $types = ServiceType::where('parent_id', parentId())->get()->pluck('type', 'id');
            $types->prepend(__('Select Service type'), '');

            $serviceRates = ServiceType::where('parent_id', parentId())
                ->pluck('rate', 'id');

            $taxes = Tax::where('parent_id', parentId())->pluck('title', 'id');
            $status = Service::status();

            return view('quotation.convert', compact('quotation', 'employees', 'status', 'types', 'taxes', 'serviceRates'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function convertInServiceData(Request $request, $id)
    {

        $validator = \Validator::make(
            $request->all(),
            [
                'assign' => 'required',
                'service_date' => 'required',
                'service_time' => 'required',
                'due_date' => 'required',
                'due_time' => 'required',


            ]
        );
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return redirect()->back()->with('error', $messages->first());
        }

        $quotation = Quotation::find($id);
        $quotation->convert_service = 1;
        $quotation->save();


        $service = new Service();
        $service->service_id = $this->serviceNumber();
        $service->vehicle = $quotation->vehicle_id;
        $service->client = $quotation->client_id;
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
                    'to' =>$service->clients->phone_number,
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
                    'to' =>$service->assigns->phone_number,
                    'body' => $notificationResponse['sms_message']
                ]);
            }
        }

        return redirect()->route('quotation.index')->with('success', __('Quotation successfully created.') . '' . $errorMessage);
    }

    public function serviceNumber()
    {
        $latest = Service::where('parent_id', parentId())->latest()->first();
        if (!$latest) {
            return 1;
        }
        return $latest->service_id + 1;
    }

    public function quotationItem(Request $request)
    {
        $itemData = Item::find($request->item_id);
        return json_encode($itemData);
    }
}
