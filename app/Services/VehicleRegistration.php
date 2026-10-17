<?php
namespace App\Services;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleRegistration
{
    public function rules(int $owner, Request $request): array {
        return [
                    'client' => ['required', Rule::exists('users', 'id')->where('parent_id', $owner)->where('type', 'client')->whereNull('client_archived_at')],
                    'type' => ['required', Rule::exists('vehicle_types', 'id')->where('parent_id', $owner)],
                    'brand' => ['required', Rule::exists('vehicle_brands', 'id')->where('parent_id', $owner)->where('type', $request->type)],
                    'qr_code_id' => 'nullable|integer|min:1',
                    'color' => 'nullable|string|max:255',
                    'license_plate' => 'required',
                    'engine_type' => 'nullable|string|max:255',
                    'engine_no' => 'nullable|string|max:255',
                    'chassis_no' => 'nullable|string|max:255',
                    'fuel_type' => 'nullable|string|max:255',
                    'mileage' => 'nullable|integer|min:0|max:2147483647',
                    'last_service_date' => 'nullable|date',
                    'next_service_due_date' => 'nullable|date',
                    'insurance_details' => 'nullable|string',
                    'notes' => 'nullable|string',
                ];
    }
    public function create(int $owner, Request $request): Vehicle {
    $vehicle = app(VehicleQrPool::class)->createVehicle($owner, $request->filled('qr_code_id') ? (int) $request->qr_code_id : null, function () use ($request, $owner) {
        $vehicle = new Vehicle();
        $vehicle->vehicle_id = $this->number($owner);
        $vehicle->client = $request->client;
        $vehicle->type = $request->type;
        $vehicle->brand = $request->brand;
        $vehicle->license_plate = $request->license_plate;
        $vehicle->engine_type = $request->engine_type;
        $vehicle->engine_no = $request->engine_no;
        $vehicle->fuel_type = $request->fuel_type;
        $vehicle->chassis_no = $request->chassis_no;
        $vehicle->mileage = $request->mileage;
        $vehicle->last_service_date = $request->last_service_date;
        $vehicle->next_service_due_date = $request->next_service_due_date;
        $vehicle->insurance_details = $request->insurance_details;
        $vehicle->color = $request->color;
        $vehicle->status = $request->status;
        $vehicle->notes = $request->notes;
        $vehicle->parent_id = $owner;
        $vehicle->save();

        return $vehicle;
    });

    if ($request->exists('history_public')) app(\App\Services\PublicVehicleHistory::class)->share($vehicle,$request->boolean('history_public'));
        return $vehicle;
    }
    private function number(int $owner): int {
        $latest = Vehicle::withTrashed()->where('parent_id', $owner)->latest()->first();
        return $latest ? $latest->vehicle_id + 1 : 1;
    }
}
