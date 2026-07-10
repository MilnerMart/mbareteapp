<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFrontendAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->session()->has('auth_user') || !$request->session()->has('auth_token')) {
            return redirect()->route('user.login');
        }

        return $next($request);
    }
}
