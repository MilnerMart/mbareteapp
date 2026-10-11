<?php

namespace App\Http\Middleware;

use App\Support\AuthPermits;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCanConfigureApp
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!AuthPermits::canConfigureApp()) {
            return redirect()->route('muscle.index');
        }

        return $next($request);
    }
}
