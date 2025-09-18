<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class MongoTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token required',
                'debug' => 'No bearer token in request'
            ], 401);
        }

        // Parse our custom token format
        if (!str_starts_with($token, 'mongodb_token_')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid token format',
                'debug' => 'Token does not start with mongodb_token_'
            ], 401);
        }

        $hashedToken = hash('sha256', $token);

        // Find user with this token
        $user = User::where('api_tokens.token', $hashedToken)->first();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid token',
                'debug' => [
                    'hashed_token_preview' => substr($hashedToken, 0, 20) . '...',
                    'token_query_result' => 'No user found with this token'
                ]
            ], 401);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'status' => 'error',
                'message' => 'Account suspended'
            ], 403);
        }

        // Update last used
        $tokens = $user->api_tokens ?? [];
        foreach ($tokens as &$tokenData) {
            if ($tokenData['token'] === $hashedToken) {
                $tokenData['last_used_at'] = now();
                break;
            }
        }

        $user->update(['api_tokens' => $tokens]);

        // Set authenticated user
        Auth::setUser($user);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        return $next($request);
    }
}
