<?php
namespace App\Http\Controllers;

use App\Models\{AppointmentSmsSetting, AppointmentSmsMessage};
use App\Services\AppointmentSms;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentSmsSettingController extends Controller
{
    private function authorizeAdmin(): void { abort_unless(auth()->user()->type === 'super admin', 403); }

    public function index()
    {
        $this->authorizeAdmin();
        $smsSettings = AppointmentSmsSetting::central();
        $messages = AppointmentSmsMessage::with('appointment.profile')->latest('id')->paginate(20);
        return view('appointments.sms-settings', compact('smsSettings', 'messages'));
    }

    public function save(Request $request)
    {
        $this->authorizeAdmin();
        $data = $request->validate([
            'enabled' => 'required|boolean', 'brand' => 'required|string|max:80',
            'account_sid' => ['nullable', 'regex:/^AC[a-f0-9]{32}$/i'],
            'auth_token' => 'nullable|string|min:16|max:200',
            'from_number' => ['nullable', 'regex:/^\+[1-9]\d{7,14}$/'],
            'messaging_service_sid' => ['nullable', 'regex:/^MG[a-f0-9]{32}$/i'],
            'daily_limit' => 'required|integer|min:1|max:10000',
            'verification_template' => 'required|string|max:480', 'approval_template' => 'required|string|max:480',
        ], [], ['auth_token' => 'Auth Token', 'account_sid' => 'Account SID', 'daily_limit' => 'Günlük doğrulama SMS sınırı']);
        foreach (['verification_template' => ['kod', 'isletme', 'marka'], 'approval_template' => ['isletme', 'tarih', 'saat', 'marka']] as $field => $allowed) {
            preg_match_all('/\{([^{}]+)\}/u', $data[$field], $matches);
            if (array_diff($matches[1], $allowed)) throw ValidationException::withMessages([$field => 'Şablonda desteklenmeyen bir değişken var.']);
        }
        if (!str_contains($data['verification_template'], '{kod}')) throw ValidationException::withMessages(['verification_template' => 'Doğrulama mesajında {kod} bulunmalıdır.']);
        foreach (['{isletme}', '{tarih}', '{saat}'] as $field) {
            if (!str_contains($data['approval_template'], $field)) throw ValidationException::withMessages(['approval_template' => 'Onay mesajında {isletme}, {tarih} ve {saat} bulunmalıdır.']);
        }
        AppointmentSmsSetting::central();
        DB::transaction(function () use ($data) {
            $settings = AppointmentSmsSetting::lockForUpdate()->findOrFail(1);
            if (empty($data['auth_token'])) unset($data['auth_token']);
            $settings->fill($data);
            if ($settings->enabled && !$settings->ready()) throw ValidationException::withMessages(['enabled' => 'Etkinleştirmek için Account SID, Auth Token ve gönderici numarası veya Messaging Service SID girin.']);
            $settings->save();
        });
        return back()->with('success', 'Merkezi randevu SMS ayarları kaydedildi.');
    }

    public function retry(int $id, AppointmentSms $sms)
    {
        $this->authorizeAdmin();
        $message = AppointmentSmsMessage::findOrFail($id);
        abort_unless(in_array($message->status, ['failed', 'pending']), 422);
        AppointmentSmsMessage::whereKey($id)->where('status', 'failed')->update(['status' => 'pending', 'error_code' => null]);
        $sms->dispatchApproval($message->appointment_id);
        return back()->with('success', 'Gönderim kontrol edildi. Güncel sonucu listeden görebilirsiniz.');
    }
}
