<?php
namespace App\Http\Controllers;

use App\Models\{PackageTransaction, Subscription, User, VehicleCapacityRequest};
use App\Services\VehicleCapacity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VehicleCapacityController extends Controller
{
    public function index()
    {
        abort_unless(in_array(auth()->user()->type, ['owner', 'super admin'], true), 403);
        $admin = auth()->user()->type === 'super admin';
        $requests = VehicleCapacityRequest::with('owner')->when(!$admin, fn ($q) => $q->where('owner_id', auth()->id()))->latest()->paginate(20);
        return view('subscription.capacity', compact('requests', 'admin'));
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->type === 'owner', 403);
        $request->validate(['confirm' => 'accepted', 'quoted_amount' => 'required|numeric|min:0.01']);
        DB::transaction(function () use ($request) {
            $owner = User::whereKey(auth()->id())->lockForUpdate()->firstOrFail();
            $plan = Subscription::whereKey($owner->subscription)->lockForUpdate()->first();
            if (!$plan || (int) $plan->vehicle_limit !== 3000 || $plan->vehicle_block_amount === null || (float) $plan->vehicle_block_amount <= 0) {
                throw ValidationException::withMessages(['capacity' => 'Ek kapasite yalnızca 3.000 araçlık paket için ve ücreti belirlendiğinde alınabilir.']);
            }
            if (VehicleCapacityRequest::where('owner_id', $owner->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['capacity' => 'Onay bekleyen bir ek kapasite talebiniz zaten var.']);
            }
            if (number_format((float)$request->quoted_amount, 2, '.', '') !== number_format((float)$plan->vehicle_block_amount, 2, '.', '')) {
                throw ValidationException::withMessages(['capacity' => 'Ek kapasite ücreti değişmiş. Sayfayı yenileyip yeni tutarı kontrol edin.']);
            }
            VehicleCapacityRequest::create(['owner_id' => $owner->id, 'subscription_id' => $plan->id,
                'vehicles' => 500, 'amount' => $plan->vehicle_block_amount, 'status' => 'pending']);
        }, 5);
        return redirect()->route('capacity.index')->with('success', '500 araçlık ek kapasite talebiniz oluşturuldu. Ödeme kontrolü ve süper admin onayından sonra kapasiteniz artar.');
    }

    public function review(Request $request, int $id)
    {
        abort_unless(auth()->user()->type === 'super admin', 403);
        $data = $request->validate(['decision' => 'required|in:approved,rejected']);
        DB::transaction(function () use ($id, $data) {
            $record = VehicleCapacityRequest::findOrFail($id);
            $owner = User::whereKey($record->owner_id)->lockForUpdate()->firstOrFail();
            $record = VehicleCapacityRequest::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($record->status !== 'pending') return;
            if ($data['decision'] === 'approved') {
                $plan = Subscription::find($owner->subscription);
                if (!$plan || (int) $plan->vehicle_limit !== 3000 || $plan->id !== $record->subscription_id) {
                    throw ValidationException::withMessages(['capacity' => 'İşletmenin paketi değişmiş. Bu talep onaylanamaz; yeni talep oluşturulmalıdır.']);
                }
                PackageTransaction::transactionData(['user_id' => $owner->id, 'subscription_id' => $plan->id,
                    'amount' => $record->amount, 'status' => 'Success', 'payment_type' => '500 araç ek kapasite',
                    'subscription_transactions_id' => 'vehicle-capacity-' . $record->id]);
            }
            $record->update(['status' => $data['decision'], 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
        }, 5);
        return back()->with('success', 'Ek kapasite talebi işlendi.');
    }
}
