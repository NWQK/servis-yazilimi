<?php
namespace App\Http\Middleware;
use Closure;
class EmployeeSession {
    public function handle($request,Closure $next){
        $user=$request->user();if(!$user || !\Schema::hasColumn('users','session_epoch'))return $next($request);
        $state=\DB::table('users')->where('id',$user->id)->first(['is_active','session_epoch']);
        $key=$user->type==='employee'?'employee_epoch':'account_epoch';
        $epoch=(int)($state->session_epoch??0);$known=$request->session()->get($key);
        if(!$state || (int)$state->is_active!==1 || ($known===null && $epoch>0) || ($known!==null && (int)$known!==$epoch)){
            auth()->logout();$request->session()->invalidate();$request->session()->regenerateToken();
            return $request->expectsJson()?response()->json(['message'=>'Hesap erişiminiz değişti. Yeniden giriş yapın.'],401):redirect()->route('login')->with('error','Hesap erişiminiz kapatıldı veya değişti. Yeniden giriş yapın.');
        }
        $request->session()->put($key,$epoch);return $next($request);
    }
}
