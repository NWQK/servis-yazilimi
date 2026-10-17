<?php
namespace App\Services;

use App\Models\{Appointment,User};
use Illuminate\Support\Facades\{Crypt,DB};
use Laravel\Sanctum\PersonalAccessToken;

class MobilePushDelivery
{
    public function deliver(int $id): void {
        if (!config('mobile_push.enabled')) return;
        $row=DB::table('mobile_push_outbox')->find($id);
        if (!$row || $row->status!=='pending') return;
        $device=DB::table('mobile_push_devices')->find($row->device_id);
        $token=$device ? PersonalAccessToken::find($device->access_token_id) : null;
        $user=$device ? User::find($device->user_id) : null;
        $owner=$device ? User::find($device->owner_id) : null;
        $appointment=Appointment::find($row->appointment_id);
        $valid=$token && $user && $owner && $user->type==='owner' && $user->id===$owner->id
            && (int)$user->is_active===1 && $user->email_verified_at && !$user->client_archived_at
            && $token->tokenable_type===$user->getMorphClass() && (int)$token->tokenable_id===$user->id
            && $token->expires_at && $token->expires_at->isFuture()
            && in_array('mobile:v1',$token->abilities,true)
            && in_array(app(MobileAccess::class)->snapshot($user,$owner),$token->abilities,true)
            && !($user->twofa_required && !$user->twofa_secret)
            && (int)$owner->recovery_version===(int)$row->recovery_version
            && !$owner->subscription_suspended_at && !app(DemoLifecycle::class)->expired($owner)
            && (!$owner->subscription_expire_date || $owner->subscription_expire_date>=now()->toDateString())
            && $appointment && (int)$appointment->profile?->owner_id===$owner->id && $appointment->status===$row->event
            && ($row->event!=='pending' || ($appointment->starts_at->isFuture() && (!$appointment->pending_expires_at || $appointment->pending_expires_at->isFuture())))
            && ($row->event!=='cancelled' || $appointment->cancelled_by_customer);
        if (!$valid) { DB::table('mobile_push_outbox')->where('id',$id)->where('status','pending')->update(['status'=>'cancelled','updated_at'=>now()]); return; }
        if (PlatformMaintenance::enabled() || DB::table('business_recovery_locks')->where('owner_id',$owner->id)->exists()) return;
        if (DB::table('mobile_push_outbox')->where('id',$id)->where('status','pending')->update(['status'=>'sending','updated_at'=>now()])!==1) return;
        try {
            $status=app(FirebasePush::class)->send(Crypt::decryptString($device->encrypted_token),$appointment->id,$row->event);
            if ($status==='unregistered') DB::table('mobile_push_devices')->where('id',$device->id)->delete();
        } catch (\Throwable $e) {
            // Do not log credentials, FCM tokens, JWTs or customer data.
            $status='uncertain';
        }
        DB::table('mobile_push_outbox')->where('id',$id)->update(['status'=>$status,'updated_at'=>now()]);
    }
}
