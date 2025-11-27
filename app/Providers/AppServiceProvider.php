<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\MpesaTokenService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MpesaTokenService::class, function ($app) {
        return new MpesaTokenService(
            env('MPESA_BASE_URL'),
            env('MPESA_CONSUMER_KEY'),
            env('MPESA_CONSUMER_SECRET')
        );
    });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
