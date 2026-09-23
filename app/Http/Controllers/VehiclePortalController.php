<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceType;
use App\Models\Tax;
use App\Models\VehicleQrCode;
use Illuminate\Support\Facades\DB;

class VehiclePortalController extends Controller
{
    private function code(string $token): VehicleQrCode
    {
        return VehicleQrCode::where('token', $token)->firstOrFail();
    }

    private function vehicle(VehicleQrCode $code)
    {
        return $code->vehicle()->where('parent_id', $code->parent_id)->firstOrFail();
    }

    private function invoices($vehicle)
    {
        return Invoice::where('parent_id', $vehicle->parent_id)->whereHas('services', function ($query) use ($vehicle) {
            $query->where('vehicle', $vehicle->id)->where('parent_id', $vehicle->parent_id);
        });
    }

    private function displaySettings(int $parentId): array
    {
        $defaults = [
            'company_name' => 'Araç Servis Kaydı', 'company_address' => '',
            'company_phone' => '', 'CURRENCY_SYMBOL' => '₺',
            'invoice_number_prefix' => '#INV-', 'service_number_prefix' => '#SER-',
        ];
        return array_merge($defaults, DB::table('settings')->where('parent_id', $parentId)
            ->whereIn('name', array_keys($defaults))->pluck('value', 'name')->all());
    }

    private function page(string $view, array $data, int $status = 200)
    {
        return response()->view($view, $data, $status)
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Content-Security-Policy', "default-src 'none'; style-src 'self'; script-src 'self'; img-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'");
    }

    public function show(string $token)
    {
        $code = $this->code($token);
        if (!$code->assigned_at) {
            return $this->page('vehicle_portal.unassigned', [], 200);
        }
        $vehicle = $this->vehicle($code);
        $parentId = $vehicle->parent_id;
        $services = Service::where('parent_id', $parentId)->where('vehicle', $vehicle->id)
            ->with(['types' => fn ($q) => $q->where('parent_id', $parentId)])
            ->orderByDesc('service_date')->orderByDesc('id')->paginate(10, ['*'], 'services_page')->withQueryString();
        $invoices = $this->invoices($vehicle)->orderByDesc('invoice_date')->orderByDesc('id')
            ->paginate(10, ['*'], 'invoices_page')->withQueryString();
        $serviceTypes = ServiceType::where('parent_id', $parentId)->pluck('type', 'id');
        $settings = $this->displaySettings($parentId);
        return $this->page('vehicle_portal.show', compact('code', 'vehicle', 'services', 'invoices', 'serviceTypes', 'settings'));
    }

    public function invoice(string $token, int $invoiceId)
    {
        $code = $this->code($token);
        abort_unless($code->assigned_at, 404);
        $vehicle = $this->vehicle($code);
        $parentId = $vehicle->parent_id;
        $invoice = $this->invoices($vehicle)->findOrFail($invoiceId);
        $invoice->load([
            'items' => fn ($q) => $q->where('parent_id', $parentId),
            'items.items' => fn ($q) => $q->where('parent_id', $parentId),
            'types' => fn ($q) => $q->where('parent_id', $parentId),
            'payments' => fn ($q) => $q->where('parent_id', $parentId),
        ]);
        $taxes = Tax::where('parent_id', $parentId)->get()->keyBy('id');
        $serviceTypes = ServiceType::where('parent_id', $parentId)->pluck('type', 'id');
        $lines = collect();
        foreach ($invoice->types as $item) {
            $lines->push(['name' => $serviceTypes[$item->service_type] ?? 'Servis işlemi', 'description' => $item->note,
                'quantity' => 1, 'price' => (float) $item->rate, 'tax_ids' => $item->tax]);
        }
        foreach ($invoice->items as $item) {
            $lines->push(['name' => $item->item_title, 'description' => $item->description,
                'quantity' => (float) $item->quantity, 'price' => (float) $item->amount, 'tax_ids' => $item->tax]);
        }
        $lines = $lines->map(function ($line) use ($taxes) {
            $line['subtotal'] = $line['quantity'] * $line['price'];
            $rate = collect(explode(',', $line['tax_ids'] ?? ''))->unique()->sum(fn ($id) => $taxes->get($id)->rate ?? 0);
            $line['tax_rate'] = $rate;
            $line['tax'] = $line['subtotal'] * $rate / 100;
            return $line;
        });
        $total = $lines->sum('subtotal') + $lines->sum('tax');
        $paid = $invoice->payments->sum('amount');
        $settings = $this->displaySettings($parentId);
        return $this->page('vehicle_portal.invoice', compact('code', 'vehicle', 'invoice', 'lines', 'total', 'paid', 'settings'));
    }
}
