<?php

namespace App\Http\Controllers;

use App\Models\Vehicle;
use App\Models\VehicleQrCode;
use App\Services\VehicleQrPool;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;

class VehicleQrController extends Controller
{
    private function authorizeStaff(): void
    {
        abort_unless(auth()->user()->type !== 'client' && auth()->user()->can('create vehicle'), 403);
    }

    public function index(VehicleQrPool $pool)
    {
        $this->authorizeStaff();
        $pool->replenish(parentId());
        $codes = VehicleQrCode::where('parent_id', parentId())->available()->orderBy('id')->get();
        $assigned = VehicleQrCode::where('parent_id', parentId())->whereNotNull('assigned_at')
            ->with('vehicle')->latest('assigned_at')->paginate(20);
        return response()->view('vehicle_qr.index', compact('codes', 'assigned'))->header('Cache-Control', 'private, no-store');
    }

    public function print(Request $request)
    {
        $this->authorizeStaff();
        $data = $request->validate(['ids' => 'required|array|min:1|max:100', 'ids.*' => 'required|integer|distinct']);
        $codes = VehicleQrCode::where('parent_id', parentId())->whereIn('id', $data['ids'])->orderBy('id')->get();
        abort_unless($codes->count() === count($data['ids']), 404);
        // Printing existing assigned labels is safe: they retain the same permanent URL.
        $writer = new Writer(new ImageRenderer(new RendererStyle(240, 4), new SvgImageBackEnd()));
        $images = $codes->mapWithKeys(fn ($code) => [$code->id => base64_encode(
            $writer->writeString($code->publicUrl(), 'UTF-8', ErrorCorrectionLevel::M())
        )]);
        return response()->view('vehicle_qr.print', compact('codes', 'images'))
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function printed(Request $request, VehicleQrPool $pool)
    {
        $this->authorizeStaff();
        $data = $request->validate(['ids' => 'required|array|min:1|max:100', 'ids.*' => 'required|integer|distinct']);
        $pool->markPrinted(parentId(), $data['ids']);
        return redirect()->route('vehicle-qr.index')->with('success', 'Seçilen etiketler basılmış olarak işaretlendi.');
    }

    public function assign(Request $request, Vehicle $vehicle, VehicleQrPool $pool)
    {
        $this->authorizeStaff();
        abort_unless(auth()->user()->can('edit vehicle') && (int) $vehicle->parent_id === (int) parentId(), 403);
        $data = $request->validate(['qr_code_id' => 'required|integer']);
        $pool->assignExisting(parentId(), $vehicle->id, $data['qr_code_id']);
        return redirect()->route('vehicle.index')->with('success', 'QR araca atandı; boş stok 10 adede tamamlandı.');
    }
}
