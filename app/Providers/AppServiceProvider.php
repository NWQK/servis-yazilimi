<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->extend('url', fn($url) => new \App\Routing\BranchUrlGenerator($url));
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
            \Illuminate\Support\Facades\Validator::resolver(fn($translator,$data,$rules,$messages,$attributes)=>
                new \App\Validation\SafeValidator($translator,$data,$rules,$messages,$attributes));
            if($this->app->environment('production'))config(['app.debug'=>false]);
            Schema::defaultStringLength(191);
            config(['app.locale' => 'tr', 'app.fallback_locale' => 'tr', 'app.timezone' => 'Europe/Istanbul']);
            date_default_timezone_set('Europe/Istanbul');
            $this->app->setLocale('tr');
            \Carbon\Carbon::setLocale('tr');
    }
}
