<?php
namespace App\Services;

use App\Models\{BusinessGroup,User};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BranchPermissions
{
    public const MODULES = ['clients'=>'Müşteriler','vehicles'=>'Araçlar','services'=>'Servisler',
        'stock'=>'Ürünler ve stok','invoices'=>'Faturalar','finance'=>'Finansal raporlar ve giderler'];
    private const ABILITIES = [
        'clients'=>['client'], 'vehicles'=>['vehicle','vehicle type','vehicle brand'],
        'services'=>['service','service type'], 'stock'=>['item','supplier','unit','tax'],
        'invoices'=>['invoice','invoice payment'], 'finance'=>['expense','income report','expense report','profit loss report','profile and loss report','service report'],
    ];

    public static function grant(BusinessGroup $group, User $owner, User $actor): ?object
    {
        if ($actor->type !== 'employee' || !$actor->is_active || $actor->client_archived_at) return null;
        $home=User::where('type','owner')->find($actor->parent_id);
        if (!$home || !$home->is_active || $home->subscription_suspended_at || app(DemoLifecycle::class)->expired($home)) return null;
        // Leaving the home branch revokes delegated access as well as leaving the target branch.
        if (!DB::table('business_branches')->where('group_id',$group->id)->where('owner_id',$actor->parent_id)->exists()) return null;
        return DB::table('branch_staff_access')->where('group_id',$group->id)->where('owner_id',$owner->id)->where('user_id',$actor->id)->first();
    }
    public static function ability(object $grant, string $ability): bool
    {
        if (!preg_match('/^(manage|show|create|edit|delete) (.+)$/D',$ability,$parts)) return false;
        if (in_array($parts[1],['create','edit'],true) && !$grant->can_write) return false;
        if ($parts[1] === 'delete' && !$grant->can_delete) return false;
        $modules=json_decode($grant->modules,true) ?: [];
        foreach($modules as $module) if(in_array($parts[2],self::ABILITIES[$module] ?? [],true)) return true;
        return false;
    }
    public static function request(object $grant, $request): bool
    {
        $name=(string)$request->route()?->getName();
        $routes=['clients'=>['client.*'], 'vehicles'=>['vehicle.*','vehicle-type.*','vehicle-brand.*','vehicle-qr.*','vehicle-catalog.*','vehicle-cleanup.*'],
            'services'=>['service.*','service-type.*','calendar','get.service.type'],
            'stock'=>['item.*','item-category.*','supplier.*','unit.*','tax.*','inventory.setup*','history.stock'],
            'invoices'=>['invoice.*','history.invoices'], 'finance'=>['expense.*','report.*']];
        $allowed=['home','dashboard'];
        foreach(json_decode($grant->modules,true) ?: [] as $module) $allowed=array_merge($allowed,$routes[$module] ?? []);
        if (!Str::is($allowed,$name)) return false;
        if ($request->isMethod('get') || $request->isMethod('head')) return true;
        if (!$grant->can_write) return false;
        if (($request->isMethod('delete') || Str::is(['*destroy*','*delete*','*cleanup*'],$name)) && !$grant->can_delete) return false;
        return true;
    }
}
