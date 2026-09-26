<?php

namespace App\Http\Controllers;

use App\Models\{AppointmentProfile, AppointmentSmsChallenge, AppointmentSmsSetting};
use App\Services\AppointmentSms;
use App\Services\AppointmentBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicAppointmentController extends Controller
{
    private function profile(string $publicId): AppointmentProfile
    {
        return AppointmentProfile::where('public_id', $publicId)->whereHas('owner', fn ($query) => $query->where('type', 'owner'))->firstOrFail();
    }

    private function page(string $view, array $data)
    {
        return response()->view($view, $data)->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function show(Request $request, string $publicId, AppointmentBooking $booking)
    {
        $profile = $this->profile($publicId);
        $data = $request->validate(['date' => 'nullable|date_format:Y-m-d|after_or_equal:today|before_or_equal:' . today()->addDays(AppointmentBooking::BOOKING_WINDOW_DAYS)->toDateString()]);
        $date = $data['date'] ?? today()->toDateString();
        $hours = $booking->availableHours($profile, $date);
        $requestKey = (string) Str::uuid();
        $smsReady = AppointmentSmsSetting::central()->ready();
        return $this->page('appointments.public', compact('profile', 'date', 'hours', 'requestKey', 'smsReady'));
    }

    public function store(Request $request, string $publicId, AppointmentBooking $booking)
    {
        $profile = $this->profile($publicId);
        // A honeypot and per-IP throttle are interim safeguards, not phone verification.
        abort_if($request->filled('website'), 422);
        $data = $request->validate([
            'customer_name' => 'required|string|max:150', 'phone' => ['required', 'string', 'regex:/\A5[0-9]{9}\z/'],
            'license_plate' => 'nullable|string|max:20', 'notes' => 'nullable|string|max:1000',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today|before_or_equal:' . today()->addDays(AppointmentBooking::BOOKING_WINDOW_DAYS)->toDateString(),
            'hour' => 'required|integer|min:0|max:23', 'request_key' => 'required|uuid',
        ], ['phone.regex' => 'Başında 0 veya +90 olmadan, 5 ile başlayan 10 haneli cep telefonu numaranızı girin. Örnek: 5551234567.'],
            ['customer_name' => 'Ad soyad', 'phone' => 'Telefon numarası', 'date' => 'Randevu tarihi', 'hour' => 'Randevu saati']);
        $data['phone'] = '+90'.$data['phone'];
        $challenge = app(AppointmentSms::class)->start($profile, $data, $request->ip());
        return redirect()->route('booking.verify', [$profile->public_id, $challenge->token]);
    }

    private function challenge(AppointmentProfile $profile, string $token): AppointmentSmsChallenge
    {
        return AppointmentSmsChallenge::where('appointment_profile_id', $profile->id)->where('token', $token)->firstOrFail();
    }

    public function verification(string $publicId, string $token)
    {
        $profile = $this->profile($publicId);
        $challenge = $this->challenge($profile, $token);
        if ($challenge->appointment_id) return redirect($profile->appointments()->findOrFail($challenge->appointment_id)->statusUrl());
        $maskedPhone = '+90 ••• ••• '.substr($challenge->payload['phone'], -4);
        return $this->page('appointments.verify', compact('profile', 'challenge', 'maskedPhone'));
    }

    public function verify(Request $request, string $publicId, string $token, AppointmentSms $sms)
    {
        $profile = $this->profile($publicId);
        $request->validate(['code' => 'required|digits:6']);
        $appointment = $sms->verify($this->challenge($profile, $token), $profile, $request->input('code'));
        return redirect($appointment->statusUrl());
    }

    public function resend(string $publicId, string $token, AppointmentSms $sms)
    {
        $profile = $this->profile($publicId);
        $sms->resend($this->challenge($profile, $token), $profile);
        return back()->with('success', 'Yeni kod istendi. Gönderim durumunu aşağıdan kontrol edebilirsiniz.');
    }

    public function status(string $publicId, string $token)
    {
        $profile = $this->profile($publicId);
        $appointment = $profile->appointments()->where('public_token', $token)->firstOrFail();
        return $this->page('appointments.status', compact('profile', 'appointment'));
    }
}
