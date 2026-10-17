<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/** Read current business records; payments and stock transfers are not usage events. */
class UsageStatistics
{
    public static function accessible(): bool
    {
        $user = auth()->user();
        return $user && !in_array($user->type, ['client', 'super admin'], true)
            && $user->can('manage item') && $user->can('manage service report');
    }

    public function report(int $owner, string $start, string $end): array
    {
        $products = $this->products($owner, $start, $end);
        $jobs = DB::table('services as s')->where('s.parent_id', $owner)->where('s.status', 'completed')
            ->whereBetween(DB::raw('COALESCE(s.service_date, DATE(s.created_at))'), [$start, $end]);
        $services = (clone $jobs)->join('service_items as line', function ($join) {
            $join->on('line.service_id', '=', 's.id')->on('line.parent_id', '=', 's.parent_id');
        })->leftJoin('service_types as kind', 'kind.id', '=', 'line.type_id')
            ->where(fn ($q) => $q->where('kind.parent_id', $owner)->orWhereNull('kind.id'))
            ->where('line.type_id', '>', 0)->groupBy('line.type_id')
            ->select('line.type_id')->selectRaw('MAX(kind.type) AS title, COUNT(DISTINCT s.id) AS uses');

        return [
            'products' => (clone $products)->orderByDesc('quantity')->orderBy('title')->orderBy('product_key')->orderBy('unit_id')->limit(10)->get(),
            'product_count' => DB::query()->fromSub(clone $products, 'ranked_products')->distinct()->count('product_key'),
            'services' => (clone $services)->orderByDesc('uses')->orderBy('title')->orderBy('line.type_id')->limit(10)->get(),
            'service_type_count' => DB::query()->fromSub(clone $services, 'ranked_services')->count(),
            'completed_services' => (clone $jobs)->count(),
        ];
    }

    private function products(int $owner, string $start, string $end): Builder
    {
        $grammar = DB::connection()->getQueryGrammar();
        $json = fn (string $field) => 'NULLIF(NULLIF('.$grammar->wrap('line.item_snapshot->'.$field).", 'null'), '')";
        $legacy = fn (string $field) => DB::getDriverName() === 'sqlite'
            ? "'legacy:' || CAST($field AS TEXT)" : "CONCAT('legacy:', $field)";
        $identity = 'COALESCE('.$json('inventory_key').', item.inventory_key, '.$legacy('line.item').')';
        $unitId = 'COALESCE('.$json('units').', item.units, 0)';

        $invoices = DB::table('invoice_items as line')->join('invoices as invoice', function ($join) {
            $join->on('invoice.id', '=', 'line.invoice_id')->on('invoice.parent_id', '=', 'line.parent_id');
        })->leftJoin('items as item', 'item.id', '=', 'line.item')
            ->leftJoin('units as unit', fn ($join) => $join->on('unit.id', '=', DB::raw($unitId))->where('unit.parent_id', $owner))
            ->where('line.parent_id', $owner)->where(fn ($q) => $q->where('item.parent_id', $owner)->orWhereNull('item.id'))
            ->whereBetween(DB::raw('COALESCE(invoice.invoice_date, DATE(invoice.created_at))'), [$start, $end])
            ->where('line.quantity', '>', 0)
            ->selectRaw($identity.' AS product_key, '.$unitId.' AS unit_id')
            ->selectRaw('COALESCE(item.title, '.$json('title').", line.description, 'Ürün') AS title")
            ->selectRaw("COALESCE(unit.unit, 'Birim belirtilmemiş') AS unit_name, line.quantity AS quantity, line.quantity AS invoice_quantity, 0 AS counter_quantity");

        $counter = DB::table('counter_sales as sale')->leftJoin('items as item', 'item.id', '=', 'sale.item_id')
            ->leftJoin('units as unit', fn ($join) => $join->on('unit.id', '=', 'item.units')->where('unit.parent_id', $owner))
            ->where('sale.parent_id', $owner)->where(fn ($q) => $q->where('item.parent_id', $owner)->orWhereNull('item.id'))
            ->whereBetween('sale.sale_date', [$start, $end])->where('sale.quantity', '>', 0)
            ->selectRaw('COALESCE(sale.inventory_key, item.inventory_key, '.$legacy('sale.item_id').') AS product_key, COALESCE(item.units, 0) AS unit_id')
            ->selectRaw("COALESCE(item.title, sale.item_title, 'Ürün') AS title, COALESCE(unit.unit, 'Birim belirtilmemiş') AS unit_name")
            ->selectRaw('sale.quantity AS quantity, 0 AS invoice_quantity, sale.quantity AS counter_quantity');

        // Same stock identity is counted together, but different units never get added together.
        return DB::query()->fromSub($invoices->unionAll($counter), 'usage_lines')->groupBy('product_key', 'unit_id')
            ->select('product_key', 'unit_id')->selectRaw('MAX(title) AS title, MAX(unit_name) AS unit_name, SUM(quantity) AS quantity, SUM(invoice_quantity) AS invoice_quantity, SUM(counter_quantity) AS counter_quantity');
    }
}
