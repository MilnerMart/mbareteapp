<?php

namespace App\Http\Middleware;

use App\Support\AuthPermits;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBelongsToGym
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!AuthPermits::canBelongToGym()) {
            return redirect()->route('muscle.index');
        }

        return $next($request);
    }
}
