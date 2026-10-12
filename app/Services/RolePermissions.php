<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class RolePermissions
{
    public static function isClient(string $name): bool
    {
        return strtolower(trim($name)) === 'client';
    }

    public static function label(string $name): string
    {
        return ['client' => 'Müşteri', 'employee' => 'Personel', 'owner' => 'İşletme sahibi',
            'super admin' => 'Süper yönetici', 'manager' => 'Yönetici'][$name] ?? $name;
    }

    public static function active(string $name, User $user): bool
    {
        if (preg_match('/\b(n8n|openai|twilio|stripe|paypal|flutterwave|razorpay|paystack)\b/i', $name)) {
            return false;
        }
        return $user->type === 'super admin' || !in_array($name, [
            'manage email settings', 'manage notification', 'edit notification',
            'manage sms settings', 'manage seo settings', 'manage google recaptcha settings',
        ], true);
    }

    public static function available(User $user): Collection
    {
        return $user->roles->flatMap(fn ($role) => $role->permissions)
            ->unique('id')->filter(fn ($permission) => self::active($permission->name, $user))->values();
    }

    public static function groups(Collection $permissions): Collection
    {
        // Match full module suffixes: "invoice payment" must not also appear under "invoice".
        return $permissions->groupBy(function ($permission) {
            $module = preg_replace('/^(manage|create|edit|delete|show) /i', '', $permission->name);
            return $module;
        });
    }
}
