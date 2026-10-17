<?php
namespace App\Services;
use App\Models\{BusinessGroup,User};
use Illuminate\Support\Facades\DB;

class BranchStaff
{
    public static function managerGroup(?int $id=null): BusinessGroup
    {
        $actor=auth()->user();
        abort_unless($actor && $actor->is_active && in_array($actor->type,['super admin','branch_manager'],true),403);
        $query=BusinessGroup::query();
        if ($actor->type === 'branch_manager') $query->where('manager_user_id',$actor->id)->where('is_active',true);
        if ($id !== null) $query->whereKey($id);
        return $query->firstOrFail();
    }
    public function save(int $groupId, array $data): void
    {
        // Re-authorize inside the write transaction, not just in the page controller.
        DB::transaction(function()use($groupId,$data){
            $group=self::managerGroup($groupId);
            $group=BusinessGroup::whereKey($group->id)->lockForUpdate()->firstOrFail();
            abort_unless(auth()->user()->type==='super admin' || ($group->is_active && $group->manager_user_id===auth()->id()),403);
            abort_unless($group->version===(int)$data['version'],409,'Şube yapısı değişti. Sayfayı yenileyin.');
            $owner=User::where('type','owner')->findOrFail($data['owner_id']);
            $staff=User::where('type','employee')->where('is_active',1)->lockForUpdate()->findOrFail($data['user_id']);
            $members=DB::table('business_branches')->where('group_id',$group->id)->pluck('owner_id')->all();
            abort_unless(in_array($owner->id,$members) && in_array($staff->parent_id,$members),403);
            abort_if($owner->id === (int)$staff->parent_id,422,'Ana şube yetkileri mevcut personel rolünden yönetilir. Buradan ek şube erişimi verilir.');
            $key=['group_id'=>$group->id,'owner_id'=>$owner->id,'user_id'=>$staff->id];
            if ($data['action']==='revoke') DB::table('branch_staff_access')->where($key)->delete();
            else {
                $modules=array_values(array_unique($data['modules'] ?? []));
                abort_unless($modules && !array_diff($modules,array_keys(BranchPermissions::MODULES)),422);
                $write=(bool)($data['can_write'] ?? false); $delete=$write && (bool)($data['can_delete'] ?? false);
                $existing=DB::table('branch_staff_access')->where($key)->first();
                DB::table('branch_staff_access')->updateOrInsert($key,['modules'=>json_encode($modules),'can_write'=>$write,
                    'can_delete'=>$delete,'created_at'=>$existing?->created_at ?? now(),'updated_at'=>now()]);
            }
            $group->increment('version');
            BranchActivity::record($group->id,$owner->id,$data['action']==='revoke'?'staff.revoked':'staff.granted',
                ['user_id'=>$staff->id,'modules'=>$data['modules'] ?? [],'can_write'=>$write ?? false,'can_delete'=>$delete ?? false]);
        },3);
    }
}
