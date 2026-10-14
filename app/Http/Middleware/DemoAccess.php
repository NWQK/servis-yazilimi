<?php
namespace App\Http\Middleware;
use Closure;
use App\Models\User;
use App\Services\DemoLifecycle;

class DemoAccess
{
    public function handle($request, Closure $next)
    {
        $user=$request->user();
        if (!$user || in_array($user->type,['super admin','client'],true)) return $next($request);
        $owner=$user->type==='owner' ? $user : User::where('type','owner')->find($user->parent_id);
        if (!$owner || !app(DemoLifecycle::class)->expired($owner)) return $next($request);
        $name=(string)$request->route()?->getName();
        if (str_starts_with($name,'subscriptions.') || str_starts_with($name,'subscription.')
            || str_starts_with($name,'capacity.') || str_starts_with($name,'support.')
            || in_array($name,['logout','otp.show','otp.verify'],true) || $request->is('login/otp*','settings','settings/2fa')
            || !$request->route()?->gatherMiddleware()
            || (!in_array('auth',$request->route()->gatherMiddleware(),true) && !in_array($name,['home','dashboard'],true) && !$request->is('/'))) return $next($request);
        $message='14 günlük demo süreniz doldu. Devam etmek için bir paket seçin. Son girişinizden itibaren 30 gün giriş yapılmayan ücretsiz demo hesapları ve işletme kayıtları silinir.';
        if ($request->expectsJson()) return response()->json(['message'=>$message],403);
        return redirect()->route('subscriptions.index')->with('error',$message);
    }
}
