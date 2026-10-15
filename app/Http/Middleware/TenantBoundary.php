<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Database\Eloquent\Model;

/** A second boundary for bound business resources; controllers still enforce action permissions. */
class TenantBoundary
{
    public function handle($request,Closure $next)
    {
        $user=$request->user();
        if(!$user || $user->type==='super admin') return $next($request);
        $tables=['vehicles','services','service_photos','invoices','invoice_items','invoice_payments','items',
            'clients','employees','taxes','units','vehicle_types','vehicle_brands','service_types',
            'suppliers','item_categories','quotations','quotation_items','quotation_services','contacts','notice_boards','expenses'];
        foreach($request->route()->parameters() as $record)
            if($record instanceof Model && in_array($record->getTable(),$tables,true))
                abort_unless((int)$record->parent_id===(int)parentId(),in_array($record->getTable(),['suppliers','item_categories'],true)?404:403);
        if($request->routeIs('users.show','users.edit','users.update','users.destroy')){
            $id=$request->route('user'); $id=$id instanceof Model?$id->getKey():$id;
            abort_if(\App\Models\User::where('parent_id',parentId())->where('type','client')->whereKey($id)->exists(),403);
            \App\Models\User::where('parent_id',parentId())->whereNotIn('type',['owner','super admin','client'])->findOrFail($id);
        }
        return $next($request);
    }
}
