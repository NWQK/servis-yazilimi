<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\LoggedHistory;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function create()
    {
        if (!file_exists(setup())) {
            header('location:install');
            die;
        }

        \App::setLocale('tr');

        return view('auth.login');
    }

    public function store(LoginRequest $request)
    {
        $google_recaptcha = getSettingsValByName('google_recaptcha');
        if($google_recaptcha == 'on')
        {
            $validation['g-recaptcha-response'] = 'required|captcha';
        } else {
            $validation = [];
        }
        $this->validate($request, $validation);

        $request->authenticate();
        $request->session()->regenerate();
        $request->session()->forget(['owner_login_recorded','2fa_checked']);
        $loginUser = Auth::user();
        if ($loginUser->hasSuspendedSubscription()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->with('error','İşletmenizin aboneliği askıya alındı. Yöneticiyle iletişime geçin.');
        }
        if($loginUser->is_active == 0 || $loginUser->client_archived_at !== null)
        {
            auth()->logout();
            return redirect()->route('login')->with('error', __('Your account is temporarily inactive. Please contact your administrator to reactivate your account.'));
        }
        if(empty($loginUser->email_verified_at)) {
            auth()->logout();
            return redirect()->route('login')->with('error', __('Verification required: Please check your email to verify your account before continuing.'));
        }
        if ($loginUser->type === 'owner' && is_null($loginUser->twofa_secret)) app(\App\Services\OwnerLoginSecurity::class)->record($request);
        if( $loginUser->type=='owner'){

            if($loginUser->subscription_expire_date!=null && date('Y-m-d') > $loginUser->subscription_expire_date){
                assignSubscription(\App\Models\Subscription::where('vehicle_limit', 50)->value('id') ?? 1);
                 return redirect()->intended(RouteServiceProvider::HOME)->with('error', __('Your subscription has ended, and access to premium features is now restricted. To continue using our services without interruption, please renew your plan or upgrade to a higher-tier package.'));
            }
        }
        if ($loginUser->type !== 'owner') userLoggedHistory();

        return redirect()->intended(RouteServiceProvider::HOME);
    }

    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }
}
