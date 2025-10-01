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
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);
        // Security headers middleware global (pero con excepciones para docs)
        $middleware->append(\App\Http\Middleware\SecurityHeadersMiddleware::class);        // CORS middleware for API

        // MIDDLEWARE ALIASES
        $middleware->alias([
            'cart' => \App\Http\Middleware\CartMiddleware::class,
            'cart.auth' => \App\Http\Middleware\CartAuthMiddleware::class,
            'secure.headers' => \App\Http\Middleware\ValidateSecureHeaders::class,
            'security.rate' => \App\Http\Middleware\SecurityRateLimiter::class,
            'super.admin' => \App\Http\Middleware\SuperAdminMiddleware::class,
            'currency' => \App\Http\Middleware\CurrencyMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
