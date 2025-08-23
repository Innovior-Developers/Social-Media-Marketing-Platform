<?php
// app/Http/Middleware/CheckSubscriptionLimits.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscriptionLimits
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $limitType = 'posts'): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated'
            ], 401);
        }

        switch ($limitType) {
            case 'posts':
                if ($user->hasReachedPostingLimit()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Monthly posting limit reached',
                        'limits' => $user->getSubscriptionLimits(),
                        'remaining_posts' => $user->getRemainingPosts()
                    ], 429);
                }
                break;

            case 'social_accounts':
                if (!$user->canAddSocialAccount()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Social account limit reached',
                        'limits' => $user->getSubscriptionLimits(),
                        'connected_accounts' => $user->connectedSocialAccounts()->count()
                    ], 429);
                }
                break;
        }

        return $next($request);
    }
}