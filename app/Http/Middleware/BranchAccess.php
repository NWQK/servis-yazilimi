<?php
namespace App\Http\Middleware;

use App\Services\{BranchContext, Recovery\RecoveryWriteLock};
use Closure;
use Illuminate\Support\Facades\DB;

class BranchAccess
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();
        if (!$user) return $next($request);
        $hasToken=$request->query->has('_branch') || $request->request->has('_branch') || $request->hasHeader('X-Branch-Context');
        if ($user->type !== 'branch_manager' && !$hasToken) return $next($request);
        // A supplied context is never silently ignored by an unapproved actor.
        abort_unless(in_array($user->type,['branch_manager','employee'],true),403);
        abort_unless($user->is_active, 403);
        $name = (string)$request->route()?->getName();
        if(\Illuminate\Support\Str::is(['branch-referrals.*','employee-departures.*'],$name))return $next($request);
        if ($request->isMethod('get') && $request->path() === '/') return redirect()->route('branches.portal');
        // Public vehicle/booking URLs still use their own private tokens, never a branch context.
        if (\Illuminate\Support\Str::is(['booking.*','directory.*','vehicle-portal.*','vehicle-history.*',
            'reviews.show','reviews.store','marketing.*','page','login','password.*','verification.*','logout','otp.*','2fa.disable','branches.*','branch-transfers.*'], $name)) {
            return $next($request);
        }
        $tokens = array_filter([$request->query('_branch'), $request->request->get('_branch'), $request->header('X-Branch-Context')], fn($v)=>$v !== null && $v !== '');
        foreach ($tokens as $token) abort_unless(is_string($token) && strlen($token) <= 4000, 409);
        abort_unless(count(array_unique($tokens)) <= 1, 409, 'Formun şube bilgileri uyuşmuyor.');
        $token = reset($tokens);
        if (!$token && in_array($name, ['setting.index','setting.account','setting.password','setting.twofa.enable'], true)) return $next($request);
        if (!$token) {
            if ($request->isMethod('get') && in_array($name, ['home','dashboard'], true)) return redirect()->route('branches.portal');
            abort(409, 'Önce merkez ekranından bir şube seçin.');
        }
        abort_unless(BranchContext::businessRoute($name), 403, 'Bu işlem şube erişimiyle kullanılamaz.');
        $data = BranchContext::decode($token, $user);
        // Read before taking any file lock; invalid actors cannot lock another shop.
        BranchContext::validate($data, $user);
        try { return app(RecoveryWriteLock::class)->shared($data['owner'], function () use ($request,$next,$user,$data,$token) {
            $transaction=function () use ($request,$next,$user,$data,$token) {
                $context = BranchContext::validate($data, $user, true);
                if ($context['grant']) abort_unless(\App\Services\BranchPermissions::request($context['grant'],$request),403,'Bu şubede bu işlem için yetkiniz yok.');
                $request->attributes->set('branch_context', $context + ['token'=>$token]);
                $response = $next($request);
                if ($response->getStatusCode() >= 400) throw new \Illuminate\Http\Exceptions\HttpResponseException($response);
                // HTML and JSON for a branch must never be shared by a cache.
                $response->headers->set('Cache-Control', 'private, no-store');
                $response->headers->set('Referrer-Policy', 'same-origin');
                return $response;
            };
            // Some existing GET endpoints also replenish QR pools or mark messages read.
            // All of them must see data committed while they waited for the business lock.
            return \App\Services\BranchTransactions::run($transaction);
        }); } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) { return $e->getResponse(); }
        finally { $request->attributes->remove('branch_context'); }
    }
}
