<?php

use App\PublicException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Detras del proxy HTTPS del hosting: sin esto Laravel arma urls http://.
        $middleware->trustProxies(at: '*');
        $middleware->redirectGuestsTo(function (Request $request) {
            return $request->is('api/*') ? null : route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->shouldRenderJsonWhen(function (Request $request,Throwable $exception) {
            return $request->is('api/*')|| $request->expectsJson();
        });

        $exceptions->render(function (PublicException $exception,Request $request): ?JsonResponse {
            if (!$request->is('api/*')) {
                return null;
            }

            return response()->json([
                'success' => false,
                'error' => $exception->getInfoArray(),
            ], $exception->getHttpCode());
        });
    })->create();