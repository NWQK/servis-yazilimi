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

        Gate::before(function ($user, $ability) {
            if ($user->type === 'employee' && ($context=\App\Services\BranchContext::current())) {
                return $context['grant'] && \App\Services\BranchPermissions::ability($context['grant'],$ability);
            }
            if ($user->type !== 'branch_manager') return null;
            if (in_array($ability, ['manage account settings','manage password settings','manage 2FA settings'], true)) return true;
            $context = \App\Services\BranchContext::current();
            if (!$context || !\App\Services\BranchContext::businessRoute((string)request()->route()?->getName())) return false;
            // Use the selected shop's permissions; never give platform permissions to a central account.
            if (preg_match('/user|role|pricing|payment settings|email|notification|sms|openai|n8n|seo|recaptcha|coupon|home page|auth page|footer|FAQ|Page/i', $ability)) return false;
            return $context['owner']->can($ability);
        });

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
