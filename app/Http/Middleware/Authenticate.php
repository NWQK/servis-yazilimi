<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function handle($request, \Closure $next, ...$guards)
    {
        $this->authenticate($request, $guards);
        if (!$request->routeIs('logout','impersonate.leave') && $request->user()->client_archived_at !== null) { abort(403, 'Müşteri kaydı silinenlere taşınmış.'); }
        if (!$request->routeIs('logout','impersonate.leave') && $request->user()->hasSuspendedSubscription()) {
            if ($request->expectsJson()) { return response()->json(['message'=>'İşletmenizin aboneliği askıya alındı.'],403); }
            return response()->view('subscription.suspended',[],403);
        }
        return $next($request);
    }

    protected function redirectTo($request)
    {
        if (! $request->expectsJson()) {
            return route('login');
        }
    }
}
