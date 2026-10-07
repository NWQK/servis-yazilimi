<?php
namespace App\Services;
use App\Models\{User,Vehicle,Service,Invoice,Appointment};
use Illuminate\Support\Facades\DB;
class BusinessUsage {
 public static function query(){
  $since=now()->startOfMonth();
  return User::where('type','owner')->select('users.*')->with('subscriptions')
   ->selectSub(Vehicle::selectRaw('COUNT(*)')->whereColumn('parent_id','users.id'),'vehicle_count')
   ->selectSub(User::selectRaw('COUNT(*)')->whereColumn('parent_id','users.id')->where('type','client')->whereNull('client_archived_at'),'client_count')
   ->selectSub(Service::selectRaw('COUNT(*)')->whereColumn('parent_id','users.id')->where('created_at','>=',$since),'month_services')
   ->selectSub(Invoice::selectRaw('COUNT(*)')->whereColumn('parent_id','users.id')->where('created_at','>=',$since),'month_invoices')
   ->selectSub(Appointment::join('appointment_profiles','appointment_profiles.id','=','appointments.appointment_profile_id')->selectRaw('COUNT(*)')->whereColumn('appointment_profiles.owner_id','users.id')->where('appointments.created_at','>=',$since),'month_appointments')
   ->selectSub(DB::table('vehicle_sms_messages')->selectRaw('COUNT(*)')->whereColumn('parent_id','users.id')->where('status','accepted')->where('sent_at','>=',$since),'month_vehicle_sms')
   ->selectSub(DB::table('appointment_sms_messages')->join('appointments','appointments.id','=','appointment_sms_messages.appointment_id')->join('appointment_profiles','appointment_profiles.id','=','appointments.appointment_profile_id')->selectRaw('COUNT(*)')->whereColumn('appointment_profiles.owner_id','users.id')->where('appointment_sms_messages.status','accepted')->where('appointment_sms_messages.sent_at','>=',$since),'month_appointment_sms')
   ->selectSub(DB::table('appointment_sms_challenges')->join('appointment_profiles','appointment_profiles.id','=','appointment_sms_challenges.appointment_profile_id')->selectRaw('COALESCE(SUM(send_count),0)')->whereColumn('appointment_profiles.owner_id','users.id')->where('last_sent_at','>=',$since),'month_verification_sms');
 }
}
