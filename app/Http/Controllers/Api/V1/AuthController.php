<?php
namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\{MobileAccess,TwoFactorAuthentication};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash,RateLimiter};
class AuthController extends Controller {
    public function login(Request $request) {
        $data=$request->validate(['email'=>'required|string|email|max:255','password'=>'required|string|max:1024','device_name'=>'required|string|max:80']);
        $key='mobile-login:'.hash('sha256',strtolower($data['email']).'|'.$request->ip());
        abort_if(RateLimiter::tooManyAttempts($key,5),429,'Çok fazla deneme. Bir dakika bekleyin.');
        RateLimiter::hit($key,60);
        $user=User::where('email',$data['email'])->first();
        abort_unless($user && Hash::check($data['password'],$user->password),422,'E-posta veya şifre hatalı.');
        return DB::transaction(function()use($user,$data,$key) {
            $ownerId=$user->type==='owner'?$user->id:$user->parent_id;
            User::whereKey($ownerId)->lockForUpdate()->first();
            $user=User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless(Hash::check($data['password'],$user->password),401);
            $owner=app(MobileAccess::class)->owner($user);
            abort_if($user->twofa_required && !$user->twofa_secret,403,'İki aşamalı doğrulamayı önce web hesabınızdan kurun.');
            $pending=(bool)$user->twofa_secret;
            $expires=$pending?now()->addMinutes(5):now()->addDays(30);
            $token=$user->createToken($data['device_name'],[$pending?'mobile:2fa':'mobile:v1',app(MobileAccess::class)->snapshot($user,$owner)],$expires);
            if(!$pending)app(\App\Services\DemoLifecycle::class)->recordLogin($user);
            RateLimiter::clear($key);
            return response()->json(['token'=>$token->plainTextToken,'requires_2fa'=>$pending,'expires_at'=>$expires->toIso8601String()]);
        });
    }
    public function verify(Request $request) {
        $data=$request->validate(['code'=>'required|string|max:40']);
        $token=$request->user()->currentAccessToken();
        $key='mobile-otp:'.$request->user()->id;
        abort_if(RateLimiter::tooManyAttempts($key,5),429,'Çok fazla kod denemesi. Bir dakika bekleyin.');
        RateLimiter::hit($key,60);
        abort_unless(app(TwoFactorAuthentication::class)->consume($request->user(),$data['code']),422,'Doğrulama kodu geçersiz veya kullanılmış.');
        $user=$request->user()->fresh();$owner=app(MobileAccess::class)->owner($user);
        $new=$user->createToken($token->name,['mobile:v1',app(MobileAccess::class)->snapshot($user,$owner)],now()->addDays(30));
        app(\App\Services\DemoLifecycle::class)->recordLogin($user);
        $token->delete(); RateLimiter::clear($key);
        return response()->json(['token'=>$new->plainTextToken,'requires_2fa'=>false]);
    }
    public function logout(Request $request) {
        $token=$request->user()->currentAccessToken();
        abort_unless($token instanceof \Laravel\Sanctum\PersonalAccessToken,401);
        $token->delete(); return response()->noContent();
    }
    public function session(Request $request) {
        $user=$request->user(); $owner=User::findOrFail($request->attributes->get('mobile_owner'));
        return response()->json(['data'=>['user'=>['id'=>$user->id,'name'=>$user->name,'type'=>$user->type],
            'business'=>['id'=>$owner->id,'name'=>$owner->name],
            'permissions'=>collect(['manage client','show client','create client','manage vehicle','show vehicle','create vehicle','manage service','show service'])->filter(fn($p)=>$user->can($p))->values(),
            'capabilities'=>['branch_switch'=>false,'financial_writes'=>false]]]);
    }
    public function devices(Request $request) {
        return response()->json(['data'=>$request->user()->tokens()->whereJsonContains('abilities','mobile:v1')->get(['id','name','last_used_at','expires_at'])]);
    }
    public function revoke(Request $request,int $id) {
        $request->user()->tokens()->findOrFail($id)->delete();return response()->noContent();
    }
}
