<?php
namespace App\Services;

use App\Models\{Subscription, User};
use Illuminate\Support\Facades\{DB, Schema};

class DemoLifecycle
{
    public function prepare(User $owner): void
    {
        if ($owner->type !== 'owner' || !Schema::hasColumn('users','demo_started_at')) return;
        $plan=Subscription::find($owner->subscription);
        if (!$plan) return;
        if ((int)$plan->vehicle_limit !== 50) {
            if (!$owner->paid_subscription_at) $owner->paid_subscription_at=now();
            return;
        }
        // Demo cannot be restarted by signing in or assigning the same plan again.
        if (!$owner->demo_started_at) {
            $owner->demo_started_at=now();
            $owner->last_business_login_at=now();
            $owner->demo_expires_at=now()->addDays(14);
        }
        $owner->subscription_expire_date=\Carbon\Carbon::parse($owner->demo_expires_at)->toDateString();
    }

    public function expired(User $owner): bool
    {
        return (int) optional(Subscription::find($owner->subscription))->vehicle_limit === 50
            && $owner->demo_expires_at && now()->greaterThanOrEqualTo(\Carbon\Carbon::parse($owner->demo_expires_at));
    }

    public function recordLogin(User $user): void
    {
        if (!Schema::hasColumn('users','last_business_login_at')) return;
        $ownerId=$user->type==='owner' ? $user->id : (!in_array($user->type,['super admin','client'],true) ? $user->parent_id : null);
        if (!$ownerId) return;
        // Same owner lock as cleanup and all inventory/invoice writes.
        DB::transaction(function() use($ownerId) {
            $owner=User::where('type','owner')->whereKey($ownerId)->lockForUpdate()->first();
            if ($owner) DB::table('users')->where('id',$ownerId)->update(['last_business_login_at'=>now()]);
        });
    }
}
