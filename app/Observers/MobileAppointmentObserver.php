<?php
namespace App\Observers;

use App\Models\Appointment;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Support\Facades\DB;

class MobileAppointmentObserver implements ShouldHandleEventsAfterCommit
{
    public function created(Appointment $appointment): void { $this->enqueue($appointment); }
    public function updated(Appointment $appointment): void {
        if ($appointment->wasChanged('status')) $this->enqueue($appointment);
    }
    private function enqueue(Appointment $appointment): void {
        if (!in_array($appointment->status,['pending','cancelled'],true)) return;
        if ($appointment->status==='cancelled' && !$appointment->cancelled_by_customer) return;
        $owner=$appointment->profile?->owner;
        if (!$owner) return;
        foreach(DB::table('mobile_push_devices')->where('owner_id',$owner->id)->get(['id']) as $device) {
            DB::table('mobile_push_outbox')->insertOrIgnore([
                'appointment_id'=>$appointment->id,'device_id'=>$device->id,'event'=>$appointment->status,
                'recovery_version'=>(int)$owner->recovery_version,'status'=>'pending','created_at'=>now(),'updated_at'=>now(),
            ]);
        }
    }
}
