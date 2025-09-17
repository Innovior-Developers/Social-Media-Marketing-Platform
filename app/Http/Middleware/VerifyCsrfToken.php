<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyCsrfToken
{
    /**
     * Handle an incoming request - CSRF COMPLETELY DISABLED
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip all CSRF verification entirely
        return $next($request);
    }
}