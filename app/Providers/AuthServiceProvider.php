<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        \Illuminate\Support\Facades\Auth::provider('accounts', function ($app, array $config) {
            return new \App\Auth\AccountUserProvider($app['hash'], $config['model']);
        });

        // The framework's verification resend must use the same central brand and SMTP.
        \Illuminate\Auth\Notifications\VerifyEmail::toMailUsing(function ($user, $url) {
            $smtp = app(\App\Services\TenantMailSettings::class)->apply($user->id);
            $subject = 'E-posta adresinizi doğrulayın — sanayirandevu.com';
            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->mailer('smtp')->from($smtp['FROM_EMAIL'], \App\Services\EmailBranding::NAME)
                ->subject($subject)->view('email.email_verification', [
                    'subject' => $subject, 'data' => ['name' => $user->name, 'url' => $url],
                ]);
        });
    }
}
