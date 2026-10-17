<?php
namespace App\Services;
use App\Models\User;
use Illuminate\Support\Facades\DB;
class MobileAccess
{
    public function owner(User $user): User {
        abort_unless(in_array($user->type,['owner','employee'],true) && (int)$user->is_active===1 && $user->email_verified_at && !$user->client_archived_at,401,'Hesap erişiminiz kapatıldı.');
        $owner=$user->type==='owner'?$user:User::where('type','owner')->find($user->parent_id);
        abort_unless($owner && (int)$owner->is_active===1,401,'İşletme erişimi kapalı.');
        return $owner;
    }
    public function snapshot(User $user,User $owner): string {
        return 'state:'.hash('sha256',json_encode([$user->type,$user->parent_id,(int)$user->session_epoch,
            (int)$owner->session_epoch,(int)$owner->recovery_version,$user->twofa_secret,(bool)$user->twofa_required,$user->password]));
    }
    public function check($request,bool $full=true): User {
        $user=$request->user()->fresh(); $token=$request->user()->currentAccessToken();
        abort_unless($token instanceof \Laravel\Sanctum\PersonalAccessToken && $token->fresh(),401);
        abort_unless($user,401);
        $owner=$this->owner($user);
        abort_unless(in_array($this->snapshot($user,$owner),$token->abilities,true),401,'Oturum değişti. Yeniden giriş yapın.');
        abort_unless(in_array($full?'mobile:v1':'mobile:2fa',$token->abilities,true),403,'İki aşamalı doğrulamayı tamamlayın.');
        abort_if($user->twofa_required && !$user->twofa_secret,403,'İki aşamalı doğrulamayı web hesabınızdan kurun.');
        abort_if($owner->subscription_suspended_at || app(DemoLifecycle::class)->expired($owner)
            || ($owner->subscription_expire_date && $owner->subscription_expire_date < now()->toDateString()),403,'İşletmenizin aboneliğini yenileyin.');
        abort_if(DB::table('business_recovery_locks')->where('owner_id',$owner->id)->exists(),503,'İşletme verileri kurtarılıyor. Daha sonra deneyin.');
        $user->withAccessToken($token); $request->setUserResolver(fn()=>$user); auth()->setUser($user);
        return $owner;
    }
}

