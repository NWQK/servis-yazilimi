<?php
namespace App\Http\Controllers;

use App\Models\{AppointmentProfile, BookingService};
use App\Services\BookingDirectory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BookingDirectoryController extends Controller
{
    public function home(Request $request)
    {
        return auth()->check() ? app(HomeController::class)->index() : $this->index($request);
    }
    public function index(Request $request)
    {
        $regions = BookingDirectory::regions();
        $vehicles = config('booking_directory.vehicles');
        $data = $request->validate([
            'region'=>['nullable', Rule::in(array_keys($regions))],
            'vehicle'=>['nullable', 'required_with:service', Rule::in(array_keys($vehicles))],
            'service'=>'nullable|integer|exists:booking_services,id',
        ]);
        $region = $data['region'] ?? null; $vehicle = $data['vehicle'] ?? null; $service = null;
        if ($vehicle && !$region) return redirect()->route('directory.index');
        $services = $vehicle ? BookingService::where('is_active', true)->orderBy('name')->get()->filter(fn ($service) => in_array($vehicle, $service->vehicle_types, true)) : collect();
        if (!empty($data['service'])) {
            $service = $services->firstWhere('id', $data['service']);
            abort_unless($service, 404);
        }
        $shops = null;
        if ($service) {
            $shops = AppointmentProfile::where('directory_visible', true)->where('is_active', true)
                ->where('directory_region', $region)->whereHas('owner', fn ($q) => $q->where('type', 'owner')->whereNull('subscription_suspended_at'))
                ->whereExists(fn ($q) => $q->selectRaw('1')->from('booking_offerings')
                    ->whereColumn('appointment_profile_id', 'appointment_profiles.id')
                    ->where('vehicle_type', $vehicle)->where('booking_service_id', $service->id))
                ->orderBy('display_name')->orderBy('id')->paginate(12)->withQueryString();
        }
        return view('appointments.directory', compact('regions','vehicles','region','vehicle','service','services','shops'));
    }

    public function catalog()
    {
        abort_unless(auth()->user()->type === 'super admin', 403);
        return view('appointments.catalog', ['services'=>BookingService::orderBy('name')->get(), 'vehicles'=>config('booking_directory.vehicles')]);
    }

    public function saveCatalog(Request $request)
    {
        abort_unless(auth()->user()->type === 'super admin', 403);
        $data = $request->validate(['id'=>'nullable|integer|exists:booking_services,id', 'name'=>'required|string|max:100',
            'vehicle_types'=>'required|array|min:1', 'vehicle_types.*'=>['required', Rule::in(array_keys(config('booking_directory.vehicles')))], 'is_active'=>'required|boolean']);
        $service = !empty($data['id']) ? BookingService::findOrFail($data['id']) : new BookingService;
        unset($data['id']); $service->fill($data)->save();
        return back()->with('success', 'Hizmet kaydedildi.');
    }
}
