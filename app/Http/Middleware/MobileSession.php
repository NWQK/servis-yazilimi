<?php
namespace App\Http\Middleware;
use Closure;
use App\Services\{MobileAccess,InventoryAccounting};
use App\Services\Recovery\RecoveryWriteLock;
class MobileSession {
    public function handle($request,Closure $next,$stage='full') {
        abort_if($request->has('_branch') || $request->hasHeader('X-Branch-Context'),403,'Mobil şube değişimi henüz desteklenmiyor.');
        $access=app(MobileAccess::class); $owner=$access->check($request,$stage==='full');
        $request->attributes->set('mobile_owner',$owner->id);
        if($request->isMethodSafe())return $next($request);
        return app(RecoveryWriteLock::class)->shared($owner->id,fn()=>app(InventoryAccounting::class)->transaction($owner->id,function()use($access,$request,$stage,$next){
            $access->check($request,$stage==='full');
            return $next($request);
        }));
    }
}
