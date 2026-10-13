<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PlatformMaintenance
{
    public function handle(Request $request, Closure $next)
    {
        // Public information and account recovery stay reachable. No request parameter bypass.
        if ($request->routeIs('marketing.index','page','login','password.*','logout','otp.*')
            || $request->is('login')
            || $request->user()?->type === 'super admin'
            || $request->is('install','install/*')
            || !\App\Services\PlatformMaintenance::enabled()) return $next($request);

        $headers = ['Retry-After'=>'300','Cache-Control'=>'no-store, private'];
        if ($request->expectsJson()) return response()->json(['message'=>'Sistem bakımda. Lütfen kısa bir süre sonra tekrar deneyin.'],503,$headers);
        return response()->view('errors.platform-maintenance',[],503,$headers);
    }
}
