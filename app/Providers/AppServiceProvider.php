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
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
            Schema::defaultStringLength(191);
            config(['app.locale' => 'tr', 'app.fallback_locale' => 'tr', 'app.timezone' => 'Europe/Istanbul']);
            date_default_timezone_set('Europe/Istanbul');
            $this->app->setLocale('tr');
            \Carbon\Carbon::setLocale('tr');
    }
}
