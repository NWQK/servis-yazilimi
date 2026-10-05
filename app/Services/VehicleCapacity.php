<?php
namespace App\Services;

use App\Models\{Subscription, User, Vehicle, VehicleCapacityRequest};
use Illuminate\Validation\ValidationException;

class VehicleCapacity
{
    public function summary(User $owner): array
    {
        $plan = Subscription::find($owner->subscription);
        $extra = $plan && (int) $plan->vehicle_limit === 3000
            ? (int) VehicleCapacityRequest::where('owner_id', $owner->id)->where('status', 'approved')->sum('vehicles') : 0;
        $limit = $plan && $plan->vehicle_limit !== null ? (int) $plan->vehicle_limit + $extra : null;
        $used = Vehicle::where('parent_id', $owner->id)->count();
        return ['plan' => $plan, 'limit' => $limit, 'used' => $used, 'extra' => $extra,
            'remaining' => $limit === null ? null : max(0, $limit - $used)];
    }

    // The caller must hold the owner's row lock throughout vehicle creation.
    public function assertAvailable(int $ownerId): void
    {
        $capacity = $this->summary(User::findOrFail($ownerId));
        if ($capacity['limit'] !== null && $capacity['used'] >= $capacity['limit']) {
            throw ValidationException::withMessages(['vehicle_limit' =>
                'Paketinizin ' . $capacity['limit'] . ' araçlık kapasitesi doldu. Paketim ve abonelik bölümünden daha büyük paket veya 500 araçlık ek kapasite talep edebilirsiniz.']);
        }
    }
}
