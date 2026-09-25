<?php

namespace App\Services;

use App\Models\{Expense, Invoice, InvoiceItem, InvoicePayment, Item, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryAccounting
{
    public function transaction(int $tenant, callable $work)
    {
        return DB::transaction(function () use ($tenant, $work) {
            // All stock mutations for one business use the same lock order.
            User::whereKey($tenant)->lockForUpdate()->firstOrFail();
            return $work();
        });
    }

    public function savePurchase(int $tenant, array $attributes, ?int $id = null): Item
    {
        return $this->transaction($tenant, function () use ($tenant, $attributes, $id) {
            $item = $id ? Item::where('parent_id', $tenant)->lockForUpdate()->findOrFail($id) : new Item();
            $previousQuantity = $item->exists ? (int) $item->quantity : 0;
            if (!empty($attributes['category_id']) && !\App\Models\ItemCategory::where('parent_id', $tenant)->whereKey($attributes['category_id'])->exists()) {
                $this->invalid('Geçersiz ürün kategorisi.');
            }
            $item->fill($attributes);
            $item->parent_id = $tenant;
            $item->inventory_key = $item->inventory_key ?: (string) Str::uuid();
            $item->save();
            $difference = (int) $item->quantity - $previousQuantity;
            if ($difference !== 0) {
                $movement = $this->movement($item, $difference > 0 ? 'purchase' : 'adjustment', $difference, $item->purchase_price);
                if ($difference > 0) {
                    $expense = new Expense();
                    $expense->title = $item->title;
                    $expense->date = $item->purchase_date ?: now()->toDateString();
                    $expense->amount = $this->money($item->purchase_price, $difference);
                    $expense->notes = $difference . ' adet × ' . number_format($item->purchase_price, 2, '.', '') . ' — ürün alımı';
                    $expense->parent_id = $tenant;
                    $expense->stock_movement_id = $movement;
                    $expense->save();
                }
            }
            return $item;
        });
    }

    public function sync(Invoice $invoice, array $rows, ?float $previousTotal = null): void
    {
        $this->transaction($invoice->parent_id, function () use ($invoice, $rows, $previousTotal) {
            $invoice = Invoice::where('parent_id', $invoice->parent_id)->lockForUpdate()->findOrFail($invoice->id);
            $existing = $invoice->items()->lockForUpdate()->get()->keyBy('id');
            $previousTotal = $previousTotal ?? $invoice->getInvoiceAllTotalAmount();
            $seen = [];
            foreach ($rows as $row) {
                if (!empty($row['id'])) {
                    if (!$existing->has($row['id']) || isset($seen[$row['id']])) {
                        $this->invalid('Geçersiz veya tekrarlanan fatura ürün satırı.');
                    }
                    $seen[$row['id']] = true;
                }
            }
            // Release removed lines first, so a product replacement can reuse stock.
            foreach ($existing as $line) {
                if (!isset($seen[$line->id])) $this->release($line);
            }
            foreach ($rows as $row) {
                $line = !empty($row['id']) ? $existing->get($row['id']) : new InvoiceItem();
                if ($line->exists && filter_var($row['quantity'] ?? null, FILTER_VALIDATE_INT) === 0) {
                    $this->release($line);
                    continue;
                }
                $this->writeLine($invoice, $line, $row);
            }
            $this->adjustReturnedIncome($invoice, $previousTotal);
        });
    }

    public function add(Invoice $invoice, array $row): InvoiceItem
    {
        return $this->transaction($invoice->parent_id, function () use ($invoice, $row) {
            $invoice = Invoice::where('parent_id', $invoice->parent_id)->lockForUpdate()->findOrFail($invoice->id);
            $line = new InvoiceItem();
            $this->writeLine($invoice, $line, $row);
            return $line;
        });
    }

    public function remove(int $tenant, int $invoiceId, int $lineId): void
    {
        $this->transaction($tenant, function () use ($tenant, $invoiceId, $lineId) {
            $invoice = Invoice::where('parent_id', $tenant)->lockForUpdate()->findOrFail($invoiceId);
            $line = $invoice->items()->lockForUpdate()->find($lineId);
            $previousTotal = $invoice->getInvoiceAllTotalAmount();
            if ($line) $this->release($line); // Repeated removal never returns stock twice.
            $this->adjustReturnedIncome($invoice, $previousTotal);
            $this->refreshStatus($invoice);
        });
    }

    public function adjustReturnedIncome(Invoice $invoice, $previousTotal): void
    {
        $invoice->unsetRelation('items')->unsetRelation('types')->unsetRelation('payments');
        $newTotal = (int) round($invoice->getInvoiceAllTotalAmount() * 100);
        $reduction = max(0, (int) round($previousTotal * 100) - $newTotal);
        if ($reduction === 0) return;

        $paid = (int) round($invoice->payments()->sum('amount') * 100);
        // A partial payment still covered by the remaining invoice is retained.
        // Never reverse unpaid income or repeat an already-recorded correction.
        $correction = min($reduction, max(0, $paid - $newTotal));
        if ($correction === 0) return;

        $payment = new InvoicePayment();
        $payment->invoice_id = $invoice->id;
        $payment->parent_id = $invoice->parent_id;
        $payment->transaction_id = 'return-' . Str::uuid();
        $payment->payment_type = 'Invoice adjustment';
        $payment->payment_status = 'success';
        $payment->payment_date = now()->toDateString();
        $payment->amount = -$correction / 100;
        $payment->description = 'Fatura tutarındaki azalma nedeniyle otomatik gelir düzeltmesi. Banka işlemi değildir.';
        $payment->save();
    }

    public function refreshStatus(Invoice $invoice): void
    {
        $invoice->unsetRelation('items')->unsetRelation('types')->unsetRelation('payments');
        $paid = (float) $invoice->payments()->sum('amount');
        $invoice->status = $paid <= 0 ? 0 : ($paid >= $invoice->getInvoiceAllTotalAmount() ? 2 : 1);
        $invoice->save();
    }

    private function writeLine(Invoice $invoice, InvoiceItem $line, array $row): void
    {
        $quantity = filter_var($row['quantity'] ?? null, FILTER_VALIDATE_INT);
        if ($quantity === false || $quantity < 1 || $quantity > 100000000) $this->invalid('Ürün adedi pozitif tam sayı olmalıdır.');
        $same = $line->exists && (int) $line->item === (int) ($row['item'] ?? 0);
        $oldQuantity = $same ? (int) $line->quantity : 0;
        if ($line->exists && !$same) {
            $this->returnStock($line, (int) $line->stock_quantity);
            $line->stock_quantity = 0;
            $line->item_snapshot = null;
        }
        $item = Item::where('parent_id', $invoice->parent_id)->lockForUpdate()->find($row['item'] ?? 0);
        if (!$item && $same && $line->item_snapshot) {
            $item = $this->restoreItem($line);
        }
        if (!$item) $this->invalid('Ürün bulunamadı veya bu işletmeye ait değil.');
        if (!$item->inventory_key) {
            $item->inventory_key = (string) Str::uuid();
            $item->save();
        }
        $difference = $quantity - $oldQuantity;
        $tracked = $same ? (int) $line->stock_quantity : 0;
        if ($difference > 0) {
            if ((int) $item->quantity < $difference) $this->invalid($item->title . ': yeterli stok yok.');
            $item->quantity -= $difference;
            $item->save();
            $tracked += $difference;
        } elseif ($difference < 0) {
            $returned = min(-$difference, $tracked);
            $this->returnStock($line, $returned);
            $tracked -= $returned;
        }
        if (!$same || !$line->item_snapshot) {
            $line->item_snapshot = $item->only(array_merge($item->getFillable(), ['inventory_key']));
        }
        $line->invoice_id = $invoice->id;
        $line->parent_id = $invoice->parent_id;
        $line->item = $item->id;
        $line->quantity = $quantity;
        // Prices on existing lines remain historical, even if catalogue prices change.
        if (!$same) $line->amount = $item->sales_price;
        $line->stock_quantity = $tracked;
        $line->tax = $row['tax'] ?? ($same ? $line->tax : $item->taxs);
        $line->description = $row['description'] ?? null;
        $line->save();
        if ($difference > 0) $this->movement($item, 'sale', -$difference, $line->amount, $line->id);
    }

    private function release(InvoiceItem $line): void
    {
        $this->returnStock($line, (int) $line->stock_quantity);
        $line->delete();
    }

    private function returnStock(InvoiceItem $line, int $quantity): void
    {
        if ($quantity <= 0) return;
        $item = $this->restoreItem($line);
        $item->quantity += $quantity;
        $item->save();
        $this->movement($item, 'return', $quantity, $line->amount, $line->id);
    }

    private function restoreItem(InvoiceItem $line): Item
    {
        $snapshot = $line->item_snapshot;
        if (!$snapshot || empty($snapshot['inventory_key'])) $this->invalid('Stok iadesi için ürün bilgileri bulunamadı.');
        $item = Item::where('parent_id', $line->parent_id)->where('inventory_key', $snapshot['inventory_key'])->lockForUpdate()->first();
        if (!$item) {
            $item = new Item();
            $item->fill($snapshot);
            if ($item->category_id && !\App\Models\ItemCategory::where('parent_id', $line->parent_id)->whereKey($item->category_id)->exists()) {
                $item->category_id = null;
            }
            $item->parent_id = $line->parent_id;
            $item->inventory_key = $snapshot['inventory_key'];
            $item->quantity = 0;
            $item->save(); // A return is not a purchase; never generate another expense.
        }
        return $item;
    }

    private function movement(Item $item, string $kind, int $quantity, $price, ?int $lineId = null): int
    {
        return DB::table('stock_movements')->insertGetId([
            'parent_id' => $item->parent_id, 'inventory_key' => $item->inventory_key,
            'invoice_item_id' => $lineId, 'kind' => $kind, 'quantity' => $quantity,
            'unit_price' => $this->money($price), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function money($price, int $quantity = 1): string
    {
        return number_format((int) round((float) $price * 100) * $quantity / 100, 2, '.', '');
    }

    private function invalid(string $message): void
    {
        throw ValidationException::withMessages(['item' => $message]);
    }
}
