<?php
namespace App\Services;

use App\Models\{BusinessGroup, User};
use App\Services\Recovery\RecoveryWriteLock;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Validation\ValidationException;

class BusinessGroups
{
    private function admin(): void { abort_unless(auth()->user()?->type === 'super admin', 403); }

    public function create(array $data): BusinessGroup
    {
        $this->admin();
        return DB::transaction(function () use ($data) {
            // A separate identity: never convert a shop owner or create a demo subscription.
            $manager = User::create(['name'=>$data['manager_name'], 'email'=>$data['email'],
                'password'=>Hash::make($data['password']), 'type'=>'branch_manager', 'parent_id'=>0,
                'is_active'=>1, 'lang'=>'tr']);
            $manager->email_verified_at = now();
            $manager->save();
            $group = BusinessGroup::create(['name'=>$data['name'], 'manager_user_id'=>$manager->id]);
            AdminAudit::record('branches.created', null, [], ['group_id'=>$group->id, 'name'=>$group->name]);
            BranchActivity::record($group->id,null,'group.created');
            return $group->refresh();
        });
    }

    public function membership(int $groupId, int $ownerId, int $expectedVersion, bool $attach): void
    {
        $this->admin();
        // Same order as branch HTTP requests: recovery file lock, then group DB lock.
        app(RecoveryWriteLock::class)->shared($ownerId, function () use ($groupId, $ownerId, $expectedVersion, $attach) {
            DB::transaction(function () use ($groupId, $ownerId, $expectedVersion, $attach) {
                $group = BusinessGroup::whereKey($groupId)->lockForUpdate()->firstOrFail();
                $this->version($group, $expectedVersion);
                $owner = User::where('type', 'owner')->whereKey($ownerId)->lockForUpdate()->firstOrFail();
                abort_if(DB::table('business_recovery_locks')->where('owner_id', $ownerId)->exists(), 409, 'Veri kurtarma işlemi tamamlandıktan sonra tekrar deneyin.');
                $membership = DB::table('business_branches')->where('owner_id', $ownerId)->first();
                if ($attach) {
                    if ($membership) throw ValidationException::withMessages(['owner_id'=>'Bu işletme zaten bir şubeye bağlı. Önce mevcut gruptan ayırın.']);
                    DB::table('business_branches')->insert(['owner_id'=>$ownerId, 'group_id'=>$groupId, 'created_at'=>now(), 'updated_at'=>now()]);
                } else {
                    abort_unless($membership && (int)$membership->group_id === $groupId, 409, 'Şube bağlantısı değişmiş. Sayfayı yenileyin.');
                    abort_if(DB::table('branch_stock_transfers')->where('status','pending')->where(fn($q)=>$q->where('source_owner_id',$ownerId)->orWhere('target_owner_id',$ownerId))->exists(),409,'Önce bu şubenin bekleyen stok transferlerini teslim alın veya iade ederek iptal edin.');
                    abort_if(\Schema::hasTable('branch_referrals') && DB::table('branch_referrals')->where('status','pending')->where(fn($q)=>$q->where('source_owner_id',$ownerId)->orWhere('target_owner_id',$ownerId))->exists(),409,'Önce bekleyen araç yönlendirmelerini kabul edin veya iptal / reddedin.');
                    DB::table('branch_staff_access')->where('group_id',$groupId)
                        ->where(fn($q)=>$q->where('owner_id',$ownerId)->orWhereIn('user_id',DB::table('users')->where('parent_id',$ownerId)->select('id')))->delete();
                    DB::table('business_branches')->where('owner_id', $ownerId)->where('group_id', $groupId)->delete();
                }
                $group->increment('version');
                BranchActivity::record($groupId,$ownerId,$attach?'branch.attached':'branch.detached');
                AdminAudit::record($attach ? 'branches.attached' : 'branches.detached', $owner->id,
                    ['group_id'=>$attach ? null : $groupId], ['group_id'=>$attach ? $groupId : null]);
            }, 3);
        });
    }

    public function status(int $groupId, int $expectedVersion, bool $active): void
    {
        $this->admin();
        DB::transaction(function () use ($groupId, $expectedVersion, $active) {
            $group = BusinessGroup::whereKey($groupId)->lockForUpdate()->firstOrFail();
            $this->version($group, $expectedVersion);
            $group->update(['is_active'=>$active, 'version'=>$group->version + 1]);
            BranchActivity::record($groupId,null,'group.status',['active'=>$active]);
            AdminAudit::record('branches.status', null, [], ['group_id'=>$groupId, 'is_active'=>$active]);
        });
    }

    private function version(BusinessGroup $group, int $expected): void
    {
        abort_unless($group->version === $expected, 409, 'Şube yapısı başka bir işlemde değişti. Sayfayı yenileyin.');
    }
}
