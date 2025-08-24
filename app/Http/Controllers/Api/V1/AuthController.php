<?php
// app/Http/Controllers/Api/V1/AuthController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Register a new user
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8|confirmed',
                'timezone' => 'string|timezone',
                'referral_code' => 'string|max:50'
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'timezone' => $validated['timezone'] ?? 'UTC',
                'status' => 'active',
                'roles' => ['user'],
                'email_verified_at' => now(), // Auto-verify for demo
            ]);

            // Create API token
            $token = $user->createToken('api-token', ['*'])->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'User registered successfully',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'subscription' => $user->getSubscriptionLimits(),
                    'permissions' => $user->getAllPermissions()
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Login user
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
                'remember' => 'boolean',
                'device_name' => 'string|max:255'
            ]);

            $user = User::where('email', $validated['email'])->first();

            if (!$user || !Hash::check($validated['password'], $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid credentials'
                ], 401);
            }

            if ($user->status !== 'active') {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Account is suspended. Please contact support.'
                ], 403);
            }

            // Update last login
            $user->updateLastLogin();

            // Create token
            $deviceName = $validated['device_name'] ?? 'Unknown Device';
            $abilities = ['*']; // Full permissions for now
            
            $token = $user->createToken($deviceName, $abilities)->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                    'subscription' => [
                        'plan' => $user->subscription['plan'] ?? 'free',
                        'limits' => $user->getSubscriptionLimits(),
                        'remaining_posts' => $user->getRemainingPosts()
                    ],
                    'permissions' => $user->getAllPermissions(),
                    'connected_accounts' => $user->connectedSocialAccounts()->count()
                ]
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Login failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Delete current token
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout from all devices
     */
    public function logoutAll(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            // Delete all tokens for this user
            $user->tokens()->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Logged out from all devices successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get authenticated user
     */
    public function user(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'user' => $user,
                    'subscription' => [
                        'plan' => $user->subscription['plan'] ?? 'free',
                        'limits' => $user->getSubscriptionLimits(),
                        'remaining_posts' => $user->getRemainingPosts()
                    ],
                    'permissions' => $user->getAllPermissions(),
                    'roles' => $user->getRoleNames(),
                    'stats' => [
                        'total_posts' => $user->posts()->count(),
                        'connected_accounts' => $user->connectedSocialAccounts()->count(),
                        'last_login' => $user->last_login_at
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve user data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Refresh token
     */
    public function refresh(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $currentToken = $request->user()->currentAccessToken();
            
            // Delete current token
            $currentToken->delete();
            
            // Create new token
            $newToken = $user->createToken('refreshed-token', ['*'])->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $newToken,
                    'user' => $user
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token refresh failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's active sessions/tokens
     */
    public function sessions(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            
            $tokens = $user->tokens()->get()->map(function($token) {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => $token->abilities,
                    'created_at' => $token->created_at,
                    'last_used_at' => $token->last_used_at,
                    'is_current' => $token->id === request()->user()->currentAccessToken()->id
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'active_sessions' => $tokens,
                    'total_sessions' => $tokens->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve sessions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Revoke specific token/session
     */
    public function revokeSession(Request $request, string $tokenId): JsonResponse
    {
        try {
            $user = $request->user();
            
            $token = $user->tokens()->where('id', $tokenId)->first();
            
            if (!$token) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Session not found'
                ], 404);
            }

            // Prevent revoking current session
            if ($token->id === $request->user()->currentAccessToken()->id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot revoke current session. Use logout instead.'
                ], 400);
            }

            $token->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Session revoked successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to revoke session',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}