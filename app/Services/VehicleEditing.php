<?php
namespace App\Services;
use App\Models\{User,Vehicle};
use Illuminate\Http\Request;
class VehicleEditing {
    public function update(int $owner,Vehicle $vehicle,Request $request): void {
            \Illuminate\Support\Facades\DB::transaction(function() use($request,$vehicle,$owner){
                User::whereKey($owner)->lockForUpdate()->firstOrFail();
                $vehicle=Vehicle::where('parent_id',$owner)->lockForUpdate()->findOrFail($vehicle->id);
                \App\Services\ChassisNumber::validateForm($request,$vehicle);
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
                \App\Services\ChassisNumber::assertUnique($vehicle);
                $vehicle->save();

            },5);    }
}
