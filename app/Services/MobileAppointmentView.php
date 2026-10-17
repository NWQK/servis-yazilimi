<?php
namespace App\Services;

use App\Models\Appointment;

class MobileAppointmentView
{
    public function version(Appointment $appointment): string {
        return hash('sha256',json_encode([$appointment->id,$appointment->status,
            $appointment->updated_at?->format('Y-m-d H:i:s.u'),$appointment->starts_at?->toIso8601String(),
            $appointment->pending_expires_at?->toIso8601String(),$appointment->cancelled_at?->toIso8601String()]));
    }
    public function data(Appointment $appointment,bool $detail=false): array {
        $booking=app(AppointmentBooking::class);
        $status=$booking->effectiveStatus($appointment);
        $data=['id'=>$appointment->id,'customer_name'=>$appointment->customer_name,
            'license_plate'=>$appointment->license_plate,'requested_service'=>$appointment->requested_service,
            'requested_vehicle'=>$appointment->requested_vehicle,'status'=>$status,
            'status_label'=>Appointment::statuses()[$status]??$status,
            'starts_at'=>$appointment->starts_at?->toIso8601String(),'ends_at'=>$appointment->ends_at?->toIso8601String()];
        if ($detail) $data += ['phone'=>$appointment->phone,'notes'=>$appointment->notes,
            'version'=>$this->version($appointment),'actions'=>$booking->availableActions($appointment),
            'pending_expires_at'=>$appointment->pending_expires_at?->toIso8601String(),
            'notification_event'=>in_array($status,['pending','cancelled'],true)
                && ($status==='pending' || $appointment->cancelled_by_customer) ? $status : null];
        return $data;
    }
}
