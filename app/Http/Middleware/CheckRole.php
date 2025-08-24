<?php
// app/Http/Middleware/CheckRole.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Check if user has any of the required roles
        if (!$user->hasAnyRole($roles)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient permissions. Required roles: ' . implode(', ', $roles),
                'user_roles' => $user->getRoleNames()
            ], 403);
        }

        return $next($request);
    }
}