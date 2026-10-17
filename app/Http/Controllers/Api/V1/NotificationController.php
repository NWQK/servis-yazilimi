<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\{Appointment, User};
use App\Services\AppointmentAttention;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    private function owner(Request $request): int
    {
        abort_unless($request->user()->type === 'owner', 403, 'Randevuları yalnızca işletme sahibi görüntüleyebilir.');
        return (int) $request->attributes->get('mobile_owner');
    }

    private function query(Request $request)
    {
        $owner = $this->owner($request);
        $version = (int) User::findOrFail($owner)->recovery_version;
        return app(AppointmentAttention::class)->query($owner, true)
            ->select('appointments.*')
            ->selectSub(DB::table('mobile_notification_reads')->selectRaw('1')
                ->whereColumn('appointment_id', 'appointments.id')->whereColumn('event', 'appointments.status')
                ->where('user_id', $request->user()->id)->where('recovery_version', $version)->limit(1), 'is_read');
    }

    public function index(Request $request)
    {
        $input = $request->validate(['page'=>'sometimes|integer|min:1', 'per_page'=>'sometimes|integer|min:1|max:50', 'summary'=>'sometimes|boolean']);
        if ($request->user()->type !== 'owner') {
            return response()->json(['data'=>[], 'meta'=>['available'=>false, 'unread_count'=>0, 'page'=>1, 'has_more'=>false]]);
        }
        $query = $this->query($request);
        // Wrap the selected read flag; works on SQLite and MySQL without vendor-specific concatenation.
        $unread = DB::query()->fromSub((clone $query)->toBase(), 'inbox')->whereNull('is_read')->count();
        if ($request->boolean('summary')) return response()->json(['data'=>[], 'meta'=>['available'=>true,'unread_count'=>$unread]]);
        $page = $query->orderByDesc('updated_at')->orderByDesc('id')->simplePaginate($input['per_page'] ?? 20);
        return response()->json(['data'=>collect($page->items())->map(fn ($a) => [
            'id'=>$a->id, 'event'=>$a->status, 'read'=>(bool) $a->is_read,
            'title'=>$a->customer_name.($a->status === 'cancelled' ? ' randevusunu iptal etti' : ' randevu talep etti'),
            'starts_at'=>$a->starts_at?->toIso8601String(),
        ]), 'meta'=>['available'=>true, 'unread_count'=>$unread, 'page'=>$page->currentPage(), 'has_more'=>$page->hasMorePages()]]);
    }

    public function read(Request $request, int $id)
    {
        $input = $request->validate(['event'=>'required|in:pending,cancelled']);
        $appointment = $this->query($request)->whereKey($id)->firstOrFail();
        abort_unless($appointment->status === $input['event'], 409, 'Randevu durumu değişti. Listeyi yenileyin.');
        DB::table('mobile_notification_reads')->insertOrIgnore([
            'user_id'=>$request->user()->id, 'appointment_id'=>$id, 'event'=>$input['event'],
            'recovery_version'=>(int) User::findOrFail($this->owner($request))->recovery_version, 'read_at'=>now(),
        ]);
        return response()->noContent();
    }

    public function appointment(Request $request, int $id)
    {
        $owner = $this->owner($request);
        $appointment = Appointment::whereHas('profile', fn ($q) => $q->where('owner_id', $owner))->findOrFail($id);
        return response()->json(['data'=>[
            'id'=>$appointment->id, 'customer_name'=>$appointment->customer_name,
            'phone'=>$appointment->phone, 'license_plate'=>$appointment->license_plate, 'notes'=>$appointment->notes,
            'notification_event'=>app(AppointmentAttention::class)->query($owner,true)->whereKey($id)->exists() ? $appointment->status : null,
            'status'=>$appointment->status, 'status_label'=>Appointment::statuses()[$appointment->status] ?? $appointment->status,
            'starts_at'=>$appointment->starts_at?->toIso8601String(), 'ends_at'=>$appointment->ends_at?->toIso8601String(),
        ]]);
    }
}
