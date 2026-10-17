<?php
namespace App\Http\Middleware;
use Closure;
class FinancialCatalogueFence {
    public function handle($request,Closure $next){
        if(!$request->user() || $request->isMethodSafe() || !$request->routeIs('tax.store','tax.update','tax.destroy','service-type.store','service-type.update','service-type.destroy','service.store','service.update','service.destroy','service.change.status','service.type.destroy','quotation.store','quotation.update','quotation.destroy','quotation.convertservice'))return $next($request);
        return app(\App\Services\InventoryAccounting::class)->transaction(parentId(),fn()=>$next($request));
    }
}
