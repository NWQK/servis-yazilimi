<?php

namespace App\Http\Controllers;

use App\Models\{Appointment, AppointmentProfile};
use App\Services\AppointmentBooking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AppointmentController extends Controller
{
    private function profile(AppointmentBooking $booking): AppointmentProfile
    {
        abort_unless(auth()->user()->type === 'owner', 403);
        return $booking->profileForOwner(auth()->id());
    }

    public function settings(AppointmentBooking $booking)
    {
        $profile = $this->profile($booking);
        return view('appointments.settings', compact('profile'));
    }

    public function notifications()
    {
        abort_unless(auth()->user()->type === 'owner', 403);
        $query = Appointment::whereHas('profile', fn ($query) => $query->where('owner_id', auth()->id()))
            ->where('status', 'pending');
        $count = (clone $query)->count();
        $items = $query->orderByDesc('id')->limit(10)->get()->map(fn ($appointment) => [
            'id' => $appointment->id,
            'title' => $appointment->customer_name.' randevu talep etti',
            'date' => dateFormat($appointment->starts_at).' · '.timeFormat($appointment->starts_at),
            'url' => route('appointments.index', ['status' => 'pending', 'date' => $appointment->starts_at->toDateString()]),
        ]);
        return response()->json(['count' => $count, 'items' => $items])->header('Cache-Control', 'private, no-store');
    }

    public function saveSettings(Request $request, AppointmentBooking $booking)
    {
        $profile = $this->profile($booking);
        $data = $request->validate([
            'display_name' => 'required|string|max:150', 'is_active' => 'nullable|boolean',
            'hours' => 'nullable|array:1,2,3,4,5,6,7', 'hours.*' => 'array|max:24',
            'hours.*.*' => 'integer|min:0|max:23',
        ], [], ['display_name' => 'İşletme adı', 'hours' => 'Çalışma saatleri']);
        $hours = [];
        foreach (AppointmentProfile::days() as $day => $label) {
            $hours[$day] = array_values(array_unique(array_map('intval', $data['hours'][$day] ?? [])));
            sort($hours[$day]);
        }
        DB::transaction(function () use ($profile, $data, $hours, $request) {
            $profile = AppointmentProfile::lockForUpdate()->findOrFail($profile->id);
            $profile->update(['display_name' => $data['display_name'], 'is_active' => $request->boolean('is_active'), 'weekly_hours' => $hours]);
        });
        return back()->with('success', 'Randevu ayarları kaydedildi.');
    }

    public function index(Request $request, AppointmentBooking $booking)
    {
        $profile = $this->profile($booking);
        $data = $request->validate(['status' => ['nullable', Rule::in(array_keys(Appointment::statuses()))], 'date' => 'nullable|date_format:Y-m-d']);
        $appointments = $profile->appointments()
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['date'] ?? null, fn ($query, $date) => $query->whereDate('starts_at', $date))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN status = 'approved' THEN 1 ELSE 2 END")
            ->orderBy('starts_at')->paginate(20)->withQueryString();
        $pendingCount = $profile->appointments()->where('status', 'pending')->count();
        return view('appointments.index', compact('profile', 'appointments', 'pendingCount'));
    }

    public function status(Request $request, int $id, AppointmentBooking $booking)
    {
        $profile = $this->profile($booking);
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Appointment::statuses()))]]);
        $appointment = $booking->changeStatus($profile, $id, $data['status']);
        $message = $data['status'] === 'approved' ? app(\App\Services\AppointmentSms::class)->dispatchApproval($id) : null;
        $text = 'Randevu durumu güncellendi.';
        if ($message) $text .= $message->status === 'accepted' ? ' Onay SMS’i gönderim için İleti Merkezi’ne iletildi.' : ' Onay SMS’i gönderimi tamamlanamadı; süper admin SMS panelinden kontrol edebilir.';
        elseif ($data['status'] === 'approved' && !$appointment->phone_verified_at) $text .= ' Bu eski kaydın telefonu doğrulanmadığı için SMS gönderilmedi.';
        return back()->with('success', $text);
    }
}
