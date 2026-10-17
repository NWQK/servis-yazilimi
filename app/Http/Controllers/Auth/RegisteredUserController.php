<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Spatie\Permission\Models\Role;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        \App::setLocale('tr');
        $registerPage = getSettingsValByName('register_page');

        if ($registerPage == 'on') {
            $menu = Page::where('slug', 'terms_conditions')->first();
            return view('auth.register', compact('menu'));
        } else {
            return redirect()->route('login');
        }
    }

    /**
     * Handle an incoming registration request.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request)
    {
        abort_unless(getSettingsValByName('register_page') === 'on', 403);
        $google_recaptcha = getSettingsValByName('google_recaptcha');
        if ($google_recaptcha == 'on') {
            $validation['g-recaptcha-response'] = 'required|captcha';
        } else {
            $validation = [];
        }
        $this->validate($request, $validation);

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);
        $userData = [
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'type' => 'owner',
            'lang' => 'tr',
            'subscription' => \App\Models\Subscription::where('vehicle_limit', 50)->value('id') ?? 1,
            'parent_id' => 1,
        ];
        $owner_email_verification = getSettingsValByName('owner_email_verification');
        $owner = \DB::transaction(function () use ($userData) {
            $owner = User::create($userData);
            $owner->assignRole(Role::findByName('owner'));
            defaultClientCreate($owner->id);
            defaultEmployeeCreate($owner->id);
            app(\App\Services\DefaultInventoryCatalog::class)->seed($owner->id);
            defultTemplate($owner->id);
            return $owner;
        });
        Auth::login($owner);
        $request->session()->regenerate();
        if ($owner_email_verification == 'on') {
            $token = bin2hex(random_bytes(32));
            $url = rtrim(config('app.url'),'/').route('email-verification', $token, false);

            $owner->email_verification_token = $token;
            $owner->save();

            $data = [
                'module' => 'email_verification',
                'subject' => 'E-posta adresinizi doğrulayın — sanayirandevu.com',
                'email' => $owner->email,
                'name' => $owner->name,
                'url' => $url,
            ];
            $to = $owner->email;
            $response = sendEmailVerification($to, $data);
            $request->session()->put('pending_verification_user',$owner->id);
            auth()->logout();
            if ($response['status'] == 'success') {
                auth()->logout();
                return redirect()->route('login')->with('error', __('We have sent an account verification email to your registered email inbox. Please check your email and follow the instructions to verify your account.'));
            } else {
                // Keep a pending account recoverable; deleting only users leaves orphaned setup rows.
                return redirect()->route('login')->with('error', 'Doğrulama e-postası gönderilemedi. Giriş ekranından yeniden gönderebilirsiniz.');
            }
        } else {
            $module = 'owner_create';
            $setting = settings();
            if (!empty($owner)) {
                $data['subject'] = 'New User Created';
                $data['module'] = $module;
                $data['password'] = $request->password;
                $data['name'] = $request->name;
                $data['email'] = $request->email;
                $data['url'] = env('APP_URL');
                $data['logo'] = $setting['company_logo'];
                $to = $owner->email;
                commonEmailSend($to, $data);
            }
            $owner->email_verified_at = now();
            $owner->email_verification_token = null;
            $owner->save();
            return redirect(RouteServiceProvider::HOME);
        }

        // return redirect(RouteServiceProvider::HOME);
    }
}
