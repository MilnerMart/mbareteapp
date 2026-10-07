<?php

use App\Http\Middleware\EnsureCanManageGyms;
use App\Http\Middleware\EnsureFrontendAuthenticated;
use App\Http\Middleware\EnsureIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Detras del proxy HTTPS del hosting: sin esto Laravel arma urls http:// (css y forms bloqueados).
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'frontend.auth' => EnsureFrontendAuthenticated::class,
            'frontend.gym' => EnsureCanManageGyms::class,
            'frontend.admin' => EnsureIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
