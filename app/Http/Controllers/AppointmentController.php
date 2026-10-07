<?php

namespace App\Http\Controllers;

use App\Models\{Appointment, AppointmentProfile, SupportTicket};
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
        $directoryServices = \App\Models\BookingService::where('is_active', true)->orderBy('name')->get();
        $offerings = DB::table('booking_offerings')->where('appointment_profile_id', $profile->id)->get()->map(fn ($row) => $row->vehicle_type.':'.$row->booking_service_id)->all();
        return view('appointments.settings', compact('profile', 'directoryServices', 'offerings'));
    }

    public function notifications(AppointmentBooking $booking)
    {
        abort_unless(in_array(auth()->user()->type, ['owner', 'super admin'], true), 403);
        $isAdmin = auth()->user()->type === 'super admin';
        $count = 0;
        $items = collect();
        if (!$isAdmin) {
            $profile = AppointmentProfile::where('owner_id', auth()->id())->first();
            if ($profile) $booking->expirePending($profile->id);
            $query = Appointment::whereHas('profile', fn ($query) => $query->where('owner_id', auth()->id()))
                ->where(fn ($query) => $query->where('status', 'pending')->orWhere(fn ($cancelled) => $cancelled
                    ->where('status', 'cancelled')->where('cancelled_by_customer', true)->whereNull('cancellation_read_at')));
            $count = (clone $query)->count();
            $items = $query->orderByDesc('updated_at')->orderByDesc('id')->limit(10)->get()->map(fn ($appointment) => [
                'id' => $appointment->id,
                'title' => $appointment->customer_name.($appointment->cancelled_by_customer ? ' randevusunu iptal etti' : ' randevu talep etti'),
                'date' => dateFormat($appointment->starts_at).' · '.timeFormat($appointment->starts_at),
                'url' => route('appointments.index', ['status' => $appointment->status, 'date' => $appointment->starts_at->toDateString()]),
                'type' => 'appointment',
                'sort_at' => $appointment->updated_at->getTimestamp(),
            ]);
        }
        if (\Illuminate\Support\Facades\Schema::hasTable('support_tickets')) {
            $tickets = SupportTicket::query()
                ->when(!$isAdmin, fn ($query) => $query->where('owner_id', auth()->id()))
                ->where($isAdmin ? 'admin_unread' : 'owner_unread', true);
            $count += (clone $tickets)->count();
            $items = $items->concat($tickets->with('owner')->orderByDesc('last_message_at')->orderByDesc('id')->limit(10)->get()->map(fn ($ticket) => [
                'id' => $ticket->id,
                'type' => 'support',
                'title' => 'Destek #'.$ticket->id.' · '.$ticket->subject,
                'date' => ($isAdmin ? ($ticket->owner?->name ?? 'İşletme').' · ' : '').dateFormat($ticket->last_message_at).' · '.timeFormat($ticket->last_message_at),
                'url' => route('support.show', $ticket->id),
                'sort_at' => $ticket->last_message_at?->getTimestamp() ?? 0,
            ]));
        }
        if (!$isAdmin && \Illuminate\Support\Facades\Schema::hasTable('announcement_recipients')) {
            $announcements=\App\Models\AnnouncementRecipient::where('owner_id',auth()->id())->whereNull('read_at')->whereHas('announcement',fn($q)=>$q->where('published',true));
            $count+=(clone $announcements)->count();
            $items=$items->concat($announcements->with('announcement')->orderByDesc('id')->limit(10)->get()->map(fn($r)=>[
                'id'=>$r->announcement_id,'type'=>'announcement','title'=>$r->announcement->title,
                'date'=>dateFormat($r->announcement->created_at).' · '.timeFormat($r->announcement->created_at),
                'url'=>route('announcements.show',$r->announcement_id),'sort_at'=>$r->announcement->created_at->getTimestamp(),
            ]));
        }
        $items = $items->sortByDesc('sort_at')->take(10)->map(fn ($item) => array_diff_key($item, ['sort_at' => true]))->values();
        return response()->json(['count' => $count, 'items' => $items])->header('Cache-Control', 'private, no-store');
    }

    public function saveSettings(Request $request, AppointmentBooking $booking)
    {
        $profile = $this->profile($booking);
        $data = $request->validate([
            'display_name' => 'required|string|max:150', 'is_active' => 'nullable|boolean',
            'hours' => 'nullable|array:1,2,3,4,5,6,7', 'hours.*' => 'array|max:24',
            'hours.*.*' => 'integer|min:0|max:23',
            'cancellation_cutoff_hours' => 'sometimes|required|integer|min:0|max:168',
            'pending_timeout_hours' => 'sometimes|required|integer|min:1|max:168',
            'directory_visible' => 'sometimes|required|boolean',
            'directory_region' => ['nullable', 'required_if:directory_visible,1', Rule::in(array_keys(\App\Services\BookingDirectory::regions()))],
            'public_address' => 'nullable|string|max:500',
            'offerings' => 'nullable|array|max:300',
            'offerings.*' => 'required|string|max:60|distinct',
        ], [], ['display_name' => 'İşletme adı', 'hours' => 'Çalışma saatleri']);
        $offeringRows = [];
        foreach ($data['offerings'] ?? [] as $offering) {
            [$vehicle, $serviceId] = array_pad(explode(':', $offering, 2), 2, null);
            $service = \App\Models\BookingService::where('is_active', true)->find($serviceId);
            if (!$service || !in_array($vehicle, $service->vehicle_types, true) || !array_key_exists($vehicle, config('booking_directory.vehicles'))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['offerings'=>'Geçersiz taşıt veya hizmet seçimi.']);
            }
            $offeringRows[] = ['appointment_profile_id'=>$profile->id, 'vehicle_type'=>$vehicle, 'booking_service_id'=>$service->id];
        }
        if ($request->boolean('directory_visible') && !$offeringRows) throw \Illuminate\Validation\ValidationException::withMessages(['offerings'=>'Listelenmek için en az bir hizmet seçin.']);
        $hours = [];
        foreach (AppointmentProfile::days() as $day => $label) {
            $hours[$day] = array_values(array_unique(array_map('intval', $data['hours'][$day] ?? [])));
            sort($hours[$day]);
        }
        DB::transaction(function () use ($profile, $data, $hours, $request, $offeringRows) {
            $profile = AppointmentProfile::lockForUpdate()->findOrFail($profile->id);
            $profile->update(['display_name' => $data['display_name'], 'is_active' => $request->boolean('is_active'), 'weekly_hours' => $hours,
                'cancellation_cutoff_hours' => $data['cancellation_cutoff_hours'] ?? $profile->cancellation_cutoff_hours,
                'pending_timeout_hours' => $data['pending_timeout_hours'] ?? $profile->pending_timeout_hours]);
            if ($request->exists('directory_visible')) {
                $profile->update(['directory_visible'=>$request->boolean('directory_visible'), 'directory_region'=>$data['directory_region'] ?? null, 'public_address'=>$data['public_address'] ?? null]);
                DB::table('booking_offerings')->where('appointment_profile_id', $profile->id)->delete();
                if ($offeringRows) DB::table('booking_offerings')->insert($offeringRows);
            }
        });
        return back()->with('success', 'Randevu ayarları kaydedildi.');
    }

    public function index(Request $request, AppointmentBooking $booking)
    {
        $profile = $this->profile($booking);
        $data = $request->validate(['status' => ['nullable', Rule::in(array_keys(Appointment::statuses()))], 'date' => 'nullable|date_format:Y-m-d']);
        $booking->expirePending($profile->id);
        $appointments = $profile->appointments()
            ->when($data['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($data['date'] ?? null, fn ($query, $date) => $query->whereDate('starts_at', $date))
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 WHEN status = 'approved' THEN 1 ELSE 2 END")
            ->orderBy('starts_at')->paginate(20)->withQueryString();
        $profile->appointments()->whereIn('id', $appointments->pluck('id'))->where('cancelled_by_customer', true)
            ->whereNull('cancellation_read_at')->update(['cancellation_read_at' => now()]);
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
        elseif ($data['status'] === 'approved' && !$appointment->phone_verified_at) $text .= ' SMS gönderilmedi. Randevu durumu müşterinin takip sayfasında güncellendi.';
        return back()->with('success', $text);
    }
}
