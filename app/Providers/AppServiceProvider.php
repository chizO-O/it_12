<?php

namespace App\Providers;

use App\Auth\CaseInsensitiveUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('grove-users', fn ($app, array $config) => new CaseInsensitiveUserProvider($app['hash'], $config['model']));
    }
}
