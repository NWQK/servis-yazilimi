<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FAQRCode\Google2FA;

class OTPController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        // Limit sensitive verification attempts per authenticated account.
        $this->middleware('throttle:6,1')->only(['check','disable']);
    }

    public function show()
    {
        return view('auth.otp');
    }

    public function check(Request $request)
    {
        $request->validate(['otp'=>'required|string|max:32']);
        if (app(\App\Services\TwoFactorAuthentication::class)->consume(Auth::user(), $request->otp)) {
            session(['2fa_checked'=>true,'2fa_verified_key'=>hash('sha256',Auth::user()->twofa_secret)]);
            app(\App\Services\OwnerLoginSecurity::class)->record($request);
            return redirect("/");
        }
        return redirect()->back()->with('error', __('Incorrect Code. Please try again...')); 
    }

    public function disable(Request $request)
    {
        $user=\Auth::user();
        abort_unless($user->can('manage 2FA settings'),403);
        if ($user->twofa_required) {
            throw ValidationException::withMessages(['otp'=>'Doğrulama süper admin tarafından zorunlu tutuluyor. Kapatmak için yöneticinizle iletişime geçin.']);
        }
        $request->session()->flash('tab','2FA');
        $request->validate(['password'=>'required|string','otp'=>'required|string|max:32']);
        if (!\Illuminate\Support\Facades\Hash::check($request->password,$user->password)
            || !app(\App\Services\TwoFactorAuthentication::class)->consume($user,$request->otp)) {
            throw ValidationException::withMessages(['otp'=>'Şifre veya doğrulama kodu hatalı.']);
        }
        $user->twofa_secret=null;
        $user->twofa_last_used_at=null;
        $user->twofa_recovery_codes=null;
        $user->save();
        $request->session()->forget(['2fa_checked','2fa_verified_key','2fa_secret','2fa_setup_user']);
        return redirect()->route('setting.index')->with('tab','2FA')->with('success','İki aşamalı doğrulama kapatıldı.');
    }
}
