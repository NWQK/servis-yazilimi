<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoicePayment;
use App\Models\InvoiceService;
use App\Models\Item;
use App\Models\Notification;
use App\Models\Service;
use App\Models\ServiceItem;
use App\Models\ServiceType;
use App\Models\Tax;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\InventoryAccounting;
use Illuminate\Support\Facades\Crypt;
use Srmklive\PayPal\Services\PayPal;
use Stripe\Charge;
use Stripe\Stripe;

class InvoiceController extends Controller
{

    public function index()
    {
        if (\Auth::user()->can('manage invoice')) {
            if (auth()->user()->type == 'client') {
                $invoices = Invoice::where('client', '=', auth()->user()->id)->orderBy('id', 'desc')->get();
            } else {
                $invoices = Invoice::where('parent_id', '=', parentId())->orderBy('id', 'desc')->get();
            }
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
        return view('invoice.index', compact('invoices'));
    }


    public function create()
    {

        $clients = User::where('type', 'client')->where('parent_id', parentId())->get()->pluck('name', 'id');
        $clients->prepend(__('Select Client'), '');

        $items = Item::where('parent_id', parentId())->get()->pluck('title', 'id');
        $items->prepend(__('Select Item'), '');

        $invoiceId = $this->invoiceNumber();

        $taxs = Tax::where('parent_id', parentId())->get()->pluck('title', 'id');
        $types = ServiceType::where('parent_id', parentId())->pluck('type', 'id');
        $types->prepend(__('Select service type'), '');

        return view('invoice.create', compact('clients', 'invoiceId', 'items', 'taxs', 'types'));
    }


    public function store(Request $request)
    {
        if (\Auth::user()->can('create invoice')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'invoice_date' => 'required',
                    'client' => 'required',
                    'service' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $invoice = app(InventoryAccounting::class)->transaction(parentId(), function () use ($request) {
            $invoice = new Invoice();
            $invoice->invoice_id = $this->invoiceNumber();
            $invoice->invoice_date = $request->invoice_date;
            $invoice->client = $request->client;
            $invoice->service = $request->service;
            $invoice->status = 0;
            $invoice->parent_id = parentId();
            $invoice->save();

            $totalAmount = 0;

            app(InventoryAccounting::class)->sync($invoice, $this->stockRows($request));

            $serviceType = $request->types ?? [];
            for ($i = 0; $i < count($serviceType); $i++) {
                $invoiceService = new InvoiceService();
                $invoiceService->invoice_id = $invoice->id;
                $invoiceService->tax = !empty($serviceType[$i]['tax']) ? implode(',', (array) $serviceType[$i]['tax']) : null;
                $invoiceService->service_type = $serviceType[$i]['service_type'];
                $invoiceService->rate = $serviceType[$i]['rate'];
                $invoiceService->note = $serviceType[$i]['note'] ?? null;
                $invoiceService->parent_id = parentId();
                $invoiceService->save();
            }

                return $invoice;
            });

            $setting = settings();

            triggerN8n('create_invoice', [
                'invoice_id' => $invoice->id,
                'invoice_no' => $invoice->invoice_id,
                'invoice_date' => $invoice->invoice_date,
                'status' => $invoice->status,
                'total_amount' => $invoice->getInvoiceAllTotalAmount(),
                'client_id' => $invoice->client,
                'client_name' => !empty($invoice->clients) ? $invoice->clients->name : '-',
                'client_email' => !empty($invoice->clients) ? $invoice->clients->email : '-',
                'client_phone' => !empty($invoice->clients) ? $invoice->clients->phone_number : '-',
                'service_id' => $invoice->service,
                'company_name' => $setting['company_name'],
                'company_email' => $setting['company_email'],
                'company_phone' => $setting['company_phone'],
            ]);

            $module = 'invoice_create';
            $notification = Notification::where('parent_id', parentId())->where('module', $module)->first();
            $errorMessage = '';

            if (!empty($notification)) {
                $notificationResponse = MessageReplace($notification, $invoice->id);
                $data['subject'] = $notificationResponse['subject'];
                $data['message'] = $notificationResponse['message'];
                $data['module'] = $module;
                $data['logo'] = $setting['company_logo'];
                $to = $invoice->clients->email;

                if ($notification->enabled_email == 1 && !empty($to)) {
                    $response = commonEmailSend($to, $data);
                    if ($response['status'] == 'error') {
                        $errorMessage = $response['message'];
                    }
                }
                if ($notification->enabled_sms == 1) {
                    $twilio_sid = getSettingsValByName('twilio_sid');
                    if (!empty($twilio_sid)) {
                        send_twilio_msg($invoice->clients->phone_number, $notificationResponse['sms_message']);
                    }
                }
                if ($notification->enabled_whatsapp == 1 && !empty($invoice->clients->phone_number)) {
                    sendWhatshappSms([
                        'to' => $invoice->clients->phone_number,
                        'body' => $notificationResponse['sms_message']
                    ]);
                }
            }

            return redirect()->route('invoice.show', \Crypt::encrypt($invoice->id))
                ->with('success', __('Invoice successfully created.') . '</br>' . $errorMessage);

        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function show($ids)
    {
        $id = Crypt::decrypt($ids);
        $invoice = Invoice::where('parent_id', parentId())->findOrFail($id);
        $settings = settings();
        $status = Invoice::statues();
        $invoicePaymentSettings = invoicePaymentSettings(auth()->user()->parent_id);
        return view('invoice.view', compact('invoice', 'settings', 'status', 'invoicePaymentSettings'));
    }


    public function edit($id)
    {
        $id = decrypt($id);
        if (!\Auth::user()->can('edit invoice')) {
            return redirect()->back()->with('error', 'Permission denied');
        }
        $invoice = Invoice::where('parent_id', parentId())->findOrFail($id);
        $clients = User::where('type', 'client')->where('parent_id', parentId())->get()->pluck('name', 'id');
        $clients->prepend(__('Select Client'), '');

        $items = Item::where('parent_id', parentId())->get()->pluck('title', 'id');
        foreach ($invoice->items as $line) {
            if (!$items->has($line->item)) $items->put($line->item, $line->item_title);
        }
        $items->prepend(__('Select Item'), '');

        $invoiceId = $this->invoiceNumber();

        $taxs = Tax::where('parent_id', parentId())->get()->pluck('title', 'id');
        $types = ServiceType::where('parent_id', parentId())->pluck('type', 'id');
        $types->prepend(__('Select service type'), '');
        $selectedService = $invoice->service ?? null;  // or $invoice->service ?? null
        $existingTypes = $invoice->types->map(function ($type) {
            return [
                'id' => $type->id,
                'service_type' => $type->service_type,
                'rate' => $type->rate,
                'tax' => $type->tax ? explode(',', $type->tax) : [],
                'note' => $type->note,
            ];
        })->toArray();
        $services = Service::where('client', $invoice->client)->get();
        $serviceData = [];
        foreach ($services as $service) {
            $serv['id'] = $service->id;
            $serv['name'] = servicePrefix() . $service->service_id . ' | ' . $service->vehicles->license_plate;
            $serviceData[] = $serv;
        }

        return view('invoice.edit', compact('clients', 'invoice', 'invoiceId', 'taxs', 'items', 'types', 'existingTypes', 'selectedService', 'serviceData'));
    }


    public function update(Request $request, $id)
    {
        $id = decrypt($id);

        if (\Auth::user()->can('edit invoice')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'invoice_date' => 'required',
                    'client' => 'required',
                    'service' => 'required',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $invoice = app(InventoryAccounting::class)->transaction(parentId(), function () use ($request, $id) {
                $invoice = Invoice::where('parent_id', parentId())->lockForUpdate()->findOrFail($id);
                $invoice->client = $request->client;
                $invoice->service = $request->service;
                $invoice->invoice_date = $request->invoice_date;
                $invoice->save();
            $existingTypeIds = InvoiceService::where('invoice_id', $invoice->id)->pluck('id')->toArray();
            $updatedTypeIds = [];

            if (!empty($request->types)) {

                foreach ($request->types as $key => $type) {

                    $tax = isset($type['tax']) ? implode(',', $type['tax']) : null;

                    $serviceType = InvoiceService::updateOrCreate(
                        [
                            'id' => $type['id'] ?? null,
                            'invoice_id' => $invoice->id
                        ],
                        [
                            'service_type' => $type['service_type'] ?? null,
                            'rate' => $type['rate'] ?? 0,
                            'tax' => $tax,
                            'note' => $type['note'] ?? null,
                            'parent_id' => parentId()
                        ]
                    );

                    $updatedTypeIds[] = $serviceType->id;
                }
            }

            $typesToDelete = array_diff($existingTypeIds, $updatedTypeIds);

            if (!empty($typesToDelete)) {
                InvoiceService::whereIn('id', $typesToDelete)->delete();
            }

                app(InventoryAccounting::class)->sync($invoice, $this->stockRows($request));
                app(InventoryAccounting::class)->refreshStatus($invoice);
                return $invoice;
            });

            return redirect()->route('invoice.index', $invoice->id)
                ->with('success', __('Invoice successfully updated.'));

        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    public function destroy(Invoice $invoice)
    {
        abort_unless(auth()->user()->can('delete invoice'), 403);
        app(InventoryAccounting::class)->transaction(parentId(), function () use ($invoice) {
            $invoice = Invoice::where('parent_id', parentId())->lockForUpdate()->findOrFail($invoice->id);
            app(InventoryAccounting::class)->sync($invoice, []);
            $invoice->types()->delete();
            $invoice->payments()->delete();
            $invoice->delete();
        });
        return redirect()->route('invoice.index')->with('success', __('Invoice successfully deleted.'));
    }


    function invoiceNumber()
    {
        $lastInvoice = Invoice::where('parent_id', parentId())->latest()->first();
        if (!$lastInvoice) {
            return 1;
        }
        return $lastInvoice->invoice_id + 1;
    }

    public function item(Request $request)
    {

        $itemData['item'] = $itemDetails = Item::where('parent_id', parentId())->findOrFail($request->item_id);
        $itemData['unit'] = (!empty($itemDetails->unit)) ? $itemDetails->unit->name : '';
        $itemData['taxRate'] = $itemDetails->taxRate($itemDetails->taxs);
        $itemData['taxes'] = $itemDetails->taxes($itemDetails->taxs);
        $salePrice = $itemDetails->sales_price;
        $quantity = 1;
        $itemData['totalAmount'] = ($salePrice * $quantity);
        return json_encode($itemData);
    }

    public function product(Request $request)
    {
        $itemDetails = InvoiceItem::where('parent_id', parentId())->where('invoice_id', $request->invoice_id)->where('item', $request->item_id)->first();
        return json_encode($itemDetails);
    }

    public function itemDestroy(Request $request)
    {
        abort_unless(auth()->user()->can('edit invoice') || auth()->user()->can('delete invoice'), 403);
        $line = InvoiceItem::where('parent_id', parentId())->find($request->id);
        if ($line) app(InventoryAccounting::class)->remove(parentId(), $line->invoice_id, $line->id);
        return redirect()->back()->with('success', __('Invoice item successfully deleted.'));
    }


    public function statusChange(Request $request, $id)
    {
        $status = $request->status;
        $invoice = Invoice::where('parent_id', parentId())->findOrFail($id);
        $invoice->status = $status;
        $invoice->save();
        return redirect()->back()->with('success', __('Invoice status changed successfully.'));
    }

    public function payment($invoice_id)
    {
        if (\Auth::user()->can('create invoice payment')) {

            $invoice = Invoice::where('parent_id', parentId())->findOrFail($invoice_id);
            if (auth()->user()->type == 'client') {
                $settings = invoicePaymentSettings(auth()->user()->parent_id);
                return view('invoice.client_payment', compact('invoice', 'settings'));
            } else {
                return view('invoice.payment', compact('invoice'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function createPayment(Request $request, $invoice_id)
    {
        if (\Auth::user()->can('create invoice payment')) {

            $invoice = Invoice::find($invoice_id);
            $dueAmount = $invoice->getInvoiceTotalDueAmount();
            $validator = \Validator::make(
                $request->all(),
                [
                    'payment_date' => 'required',
                    'amount' => 'required|numeric|min:1|max:' . $dueAmount,
                ],

            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }
            $invoicePayment = new InvoicePayment();
            $invoicePayment->transaction_id = uniqid(1);
            $invoicePayment->invoice_id = $invoice_id;
            $invoicePayment->payment_date = $request->payment_date;
            $invoicePayment->amount = $request->amount;
            $invoicePayment->payment_type = 'Manual';
            $invoicePayment->payment_status = 'success';
            $invoicePayment->description = $request->description;
            $invoicePayment->parent_id = parentId();
            $invoicePayment->save();
            $invoice = Invoice::where('id', $invoice_id)->first();
            $due = $invoice->getInvoiceTotalDueAmount();


            if ($due <= 0) {
                $invoice->status = 2;
                $invoice->save();
            } else {
                $invoice->status = 1;
                $invoice->save();
            }

            if ($invoice->status == 0) {
                $status = 'Unpaid';
            }
            if ($invoice->status == 2) {
                $status = 'Paid';
            }
            if ($invoice->status == 1) {
                $status = 'Partialy Paid';
            }
            $setting = settings();

            $n8nData = [
                'transaction_id' => $invoicePayment->transaction_id,
                'invoice_id' => $invoice_id,
                'payment_date' => $request->payment_date,
                'amount' => $request->amount,
                'payment_type' => 'Manual',
                'description' => $request->description,
                'invoice_status' => $invoice->status,
                'due_amount' => $due,
                'client_name' => !empty($invoice->clients) ? $invoice->clients->name : '',
                'client_email' => $invoice->clients->email,
                'client_phone' => $invoice->clients->phone_number,
                'company_logo' => $setting['company_logo'],
                'parent_id' => parentId(),
            ];

            triggerN8n('new_payment', $n8nData);
            $module = 'payment_create';
            $notification = Notification::where('parent_id', parentId())->where('module', $module)->first();
            $notification->status = $status;
            $errorMessage = '';

            if (!empty($notification)) {
                $notificationResponse = MessageReplace($notification, $invoice->id);
                $data['subject'] = $notificationResponse['subject'];
                $data['message'] = $notificationResponse['message'];
                $data['module'] = $module;
                $data['logo'] = $setting['company_logo'];
                $to = $invoice->clients->email;

                if ($notification->enabled_email == 1 && !empty($to)) {
                    $response = commonEmailSend($to, $data);
                    if ($response['status'] == 'error') {
                        $errorMessage = $response['message'];
                    }
                }
                if ($notification->enabled_sms == 1) {
                    $twilio_sid = getSettingsValByName('twilio_sid');
                    if (!empty($twilio_sid)) {
                        send_twilio_msg($invoice->clients->phone_number, $notificationResponse['sms_message']);
                    }
                }
                if ($notification->enabled_whatsapp == 1 && !empty($invoice->clients->phone_number)) {
                    sendWhatshappSms([
                        'to' =>$invoice->clients->phone_number,
                        'body' => $notificationResponse['sms_message']
                    ]);
                }
            }

            return redirect()->back()->with('success', __('Payment successfully created.') . '</br>' . $errorMessage);
        }
    }

    public function paymentDestroy(Request $request, $invoice_id, $payment_id)
    {
        if (\Auth::user()->can('delete invoice payment')) {
            InvoicePayment::where('id', $payment_id)->delete();
            $invoice = Invoice::where('id', $invoice_id)->first();
            $due = $invoice->getInvoiceTotalDueAmount();
            $total = $invoice->getInvoiceAllTotalAmount();
            if ($due > 0 && $total != $due) {
                $invoice->status = 2;
            } else {
                $invoice->status = 0;
            }
            $invoice->save();
            return redirect()->back()->with('success', __('Payment successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function invoiceItem($invoice_id)
    {
        if (\Auth::user()->can('create invoice')) {
            $invoice = Invoice::where('id', $invoice_id)->first();
            $invoiceItems = Item::where('parent_id', parentId())->get()->pluck('title', 'id');
            $invoiceItems->prepend(__('Select Item'), '');
            return view('invoice.create_item', compact('invoice', 'invoiceItems'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function invoiceItemStore(Request $request, $invoice_id)
    {
        abort_unless(auth()->user()->can('create invoice'), 403);
        $request->validate(['item' => 'required|integer', 'quantity' => 'required|integer|min:1', 'description' => 'nullable|string|max:255']);
        app(InventoryAccounting::class)->transaction(parentId(), function () use ($request, $invoice_id) {
            $invoice = Invoice::where('parent_id', parentId())->lockForUpdate()->findOrFail($invoice_id);
            app(InventoryAccounting::class)->add($invoice, $request->only(['item', 'quantity', 'description']));
            app(InventoryAccounting::class)->refreshStatus($invoice);
        });
        return redirect()->back()->with('success', __('Invoice item successfully created.'));
    }


    public function invoiceItemDestroy(Request $request, $invoice_id, $itemId)
    {
        abort_unless(auth()->user()->can('delete invoice'), 403);
        app(InventoryAccounting::class)->remove(parentId(), (int) $invoice_id, (int) $itemId);
        return redirect()->back()->with('success', __('Invoice item successfully deleted.'));
    }


    public function paymentSettings()
    {
        $paymentSetting = invoicePaymentSettings(parentId());
        return $paymentSetting;
    }

    public function invoiceStripePayment(Request $request, $ids)
    {
        $settings = $this->paymentSettings();
        $id = decrypt($ids);
        $invoice = Invoice::where('parent_id', parentId())->findOrFail($id);
        $amount = $request->amount;
        if ($invoice) {
            try {
                $transactionID = uniqid('', true);
                Stripe::setApiKey($settings['STRIPE_SECRET']);
                $data = Charge::create(
                    [
                        "amount" => 100 * $amount,
                        "currency" => $settings['CURRENCY'],
                        "source" => $request->stripeToken,
                        "description" => " Invoice - " . invoicePrefix() . $invoice->invoice_id,
                        "metadata" => ["order_id" => $transactionID],
                        'shipping' => [
                            'name' => $request->name,
                            'address' => [
                                'line1' => $request->state ?? 'NA',
                                'city' => $request->city ?? 'NA',
                                'postal_code' => $request->zipcode ?? '000000',
                                'country' => $request->country ?? 'NA',
                            ]
                        ],
                    ]
                );

                if ($data['amount_refunded'] == 0 && empty($data['failure_code']) && $data['paid'] == 1 && $data['captured'] == 1) {

                    if ($data['status'] == 'succeeded') {

                        $payment['invoice_id'] = $invoice->id;
                        $payment['transaction_id'] = $transactionID;
                        $payment['payment_type'] = 'Stripe';
                        $payment['amount'] = $amount;
                        $payment['receipt'] = isset($data['receipt_url']) ? $data['receipt_url'] : '';
                        $payment['notes'] = " Invoice - " . invoicePrefix() . $invoice->invoice_id;

                        Invoice::addPayment($payment);
                        return redirect()->back()->with('success', __('Invoice payment successfully completed.'));
                    } else {
                        return redirect()->back()->with('error', __('Your payment has failed.'));
                    }
                } else {
                    return redirect()->back()->with('error', __('Transaction has been failed.'));
                }
            } catch (\Exception $e) {
                return redirect()->back()->with('error', __($e->getMessage()));
            }
        } else {
            return redirect()->back()->with('error', __('Invoice is deleted.'));
        }
    }


    public function invoicePaypal(Request $request, $id)
    {
        $invoiceId = decrypt($id);
        $paypalSetting = $this->paymentSettings();

        if ($paypalSetting['paypal_mode'] == 'live') {
            config([
                'paypal.live.client_id' => isset($paypalSetting['paypal_client_id']) ? $paypalSetting['paypal_client_id'] : '',
                'paypal.live.client_secret' => isset($paypalSetting['paypal_secret_key']) ? $paypalSetting['paypal_secret_key'] : '',
                'paypal.mode' => isset($paypalSetting['paypal_mode']) ? $paypalSetting['paypal_mode'] : '',
                'paypal.currency' => isset($paypalSetting['CURRENCY']) ? $paypalSetting['CURRENCY'] : '',
            ]);
        } else {
            config([
                'paypal.sandbox.client_id' => isset($paypalSetting['paypal_client_id']) ? $paypalSetting['paypal_client_id'] : '',
                'paypal.sandbox.client_secret' => isset($paypalSetting['paypal_secret_key']) ? $paypalSetting['paypal_secret_key'] : '',
                'paypal.mode' => isset($paypalSetting['paypal_mode']) ? $paypalSetting['paypal_mode'] : '',
                'paypal.currency' => isset($paypalSetting['CURRENCY']) ? $paypalSetting['CURRENCY'] : '',
            ]);
        }

        $provider = new Paypal();
        $provider->setApiCredentials(config('paypal'));

        $paypalToken = $provider->getAccessToken();

        session(['paypal_invoice_amount_' . $invoiceId => $request->amount]);

        $response = $provider->createOrder([
            "intent" => "CAPTURE",
            "application_context" => [
                "return_url" => route('invoice.paypal.status', [$invoiceId, 'success']),
                "cancel_url" => route('invoice.paypal.status', [$invoiceId, 'cancel']),
            ],
            "purchase_units" => [
                0 => [
                    "amount" => [
                        "currency_code" => isset($paypalSetting['CURRENCY']) ? $paypalSetting['CURRENCY'] : '',
                        "value" => $request->amount
                    ]
                ]
            ]
        ]);
        if (isset($response['id']) && $response['id'] != null) {
            // redirect to approve href
            foreach ($response['links'] as $links) {
                if ($links['rel'] == 'approve') {
                    return redirect()->away($links['href']);
                }
            }
            return redirect()
                ->back()
                ->with('error', 'Something went wrong.');
        } else {
            return redirect()
                ->back()
                ->with('error', $response['message'] ?? 'Something went wrong.');
        }
    }

    public function invoicePaypalStatus(Request $request, $invoiceId, $status)
    {
        $paypalSetting = $this->paymentSettings();

        config([
            'paypal.mode' => $paypalSetting['paypal_mode'],
            'paypal.sandbox.client_id' => $paypalSetting['paypal_client_id'],
            'paypal.sandbox.client_secret' => $paypalSetting['paypal_secret_key'],
            'paypal.live.client_id' => $paypalSetting['paypal_client_id'],
            'paypal.live.client_secret' => $paypalSetting['paypal_secret_key'],
        ]);

        if ($status != 'success') {
            return redirect()->back()->with('error', __('Transaction has been failed.'));
        }


        $invoice = Invoice::find($invoiceId);
        $payAmount = session('paypal_invoice_amount_' . $invoiceId);


        if (!$invoice) {
            return redirect()->back()->with('error', __('Invoice not found.'));
        }

        $dueAmount = $invoice->getInvoiceTotalDueAmount();
        $payAmount = session('paypal_invoice_amount_' . $invoiceId);

        if (!$payAmount || $payAmount <= 0) {
            return redirect()->back()->with('error', __('Invalid payment amount.'));
        }

        if ($payAmount > $dueAmount) {
            return redirect()->back()->with('error', __('Payment amount exceeds due amount.'));
        }

        $provider = new PayPal();
        $provider->setApiCredentials(config('paypal'));
        $provider->getAccessToken();

        $response = $provider->capturePaymentOrder($request->token);

        if (isset($response['status']) && $response['status'] == 'COMPLETED') {

            $transactionID = $response['purchase_units'][0]['payments']['captures'][0]['id'];

            $payment = [
                'invoice_id' => $invoiceId,
                'transaction_id' => $transactionID,
                'payment_type' => 'Paypal',
                'amount' => $payAmount,
                'receipt' => '',
                'notes' => "Invoice - " . invoicePrefix() . $invoice->invoice_id
            ];

            Invoice::addPayment($payment);

            return redirect()->back()->with('success', __('Invoice payment successfully completed.'));
        }
        if (isset($response['error']) && $response['error']['name'] == 'INSTRUMENT_DECLINED') {
            foreach ($response['error']['links'] as $link) {
                if ($link['rel'] == 'redirect') {
                    return redirect()->away($link['href']);
                }
            }
        }
        return redirect()->back()->with('error', $response['message'] ?? __('Something went wrong.'));
    }

    public function banktransferPayment(Request $request, $id)
    {
        $invoiceId = decrypt($id);
        $validator = \Validator::make(
            $request->all(),
            [
                'receipt' => 'required',
                'amount' => 'required',
            ]
        );
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();

            return redirect()->back()->with('error', $messages->first());
        }



        if ($request->hasFile('receipt')) {
            $invoicenameWithExt = $request->file('receipt')->getClientOriginalName();
            $invoicename = pathinfo($invoicenameWithExt, PATHINFO_FILENAME);
            $tenantExtension = $request->file('receipt')->getClientOriginalExtension();
            $invoiceName = $invoicename . '_' . time() . '.' . $tenantExtension;
            $dir = storage_path('upload/receipt');
            if (!file_exists($dir)) {
                mkdir($dir, 0777, true);
            }
            $request->file('receipt')->storeAs('upload/receipt/', $invoiceName);
        }

        $invoice = Invoice::find($invoiceId);
        $transactionID = uniqid('', true);

        $payment['invoice_id'] = $invoice->id;
        $payment['transaction_id'] = $transactionID;
        $payment['receipt'] = $invoicename;
        $payment['payment_type'] = 'Bank Transfer';
        $payment['amount'] = $request->amount;
        $payment['notes'] = $request->notes;
        $payment['payment_status'] = 'pending';
        $payment['payment_date'] = date('Y-m-d');
        $payment['parent_id'] = parentId();


        InvoicePayment::insert($payment);
        return redirect()->back()->with('success', __('Invoice payment successfully completed.'));
    }
    public function invoicePaymentStatus($id, $status)
    {
        $order = InvoicePayment::find($id);
        $invoice = Invoice::find($order->invoice_id);
        if ($status == 'accept') {
            $due = $invoice->getInvoiceTotalDueAmount();
            $total = $invoice->amount;
            $status = $due <= 0 ? 2 : ($due == $total ? 0 : 1);
            Invoice::statusChange($invoice->id, $status);
            $order->payment_status = 'success';
            $order->save();
        } else {
            $order->payment_status = 'reject';
            $order->save();
        }
        return redirect()->back()->with('success', __('Invoice payment status is ' . $status));
    }


    public function invoiceFlutterwave(Request $request, $invoice_id, $pay_id)
    {
        $invoiceID = decrypt($invoice_id);
        $invoice = Invoice::find($invoiceID);
        $paymentSetting = $this->paymentSettings();

        if ($invoice) {
            try {
                $detail = [
                    'txref' => $pay_id,
                    'SECKEY' => $paymentSetting['flutterwave_secret_key'],
                ];
                $url = "https://api.ravepay.co/flwv3-pug/getpaidx/api/v2/verify";
                $headersData = ['Content-Type' => 'application/json'];
                $bodyData = \Unirest\Request\Body::json($detail);
                $responseData = \Unirest\Request::post($url, $headersData, $bodyData);

                if (!empty($responseData)) {
                    $responseData = json_decode($responseData->raw_body, true);
                }

                if (isset($responseData['status']) && $responseData['status'] == 'success') {
                    $amountPaid = $responseData['data']['amount'];
                    $expectedAmount = $request->query('amount'); // Get amount from request

                    if ($amountPaid < $expectedAmount) {
                        return redirect()->back()->with('error', __('Payment amount mismatch! Expected: ') . $expectedAmount);
                    }

                    $invoiceTransId = uniqid('', true);
                    Invoice::addPayment([
                        'invoice_id' => $invoice->id,
                        'transaction_id' => $invoiceTransId,
                        'payment_type' => 'Flutterwave',
                        'amount' => $amountPaid,
                        'notes' => $request->notes ?? 'Flutterwave Payment',
                    ]);

                    return redirect()->back()->with('success', __('Invoice payment successfully completed.'));
                } else {
                    return redirect()->back()->with('error', __('Transaction failed!'));
                }
            } catch (\Exception $e) {
                return redirect()->back()->with('error', $e->getMessage());
            }
        }
    }

    public function invoicePaystack(Request $request, $ids)
    {
        $payment_setting = $this->paymentSettings();
        $currency = $payment_setting['CURRENCY'] ?? 'USD';
        $id = Crypt::decrypt($ids);
        $invoice = Invoice::where('parent_id', parentId())->findOrFail($id);

        if (!$invoice) {
            return response()->json([
                'flag' => 0,
                'message' => __('Invoice not found.')
            ]);
        }

        $amount = $request->amount;
        if ($amount <= 0) {
            return response()->json([
                'flag' => 0,
                'message' => __('Amount must be greater than 0.')
            ]);
        }

        return response()->json([
            'flag' => 1,
            'email' => auth()->user()->email,
            'total_price' => $amount,
            'currency' => $currency,
        ]);
    }


    public function invoicePaystackStatus(Request $request, $pay_id, $invoice_id_encrypted)
    {
        try {
            $invoice = Invoice::find(Crypt::decrypt($invoice_id_encrypted));
            if (!$invoice) {
                return redirect()->back()->with('error', __('Invoice not found.'));
            }

            $secretKey = $this->paymentSettings()['paystack_secret_key'] ?? '';
            $verifyUrl = "https://api.paystack.co/transaction/verify/$pay_id";

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $verifyUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $secretKey],
            ]);
            $response = curl_exec($ch);
            curl_close($ch);
            $result = $response ? json_decode($response, true) : [];

            if (!($result['status'] ?? false) || ($result['data']['status'] !== 'success')) {
                return redirect()->back()->with('error', __('Transaction failed or cancelled.'));
            }

            $payment = [
                'invoice_id' => $invoice->id,
                'transaction_id' => uniqid('', true),
                'payment_type' => 'Paystack',
                'amount' => $result['data']['amount'] / 100,
                'receipt' => '',
                'notes' => 'Paystack Payment',
            ];

            Invoice::addPayment($payment);

            return redirect()->back()->with('success', __('Invoice payment successfully completed.'));
        } catch (\Exception $e) {
            return redirect()->back()->with('error', __('Something went wrong while verifying the payment.'));
        }
    }

    public function getServiceType(Request $request, $id)
    {
        // Step 1: Get all service items for this service
        $serviceItems = ServiceItem::where('service_id', $id)->get();

        // Step 2: Extract all type_ids from those service items
        $typeIds = $serviceItems->pluck('type_id');

        // Step 3: Get all service types where id is in those type_ids
        $serviceTypes = ServiceType::whereIn('id', $typeIds)->get();

        return response()->json($serviceTypes);
    }

    private function stockRows(Request $request): array
    {
        $request->validate([
            'item' => 'nullable|array', 'item.*' => 'nullable|integer',
            'quantity' => 'nullable|array', 'item_id' => 'nullable|array',
            'item_id.*' => 'nullable|integer', 'description.*' => 'nullable|string|max:255',
        ]);
        $rows = [];
        foreach ($request->input('item', []) as $key => $item) {
            if (empty($item)) continue;
            $rows[] = [
                'id' => $request->input("item_id.$key"),
                'item' => $item, 'quantity' => $request->input("quantity.$key"),
                'tax' => implode(',', (array) $request->input("tax.$key", [])),
                'description' => $request->input("description.$key"),
            ];
        }
        return $rows;
    }
}
