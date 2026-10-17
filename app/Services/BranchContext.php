<?php
namespace App\Services;

use App\Models\{BusinessGroup, User};
use Illuminate\Support\Facades\{Crypt, DB};

class BranchContext
{
    // Deliberately exclude platform management, impersonation, credentials and payment gateways.
    public static function businessRoute(string $name): bool
    {
        return \Illuminate\Support\Str::is([
            'home','dashboard','client.*','vehicle.*','vehicle-type.*','vehicle-brand.*',
            'vehicle-qr.*','vehicle-catalog.*','service.*','service-photo.*','service-type.*',
            'item.*','item-category.*','supplier.*','tax.*','unit.*','invoice.*','expense.*',
            'quotation.*','report.*','history.*','employee.*','contact.*','note.*','calendar','get.service.type',
            'vehicle-cleanup.*','inventory.setup*','education.index','theme.settings','support.*',
            'reviews.index','announcements.index','announcements.show',
            'appointments.index','appointments.notifications','appointments.settings',
            'appointments.settings.save','appointments.status','business-setup.*',
            'setup-guide.*','guide.*','onboarding.*','business-warnings.*',
            'setting.index','setting.company','setting.invoice-business','setting.general','setting.theme',
        ], $name);
    }

    public static function current(): ?array
    {
        return app()->bound('request') ? request()->attributes->get('branch_context') : null;
    }

    public static function token(BusinessGroup $group, User $owner, User $actor): string
    {
        abort_unless(($actor->type === 'branch_manager' && $group->manager_user_id === $actor->id
            || BranchPermissions::grant($group,$owner,$actor))
            && $group->is_active && $group->owners()->where('users.id', $owner->id)->exists(), 403);
        return Crypt::encryptString(json_encode(['actor'=>$actor->id, 'owner'=>$owner->id,
            'group'=>$group->id, 'version'=>$group->version, 'recovery'=>(int)$owner->recovery_version,
            'expires'=>now()->addDays(7)->timestamp], JSON_THROW_ON_ERROR));
    }

    public static function decode(string $token, User $actor): array
    {
        try { $data = json_decode(Crypt::decryptString($token), true, 16, JSON_THROW_ON_ERROR); }
        catch (\Throwable $e) { abort(409, 'Şube bağlantısı geçersiz. Merkez ekranından şubeyi yeniden açın.'); }
        abort_unless(is_array($data) && ($data['actor'] ?? null) === $actor->id
            && in_array($actor->type,['branch_manager','employee'],true) && is_int($data['owner'] ?? null)
            && is_int($data['group'] ?? null) && ($data['expires'] ?? 0) >= now()->timestamp, 409,
            'Şube bağlantısının süresi doldu. Merkez ekranından şubeyi yeniden açın.');
        return $data;
    }

    public static function validate(array $data, User $actor, bool $lock = false): array
    {
        $query = BusinessGroup::whereKey($data['group']);
        if ($lock) $query->sharedLock();
        $group = $query->first();
        $owner = User::where('type','owner')->find($data['owner']);
        abort_unless($group && $owner && $group->is_active && $actor->is_active
            && ($group->manager_user_id === $actor->id && $actor->type==='branch_manager' || BranchPermissions::grant($group,$owner,$actor))
            && $group->version === ($data['version'] ?? null)
            && (int)$owner->recovery_version === ($data['recovery'] ?? null)
            && DB::table('business_branches')->where('group_id',$group->id)->where('owner_id',$owner->id)->exists(),
            409, 'Şube erişimi veya kayıtları değişti. Merkez ekranından şubeyi yeniden açın.');
        abort_unless($owner->is_active && !$owner->subscription_suspended_at, 403, 'Bu şubenin hesabı veya aboneliği askıda.');
        $grant=BranchPermissions::grant($group,$owner,$actor);
        return compact('group', 'owner','grant');
    }
}
