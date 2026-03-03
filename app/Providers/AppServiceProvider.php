<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\Payment\GatewayFactory;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register GatewayFactory with payment configuration
        $this->app->singleton(GatewayFactory::class, function ($app) {
            return new GatewayFactory(config('payment.gateways'));
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
