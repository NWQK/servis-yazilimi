<?php
namespace App\Http\Controllers;
use App\Services\CentralEmail;
use App\Models\AppointmentSmsSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class CommunicationSettingsController extends Controller
{
    private function authorizeAdmin(): void { abort_unless(auth()->user()?->type === 'super admin',403); }
    public function index()
    {
        $this->authorizeAdmin();
        return view('admin.communication-settings',['emailEnabled'=>CentralEmail::enabled(),'smsEnabled'=>AppointmentSmsSetting::central()->enabled]);
    }
    public function save(Request $request)
    {
        $this->authorizeAdmin();
        $data=$request->validate(['email_enabled'=>'required|boolean','sms_enabled'=>'required|boolean']);
        AppointmentSmsSetting::central();
        DB::transaction(function() use($data) {
            $sms=AppointmentSmsSetting::lockForUpdate()->findOrFail(1);
            $before=['email_enabled'=>CentralEmail::enabled(),'sms_enabled'=>$sms->enabled];
            $sms->enabled=(bool)$data['sms_enabled'];
            if ($sms->enabled && !$sms->ready()) throw \Illuminate\Validation\ValidationException::withMessages(['sms_enabled'=>'SMS açılmadan önce API bilgileri ve onaylı gönderici başlığı yapılandırılmalıdır.']);
            $sms->save();
            DB::table('settings')->updateOrInsert(['parent_id'=>CentralEmail::administrator()->id,'type'=>'smtp','name'=>'EMAIL_ENABLED'],['value'=>(string)$data['email_enabled']]);
            if($before!==['email_enabled'=>(bool)$data['email_enabled'],'sms_enabled'=>(bool)$data['sms_enabled']]) \App\Services\AdminAudit::record('communication.status',null,$before,$data);
        });
        return back()->with('success','Merkezi e-posta ve SMS durumu kaydedildi.');
    }
}
