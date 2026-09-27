<?php
namespace App\Services;

use App\Models\{AppointmentProfile, BookingService};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingDirectory
{
    public static function regions(): array
    {
        $regions = config('booking_directory.cities');
        unset($regions[34]);
        $regions['34-avrupa'] = 'İstanbul Avrupa'; $regions['34-anadolu'] = 'İstanbul Anadolu';
        return $regions;
    }

    public function selection(AppointmentProfile $profile, ?string $vehicle, $service): ?BookingService
    {
        if (!$vehicle && !$service) return null;
        $selected = BookingService::where('is_active', true)->find($service);
        if (!$selected || !in_array($vehicle, $selected->vehicle_types, true)
            || !DB::table('booking_offerings')->where('appointment_profile_id', $profile->id)
                ->where('vehicle_type', $vehicle)->where('booking_service_id', $selected->id)->exists()) {
            throw ValidationException::withMessages(['service' => 'İşletme bu taşıt için seçilen hizmeti artık sunmuyor. Lütfen hizmet seçiminizi yenileyin.']);
        }
        return $selected;
    }
}
