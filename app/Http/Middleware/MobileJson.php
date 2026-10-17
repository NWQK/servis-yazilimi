<?php
namespace App\Http\Middleware;
use Closure;
class MobileJson {
    public function handle($request,Closure $next) {
        $request->headers->set('Accept','application/json');
        $response=$next($request); $response->headers->set('Cache-Control','private, no-store'); return $response;
    }
}
