<?php

namespace App\Http\Controllers;

use App\Models\AppointmentProfile;
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
        $data = $request->validate(['date' => 'nullable|date_format:Y-m-d|after_or_equal:today|before_or_equal:' . today()->addDays(90)->toDateString()]);
        $date = $data['date'] ?? today()->toDateString();
        $hours = $booking->availableHours($profile, $date);
        $requestKey = (string) Str::uuid();
        return $this->page('appointments.public', compact('profile', 'date', 'hours', 'requestKey'));
    }

    public function store(Request $request, string $publicId, AppointmentBooking $booking)
    {
        $profile = $this->profile($publicId);
        // A honeypot and per-IP throttle are interim safeguards, not phone verification.
        abort_if($request->filled('website'), 422);
        $phone = preg_replace('/[\s()\-]/', '', (string) $request->input('phone'));
        if (preg_match('/^05\d{9}$/', $phone)) $phone = '+9' . $phone;
        elseif (preg_match('/^5\d{9}$/', $phone)) $phone = '+90' . $phone;
        elseif (preg_match('/^905\d{9}$/', $phone)) $phone = '+' . $phone;
        $request->merge(['phone' => $phone]);
        $data = $request->validate([
            'customer_name' => 'required|string|max:150', 'phone' => ['required', 'regex:/^\+?[1-9]\d{9,14}$/'],
            'license_plate' => 'nullable|string|max:20', 'notes' => 'nullable|string|max:1000',
            'date' => 'required|date_format:Y-m-d|after_or_equal:today|before_or_equal:' . today()->addDays(90)->toDateString(),
            'hour' => 'required|integer|min:0|max:23', 'request_key' => 'required|uuid',
        ], ['phone.regex' => 'Geçerli bir telefon numarası girin. Örnek: 0555 123 45 67.'],
            ['customer_name' => 'Ad soyad', 'phone' => 'Telefon numarası', 'date' => 'Randevu tarihi', 'hour' => 'Randevu saati']);
        $appointment = $booking->book($profile, $data);
        return redirect()->route('booking.status', [$profile->public_id, $appointment->public_token]);
    }

    public function status(string $publicId, string $token)
    {
        $profile = $this->profile($publicId);
        $appointment = $profile->appointments()->where('public_token', $token)->firstOrFail();
        return $this->page('appointments.status', compact('profile', 'appointment'));
    }
}
