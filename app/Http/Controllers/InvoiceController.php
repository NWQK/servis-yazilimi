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


    public function debts()
    {
        abort_unless(auth()->user()->type !== 'client' && auth()->user()->can('manage invoice'), 403);

        // Status labels can be stale; the remaining balance is the source of truth.
        $debts = Invoice::where('parent_id', parentId())
            ->with(['items', 'types', 'payments', 'clients'])
            ->orderBy('invoice_date')->orderBy('id')->get()
            ->map(function (Invoice $invoice) {
                $total = $invoice->getInvoiceAllTotalAmount();
                $due = round($invoice->getInvoiceTotalDueAmount(), 2);
                return ['invoice' => $invoice, 'total' => $total, 'paid' => round($total - $due, 2), 'due' => $due];
            })->filter(fn ($row) => $row['due'] > 0)->values();

        $totalDue = round($debts->sum('due'), 2);
        return view('invoice.debts', compact('debts', 'totalDue'));
    }


    public function create()
    {

        $clients = User::where('type', 'client')->whereNull('client_archived_at')->where('parent_id', parentId())->get()->pluck('name', 'id');
        $clients->prepend(__('Select Client'), '');

        $items = Item::where('parent_id', parentId())->get()->pluck('title', 'id');
        $items->prepend(__('Select Item'), '');

        $invoiceId = $this->invoiceNumber();

        $taxs = Tax::where('parent_id', parentId())->get()->pluck('title', 'id');
        $types = ServiceType::where('parent_id', parentId())->pluck('type', 'id');
        $types->prepend(__('Select service type'), '');

        return view('invoice.create', compact('clients', 'invoiceId', 'items', 'taxs', 'types') + $this->itemSelectionData());
    }


    public function store(Request $request)
    {
        if (\Auth::user()->can('create invoice')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'invoice_date' => 'required',
                    'discount_amount' => Invoice::DISCOUNT_RULE,
                    'client' => 'required',
                    'service' => ['required', \Illuminate\Validation\Rule::exists('services', 'id')->where('parent_id', parentId())->where('client', $request->client)],
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $billing = \App\Services\InvoiceBilling::validated($request);
            $invoice = app(InventoryAccounting::class)->transaction(parentId(), function () use ($request, $billing) {
            $invoice = new Invoice();
            $invoice->customer_details = $request->exists('billing') ? $billing : null;
            $invoice->invoice_id = $this->invoiceNumber();
            $invoice->invoice_date = $request->invoice_date;
            if ($request->exists('discount_amount')) $invoice->discount_amount = $request->discount_amount ?? 0;
            $invoice->client = $request->client;
            $invoice->service = $request->service;
            $laborService = Service::where('parent_id', parentId())->findOrFail($request->service);
            $invoice->external_labor_amount = $laborService->external_labor_amount;
            \App\Services\ExternalLabor::copyTax($laborService, $invoice);
            $invoice->status = 0;
            $invoice->parent_id = parentId();
            $invoice->save();
            $request->attributes->set('created_invoice_id',$invoice->id);

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

            app(\App\Services\InvoiceCustomerEmail::class)->send($invoice);
            $errorMessage = '';

            return redirect()->route('invoice.show', \Crypt::encrypt($invoice->id))
                ->with('success', __('Invoice successfully created.') . '</br>' . $errorMessage);

        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function show($ids)
    {
        abort_unless(auth()->user()->can('show invoice'), 403);
        $id = Crypt::decrypt($ids);
        $invoice = Invoice::where('parent_id', parentId())
            ->when(auth()->user()->type === 'client', fn($query)=>$query->where('client',auth()->id()))
            ->findOrFail($id);
        $settings = settings();
        $status = Invoice::statues();
        $invoicePaymentSettings = invoicePaymentSettings(auth()->user()->parent_id);
        return view('invoice.view', compact('invoice', 'settings', 'status', 'invoicePaymentSettings'));
    }


    public function edit($id)
    {
        $id = decrypt($id);
        if (!\Auth::user()->can('edit invoice')) {
            return redirect()->back()->with('error', __('Permission denied'));
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
        $services = Service::where('parent_id', parentId())->where('client', $invoice->client)->get();
        $serviceData = [];
        foreach ($services as $service) {
            $serv['id'] = $service->id;
            $serv['external_labor_amount'] = $service->external_labor_amount;
            $serv['external_labor_tax_rate'] = (float) $service->external_labor_tax_rate;
            $serv['name'] = servicePrefix() . $service->service_id . ' | ' . $service->vehicles->license_plate;
            $serviceData[] = $serv;
        }

        return view('invoice.edit', compact('clients', 'invoice', 'invoiceId', 'taxs', 'items', 'types', 'existingTypes', 'selectedService', 'serviceData') + $this->itemSelectionData($invoice));
    }


    public function update(Request $request, $id)
    {
        $id = decrypt($id);

        if (\Auth::user()->can('edit invoice')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'invoice_date' => 'required',
                    'discount_amount' => Invoice::DISCOUNT_RULE,
                    'client' => 'required',
                    'service' => ['required', \Illuminate\Validation\Rule::exists('services', 'id')->where('parent_id', parentId())->where('client', $request->client)],
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $billing = \App\Services\InvoiceBilling::validated($request);
            $invoice = app(InventoryAccounting::class)->transaction(parentId(), function () use ($request, $id, $billing) {
                $invoice = Invoice::where('parent_id', parentId())->lockForUpdate()->findOrFail($id);
                if ((int) $invoice->client !== (int) $request->client) {
                    $invoice->customer_details = array_replace(
                        \App\Services\InvoiceBilling::customer(parentId(), (int) $request->client),
                        array_filter($billing, fn ($value) => $value !== null && $value !== '')
                    );
                } elseif ($request->exists('billing')) {
                    $invoice->customer_details = $billing;
                }
                $previousTotal = $invoice->getInvoiceAllTotalAmount();
                $invoice->client = $request->client;
                $invoice->service = $request->service;
                $laborService = Service::where('parent_id', parentId())->findOrFail($request->service);
            $invoice->external_labor_amount = $laborService->external_labor_amount;
            \App\Services\ExternalLabor::copyTax($laborService, $invoice);
                $invoice->invoice_date = $request->invoice_date;
            if ($request->exists('discount_amount')) $invoice->discount_amount = $request->discount_amount ?? 0;
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

                app(InventoryAccounting::class)->sync($invoice, $this->stockRows($request), $previousTotal);
                app(InventoryAccounting::class)->refreshStatus($invoice);
                return $invoice;
            });

            return redirect()->route('invoice.index', $invoice->id)
                ->with('success', __('Invoice successfully updated.'));

        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    public function deleteConfirmation(int $id)
    {
        abort_unless(auth()->user()->can('delete invoice'), 403);
        $invoice = Invoice::where('parent_id', parentId())->with('items')->findOrFail($id);
        return view('invoice.delete', compact('invoice'));
    }

    public function destroy(Request $request, $invoice)
    {
        abort_unless(auth()->user()->can('delete invoice'), 403);
        $request->validate(['return_stock'=>'required|boolean']);
        $invoiceId=$invoice instanceof Invoice ? $invoice->id : (int)$invoice;
        app(InventoryAccounting::class)->transaction(parentId(), function () use ($invoiceId, $request) {
            $invoice = Invoice::where('parent_id', parentId())->lockForUpdate()->findOrFail($invoiceId);
            if ($request->boolean('return_stock')) app(InventoryAccounting::class)->sync($invoice, []);
            else $invoice->items()->delete();
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
        abort_unless(auth()->user()->can('edit invoice'),403);
        $invoice=Invoice::where('parent_id',parentId())->lockForUpdate()->findOrFail($id);
        app(InventoryAccounting::class)->refreshStatus($invoice);
        return back()->with('success','Fatura durumu ödemelere göre güncellendi.');
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
        abort_unless(auth()->user()->can('create invoice payment'),403);
        $invoice=Invoice::where('parent_id',parentId())->lockForUpdate()->findOrFail($invoice_id);
        $due=round($invoice->getInvoiceTotalDueAmount(),2);
        $data=$request->validate(['payment_date'=>'required|date','amount'=>['required','numeric','min:0.01','max:'.$due,'regex:/^\d+(\.\d{1,2})?$/'],'description'=>'nullable|string|max:2000']);
        $payment=new InvoicePayment();
        $payment->transaction_id=(string)\Illuminate\Support\Str::uuid();
        $payment->invoice_id=$invoice->id; $payment->parent_id=parentId();
        $payment->payment_date=$data['payment_date']; $payment->amount=$data['amount'];
        $payment->payment_type='Manual'; $payment->payment_status='success'; $payment->description=$data['description']??null;
        $payment->save();
        app(InventoryAccounting::class)->refreshStatus($invoice);
        $notification=\App\Services\CentralEmail::template('payment_create');
        if($notification && $notification->enabled_email && $invoice->clients?->email) {
            $message=MessageReplace($notification,$invoice->id);
            commonEmailSend($invoice->clients->email,['module'=>'payment_create','subject'=>$message['subject'],'message'=>$message['message']]);
        }
        return back()->with('success','Ödeme kaydedildi.');
    }

    public function paymentDestroy(Request $request, $invoice_id, $payment_id)
    {
        abort_unless(auth()->user()->can('delete invoice payment'),403);
        $invoice=Invoice::where('parent_id',parentId())->lockForUpdate()->findOrFail($invoice_id);
        $payment=InvoicePayment::where('parent_id',parentId())->where('invoice_id',$invoice->id)->lockForUpdate()->findOrFail($payment_id);
        $payment->delete();
        app(InventoryAccounting::class)->refreshStatus($invoice);
        return back()->with('success','Ödeme silindi.');
    }

    private function itemSelectionData(?Invoice $invoice = null): array
    {
        $itemCategories = \App\Models\ItemCategory::where('parent_id', parentId())->orderBy('name')->pluck('name', 'id');
        $itemCatalog = Item::where('parent_id', parentId())->orderBy('title')->get()->map(fn ($item) => [
            'id' => $item->id, 'title' => $item->title, 'category' => $item->category_id ?: 'uncategorized', 'available' => true,
        ])->keyBy('id');
        foreach ($invoice?->items ?? [] as $line) {
            if (!$itemCatalog->has($line->item)) {
                $category = $line->item_snapshot['category_id'] ?? null;
                $itemCatalog->put($line->item, ['id' => $line->item, 'title' => $line->item_title,
                    'category' => $itemCategories->has($category) ? $category : 'uncategorized', 'available' => false]);
            }
        }
        return ['itemCategories' => $itemCategories, 'itemCatalog' => $itemCatalog->values()];
    }

    public function invoiceItem($invoice_id)
    {
        if (\Auth::user()->can('create invoice')) {
            $invoice = Invoice::where('parent_id', parentId())->findOrFail($invoice_id);
            $invoiceItems = Item::where('parent_id', parentId())->get()->pluck('title', 'id');
            $invoiceItems->prepend(__('Select Item'), '');
            return view('invoice.create_item', compact('invoice', 'invoiceItems') + $this->itemSelectionData());
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








    public function banktransferPayment(Request $request, $id)
    {
        abort_unless(auth()->user()->can('create invoice payment'),403);
        $invoice=Invoice::where('parent_id',parentId())->lockForUpdate()->findOrFail(decrypt($id));
        $data=$request->validate(['receipt'=>'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'amount'=>['required','numeric','min:0.01','max:'.round($invoice->getInvoiceTotalDueAmount(),2),'regex:/^\d+(\.\d{1,2})?$/'],
            'notes'=>'nullable|string|max:2000']);
        $name=(string)\Illuminate\Support\Str::uuid().'.'.$request->file('receipt')->extension();
        $request->file('receipt')->storeAs('upload/receipt/',$name);
        $payment=new InvoicePayment(); $payment->invoice_id=$invoice->id; $payment->parent_id=parentId();
        $payment->transaction_id=(string)\Illuminate\Support\Str::uuid(); $payment->receipt=$name;
        $payment->payment_type='Bank Transfer'; $payment->amount=$data['amount']; $payment->description=$data['notes']??null;
        $payment->payment_status='pending'; $payment->payment_date=now()->toDateString(); $payment->save();
        return back()->with('success','Ödeme onay bekliyor. Henüz tahsilat olarak sayılmadı.');
    }

    public function invoicePaymentStatus($id, $status)
    {
        abort_unless(auth()->user()->can('edit invoice'),403);
        abort_unless(in_array($status,['accept','reject'],true),422);
        $order=InvoicePayment::where('parent_id',parentId())->lockForUpdate()->findOrFail($id);
        $invoice=Invoice::where('parent_id',parentId())->lockForUpdate()->findOrFail($order->invoice_id);
        if($order->payment_status!=='pending') return back()->with('success','Bu ödeme daha önce işlenmiş.');
        if($status==='accept' && (float)$order->amount>round($invoice->getInvoiceTotalDueAmount(),2)) throw \Illuminate\Validation\ValidationException::withMessages(['amount'=>'Ödeme kalan borcu aşıyor. Faturayı yenileyip kontrol edin.']);
        $order->payment_status=$status==='accept' ? 'success' : 'reject'; $order->save();
        app(InventoryAccounting::class)->refreshStatus($invoice);
        return back()->with('success',$status==='accept' ? 'Ödeme onaylandı.' : 'Ödeme reddedildi.');
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
