<?php
namespace App\Services;
use App\Models\{Vehicle, VehicleSmsMessage, AppointmentSmsSetting};
use App\Support\TurkishPhone;
use Illuminate\Support\Facades\DB;

class VehicleRegistrationSms
{
    public function send(Vehicle $vehicle): void
    {
        try {
            $settings = AppointmentSmsSetting::central();
            if (!$settings->vehicle_sms_enabled) return;
            $phone = TurkishPhone::mobile($vehicle->clients?->phone_number);
            $link = $vehicle->qrCode?->publicUrl();
            $company = settingsById($vehicle->parent_id)['company_name'] ?: (\App\Models\User::find($vehicle->parent_id)?->name ?? 'İşletme');
            $body = $settings->renderMessage($settings->vehicle_template, ['{isletme}'=>(string)$company,'{link}'=>$link ?? '', '{plaka}'=>$vehicle->license_plate]);
            DB::table('vehicle_sms_messages')->insertOrIgnore(['vehicle_id'=>$vehicle->id,'parent_id'=>$vehicle->parent_id,'status'=>'pending','created_at'=>now(),'updated_at'=>now()]);
            $message = VehicleSmsMessage::where('vehicle_id',$vehicle->id)->firstOrFail();
            if (!VehicleSmsMessage::whereKey($message->id)->where('status','pending')->update(['status'=>'sending'])) return;
            $message->update(['recipient'=>$phone,'body'=>$body]);
            if (!$phone || !$link || !$settings->ready()) {
                $message->update(['status'=>'failed','error_code'=>!$phone ? 'invalid_phone' : (!$link ? 'missing_link' : 'sms_disabled')]);
                return;
            }
            $result = app(AppointmentSmsGateway::class)->send($phone,$body);
            $message->update($result + ['sent_at'=>($result['status']==='accepted' ? now() : null)]);
        } catch (\Throwable $error) {
            // Never roll back vehicle creation or resend an uncertain delivery automatically.
            try {
                VehicleSmsMessage::where('vehicle_id',$vehicle->id)->where('status','sending')->update(['status'=>'unknown','error_code'=>'delivery_unknown']);
            } catch (\Throwable $ignored) {
                // A database outage after commit must not report vehicle creation as failed.
            }
        }
    }
}
