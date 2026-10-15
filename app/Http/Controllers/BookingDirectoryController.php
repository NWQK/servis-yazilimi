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
            'sort'=>['nullable',Rule::in(['rating','distance','appointments'])],
        ]);
        $region = $data['region'] ?? null; $vehicle = $data['vehicle'] ?? null; $service = null;
        if ($vehicle && !$region) return redirect()->route('directory.index');
        $services = $vehicle ? BookingService::where('is_active', true)->orderBy('name')->get()->filter(fn ($service) => in_array($vehicle, $service->vehicle_types, true)) : collect();
        if (!empty($data['service'])) {
            $service = $services->firstWhere('id', $data['service']);
            abort_unless($service, 404);
        }
        $shops = null;
        $sort = $data['sort'] ?? 'rating';
        $location = \App\Services\BookingLocation::current($request);
        if ($service) {
            $query = AppointmentProfile::query()->leftJoin('business_locations','business_locations.owner_id','=','appointment_profiles.owner_id')->with('businessLocation')->addSelect(['appointment_profiles.*',
                'review_average'=>\App\Models\BusinessReview::selectRaw('AVG(rating)')->whereColumn('owner_id','appointment_profiles.owner_id'),
                'appointment_count'=>\App\Models\Appointment::selectRaw('COUNT(*)')->whereColumn('appointment_profile_id','appointment_profiles.id')->whereIn('status',['approved','completed']),
                'review_count'=>\App\Models\BusinessReview::selectRaw('COUNT(rating)')->whereColumn('owner_id','appointment_profiles.owner_id')])->where('directory_visible', true)->where('is_active', true)
                ->where('directory_region', $region)->whereHas('owner', fn ($q) => $q->where('type', 'owner')->whereNull('subscription_suspended_at')->where(fn($owner)=>$owner->whereNull('demo_expires_at')->orWhere('demo_expires_at','>',now())->orWhereNotIn('subscription',\App\Models\Subscription::where('vehicle_limit',50)->select('id'))))
                ->when(\Illuminate\Support\Facades\Schema::hasTable('business_recovery_locks'),fn($q)=>$q->whereNotIn('appointment_profiles.owner_id',\Illuminate\Support\Facades\DB::table('business_recovery_locks')->select('owner_id')))
                ->whereExists(fn ($q) => $q->selectRaw('1')->from('booking_offerings')
                    ->whereColumn('appointment_profile_id', 'appointment_profiles.id')
                    ->where('vehicle_type', $vehicle)->where('booking_service_id', $service->id))
                ;
            if ($location) {
                $expression=\App\Services\BookingLocation::distanceExpression();
                $query->selectRaw('CASE WHEN business_locations.latitude IS NOT NULL THEN '.$expression.' ELSE NULL END AS distance_km',[$location['lat'],$location['lng'],$location['lat']]);
            } else $query->selectRaw('NULL AS distance_km');
            if ($sort==='distance' && $location) $query->orderByRaw('CASE WHEN business_locations.latitude IS NULL THEN 1 ELSE 0 END')->orderBy('distance_km');
            if ($sort==='appointments') $query->orderByDesc('appointment_count');
            $shops=$query->orderByDesc('review_average')->orderByDesc('review_count')->orderByDesc('appointment_count')->orderBy('appointment_profiles.id')->paginate(12)->withQueryString();
        }
        return response()->view('appointments.directory', compact('regions','vehicles','region','vehicle','service','services','shops','sort','location'))->header('Cache-Control','private, no-store');
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
