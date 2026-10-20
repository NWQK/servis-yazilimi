<?php
namespace App\Services;
use App\Models\{Invoice,InvoiceService,Service};
use Illuminate\Http\Request;
class InvoiceEditing {
    public function rules(int $owner,Request $request,bool $productSale): array { return [
                    'invoice_date' => 'required',
                    'discount_amount' => Invoice::DISCOUNT_RULE,
                    'client' => $productSale ? ['required',\Illuminate\Validation\Rule::exists('users','id')->where('parent_id',$owner)->where('type','client')] : ['required','integer'],
                    'types'=>$productSale?'prohibited':'nullable|array',
                    'types.*.service_type'=>\App\Services\ServiceInput::type(),
                    'types.*.rate'=>'required|numeric|min:0|max:999999999.99',
                    'types.*.tax'=>'nullable|array',
                    'types.*.tax.*'=>\App\Services\ServiceInput::tax(),
                    'item'=>$productSale?'required|array|min:1':'nullable|array',
                    'item.*'=>$productSale?'required|integer|min:1':'nullable|integer',
                    'service' => $productSale ? ['prohibited'] : ['required', \Illuminate\Validation\Rule::exists('services', 'id')->where('parent_id', $owner)->where('client', $request->client)],
                ]; }
    public function update(int $owner,int $id,Request $request,array $billing): Invoice {
        $productSale=Invoice::where('parent_id',$owner)->findOrFail($id)->invoice_kind==='product';
            $invoice = app(InventoryAccounting::class)->transaction($owner, function () use ($request, $id, $billing, $productSale, $owner) {
                $invoice = Invoice::where('parent_id', $owner)->lockForUpdate()->findOrFail($id);
                if ((int) $invoice->client !== (int) $request->client) {
                    $invoice->customer_details = array_replace(
                        \App\Services\InvoiceBilling::customer($owner, (int) $request->client),
                        array_filter($billing, fn ($value) => $value !== null && $value !== '')
                    );
                } elseif ($request->exists('billing')) {
                    $invoice->customer_details = $billing;
                }
                $previousTotal = $invoice->getInvoiceAllTotalAmount();
                $invoice->client = $request->client;
                $invoice->service = $productSale?0:$request->service;
                if(!$productSale){
                $laborService = Service::where('parent_id', $owner)->findOrFail($request->service);
            $invoice->external_labor_amount = $laborService->external_labor_amount;
            \App\Services\ExternalLabor::copyTax($laborService, $invoice);
                }
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
                            'parent_id' => $owner
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

        return $invoice;
    }
    public function stockRows(Request $request): array
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
