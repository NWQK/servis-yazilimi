<?php

namespace App\Services;

use App\Models\{Invoice, Service};

class ExternalLabor
{
    public const RULE = 'nullable|numeric|min:0|max:9999999999.99|regex:/^\d+(\.\d{1,2})?$/';

    public static function taxRules(): array
    {
        return ['nullable', 'integer', \Illuminate\Validation\Rule::exists('taxes', 'id')->where('parent_id', parentId())];
    }

    public static function selectTax(Service $service, $taxId): void
    {
        $tax = $taxId ? \App\Models\Tax::where('parent_id', $service->parent_id)->findOrFail($taxId) : null;
        $service->external_labor_tax_id = $tax?->id;
        $service->external_labor_tax_rate = $tax?->rate ?? 0;
        $service->external_labor_tax_title = $tax?->title;
    }

    public static function copyTax(Service $service, Invoice $invoice): void
    {
        foreach (['external_labor_tax_id', 'external_labor_tax_rate', 'external_labor_tax_title'] as $field) {
            $invoice->$field = $service->$field;
        }
    }

    public function update(Service $service, $amount, $taxId = null, bool $changeTax = false): void
    {
        $accounting = app(InventoryAccounting::class);
        $accounting->transaction($service->parent_id, function () use ($service, $amount, $accounting, $taxId, $changeTax) {
            $locked = Service::where('parent_id', $service->parent_id)->lockForUpdate()->findOrFail($service->id);
            $amount = number_format((float) ($amount ?? 0), 2, '.', '');
            $taxChanged = $changeTax && (string) $locked->external_labor_tax_id !== (string) ($taxId ?: '');
            if ($locked->external_labor_amount === $amount && !$taxChanged) return;
            if ($taxChanged) self::selectTax($locked, $taxId);
            $locked->external_labor_amount = $amount;
            $locked->save();
            foreach (Invoice::where('parent_id', $locked->parent_id)->where('service', $locked->id)->lockForUpdate()->get() as $invoice) {
                $previousTotal = $invoice->getInvoiceAllTotalAmount();
                $invoice->external_labor_amount = $amount;
                self::copyTax($locked, $invoice);
                $invoice->save();
                $accounting->adjustReturnedIncome($invoice, $previousTotal);
                $accounting->refreshStatus($invoice);
            }
        });
        $service->refresh();
    }
}
