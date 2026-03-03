<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Exclude gateway callback endpoints from CSRF verification
        // This is required because payment gateways send POST requests without CSRF tokens
        // Validates: Requirements 6.6
        $middleware->validateCsrfTokens(except: [
            'api/cinetpay/callback',
            'api/tranzak/callback',
            'api/cinetpay/ipn', // Legacy endpoint for backward compatibility
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
