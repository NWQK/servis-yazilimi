<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Support\Facades\{DB,Schema};
class BusinessRecoveryGate
{
    public function handle($request,Closure $next)
    {
        if(!Schema::hasTable('business_recovery_locks')||$request->routeIs('recoveries.*','logout','login','password.*','marketing.index','page','directory.*','vehicle-history.*'))return $next($request);
        $user=$request->user();$owner=null;
        $accountOwner=$user&&$user->type!=='super admin'?($user->type==='owner'?$user->id:$user->parent_id):null;
        if ($context = \App\Services\BranchContext::current()) $accountOwner = $context['owner']->id;
        // Public links belong to their target business, even when another shop is signed in.
        if($request->route('publicId'))$owner=DB::table('appointment_profiles')->where('public_id',$request->route('publicId'))->value('owner_id');
        if(!$owner&&$request->route('token')){
            $token=$request->route('token');
            $owner=DB::table('vehicle_qr_codes')->where('token',$token)->value('parent_id')
                ?:DB::table('business_reviews')->where('token',$token)->value('owner_id');
        }
        $owner=$owner?:$accountOwner;
        if(!$owner&&$user?->type==='super admin'&&$request->routeIs('users.update','users.destroy')){
            $id=$request->route('user');
            if(is_scalar($id)&&ctype_digit((string)$id))$owner=DB::table('users')->where('type','owner')->where('id',$id)->value('id');
        }
        if(!$owner)return $next($request);
        $check=function()use($request,$next,$owner,$accountOwner){
            if(DB::table('business_recovery_locks')->where('owner_id',$owner)->exists()){
                $message='Bu işletme için veri kurtarma işlemi yapılıyor. Kayıtlar korunuyor; lütfen işlem tamamlandıktan sonra yeniden deneyin.';
                return $request->expectsJson()?response()->json(['message'=>$message],503,['Retry-After'=>'60'])
                    :response()->view('errors.business-recovery',compact('message'),503)->header('Retry-After','60')->header('Cache-Control','no-store');
            }
            if($request->user()&&!in_array($request->user()->type,['client','super admin','branch_manager'],true) && !\App\Services\BranchContext::current()){
                $version=(int)DB::table('users')->where('id',$accountOwner)->value('recovery_version');$key='business_recovery_epochs.'.$accountOwner;
                if(($request->session()->has($key)&&(int)$request->session()->get($key)!==$version)
                    ||(!$request->session()->has($key)&&$version>0)){
                    auth()->logout();$request->session()->invalidate();$request->session()->regenerateToken();
                    return redirect()->route('login')->with('error','İşletme verileri kurtarıldı. Eski açık formları kapatıp yeniden giriş yapın.');
                }
                $request->session()->put($key,$version);
            }
            return $next($request);
        };
        if($request->isMethod('get')||$request->isMethod('head'))return $check();
        // Return immediately for a paused shop, rather than occupying a PHP worker waiting for a restore.
        if(DB::table('business_recovery_locks')->where('owner_id',$owner)->exists())return $check();
        return app(\App\Services\Recovery\RecoveryWriteLock::class)->shared((int)$owner,$check);
    }
}
