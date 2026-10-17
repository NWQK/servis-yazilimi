<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;

class VerifyEmailController extends Controller
{
    public function resend(\Illuminate\Http\Request $request)
    {
        // Available only after the password was successfully checked by the login form.
        $id = $request->session()->get('pending_verification_user');
        abort_unless($id, 403);
        $user = User::where('type','<>','client')->whereNull('email_verified_at')->findOrFail($id);
        if (!\App\Services\CentralEmail::enabled()) return back()->with('error','E-posta gönderimi şu anda kapalı. Sistem yöneticisiyle iletişime geçin.');
        $token = bin2hex(random_bytes(32));
        $user->email_verification_token = $token;
        $user->save();
        $response = sendEmailVerification($user->email, ['module'=>'email_verification','subject'=>'E-posta adresinizi doğrulayın — sanayirandevu.com',
            'email'=>$user->email,'name'=>$user->name,'url'=>rtrim(config('app.url'),'/').route('email-verification',$token,false)]);
        return back()->with(($response['status']??'error')==='success'?'status':'error',
            ($response['status']??'error')==='success'?'Yeni doğrulama bağlantınız gönderim için alındı. Önceki bağlantılar geçersizdir.':'Doğrulama bağlantısı gönderilemedi. Yeniden deneyin veya destekle iletişime geçin.');
    }
    /**
     * Mark the authenticated user's email address as verified.
     *
     * @param  \Illuminate\Foundation\Auth\EmailVerificationRequest  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function __invoke(EmailVerificationRequest $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(RouteServiceProvider::HOME.'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended(RouteServiceProvider::HOME.'?verified=1');
    }
    public function verifyEmail($token)
    {
        // Legacy SHA-1(email) tokens are predictable and must no longer authenticate ownership.
        abort_unless(is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token), 404);
        // Find the user by token
        $user = User::where('type', '<>', 'client')->where('email_verification_token', $token)->first();

        if (!$user) {
            return response()->json(['message' => __('Invalid or expired token.')], 404);
        }

        // Mark the email as verified
        $user->email_verified_at = now();
        $user->email_verification_token = null; // Clear the token
        $user->save();

        return redirect()->route('login')->with('success', __('Your account has been successfully verified. You can now log in.'));
    }
}
