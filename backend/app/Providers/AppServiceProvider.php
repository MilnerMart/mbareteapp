<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        // todas las peticiones llegan desde el contenedor del frontend: los limites van por usuario, no por ip
        $tooManyAttempts = fn() => response()->json([
            'success' => false,
            'error' => ['code' => 'too_many_attempts', 'message' => 'Hiciste demasiados intentos. Espera un minuto y vuelve a probar.'],
        ], 429);
        RateLimiter::for('email-verification-send', fn(Request $request) => Limit::perMinute(3)->by((string)$request->user()?->id)->response($tooManyAttempts));
        RateLimiter::for('email-verification-verify', fn(Request $request) => Limit::perMinute(10)->by((string)$request->input('user'))->response($tooManyAttempts));
        RateLimiter::for('password-reset-send', fn(Request $request) => Limit::perMinutes(10, 3)->by(mb_strtolower((string)$request->input('email')))->response($tooManyAttempts));
        RateLimiter::for('password-reset', fn(Request $request) => Limit::perMinute(10)->by(mb_strtolower((string)$request->input('email')))->response($tooManyAttempts));
    }
}
