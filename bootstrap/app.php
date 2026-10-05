<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
            // Server-to-server gateway callbacks carry no CSRF token, so
            // without this every PayLesotho callback was refused with a 419
            // before reaching the controller.
            $middleware->alias([
                'organizer' => \App\Http\Middleware\EnsureOrganizerAccess::class,
                'organizer.setup' => \App\Http\Middleware\EnsureOrganizationSetUp::class,
            ]);

            $middleware->validateCsrfTokens(except: [
                'payment/paylesotho/callback/*',
            ]);

            $middleware->replace('api', HandleCors::class, function ($middleware) {
            return $middleware->setOptions([
                'paths' => ['api/*', 'sanctum/csrf-cookie'],
                'allowed_methods' => ['*'],
                'allowed_origins' => ['*'], // ALLOW ALL ORIGINS
                'allowed_headers' => ['*'],
                'exposed_headers' => [],
                'max_age' => 0,
                'supports_credentials' => false,
            ]);
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
