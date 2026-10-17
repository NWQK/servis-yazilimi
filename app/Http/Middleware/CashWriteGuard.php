<?php
namespace App\Http\Middleware;
use Closure;
class CashWriteGuard {
    public function handle($request,Closure $next) {
        if(!$request->user() || !$request->routeIs('expense.store','expense.update','expense.destroy'))return $next($request);
        return app(\App\Services\InventoryAccounting::class)->transaction(parentId(),fn()=>$next($request));
    }
}
