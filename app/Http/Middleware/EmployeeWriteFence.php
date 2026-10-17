<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Support\Facades\DB;
class EmployeeWriteFence {
    public function handle($request,Closure $next){
        $actor=$request->user();if(!$actor || $actor->type!=='employee' || $request->isMethodSafe() || $request->routeIs('logout','branch-transfers.*'))return $next($request);
        $owner=parentId();abort_unless($owner && (int)$owner>0,403);$group=DB::table('business_branches')->where('owner_id',$owner)->value('group_id');
        return \App\Services\BranchTransactions::run(function()use($request,$next,$actor,$owner,$group){
            if($group)DB::table('business_groups')->where('id',$group)->sharedLock()->first();
            return app(\App\Services\InventoryAccounting::class)->transaction($owner,function()use($request,$next,$actor){
                $fresh=DB::table('users')->where('id',$actor->id)->sharedLock()->first();
                abort_unless($fresh && $fresh->type==='employee' && (int)$fresh->is_active===1 && (int)$fresh->session_epoch===(int)$actor->session_epoch,403,'Personel erişimi kapatıldı.');
                return $next($request);
            });
        });
    }
}
