<?php

namespace App\Services;

use App\Models\{Invoice, Service};

class ExternalLabor
{
    public const RULE = 'nullable|numeric|min:0|max:9999999999.99|regex:/^\d+(\.\d{1,2})?$/';

    public function update(Service $service, $amount): void
    {
        $accounting = app(InventoryAccounting::class);
        $accounting->transaction($service->parent_id, function () use ($service, $amount, $accounting) {
            $locked = Service::where('parent_id', $service->parent_id)->lockForUpdate()->findOrFail($service->id);
            $amount = number_format((float) ($amount ?? 0), 2, '.', '');
            if ($locked->external_labor_amount === $amount) return;
            $locked->external_labor_amount = $amount;
            $locked->save();
            foreach (Invoice::where('parent_id', $locked->parent_id)->where('service', $locked->id)->lockForUpdate()->get() as $invoice) {
                $previousTotal = $invoice->getInvoiceAllTotalAmount();
                $invoice->external_labor_amount = $amount;
                $invoice->save();
                $accounting->adjustReturnedIncome($invoice, $previousTotal);
                $accounting->refreshStatus($invoice);
            }
        });
        $service->refresh();
    }
}
