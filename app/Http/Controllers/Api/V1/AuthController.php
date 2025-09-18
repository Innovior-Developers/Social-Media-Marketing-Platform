<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use App\Models\Organization;
use App\Models\Brand;
use App\Models\Membership;
use Illuminate\Support\Str;

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
                'organization_name' => 'required|string|max:255',
                'timezone' => 'string|timezone',
            ]);

            // Create user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'timezone' => $validated['timezone'] ?? 'UTC',
                'status' => 'active',
                'roles' => ['user'],
                'email_verified_at' => now(),
            ]);

            // Create organization
            $organization = Organization::create([
                'name' => $validated['organization_name'],
                'slug' => Str::slug($validated['organization_name']),
                'owner_id' => $user->_id,
                'status' => 'active',
                'subscription_status' => 'active',
                'subscription_plan' => 'free',
                'settings' => [
                    'default_timezone' => $validated['timezone'] ?? 'UTC',
                    'features' => ['analytics', 'scheduling', 'multi_brand', 'team_collaboration']
                ]
            ]);

            // Create default brand
            $brand = Brand::create([
                'organization_id' => $organization->_id,
                'name' => $validated['organization_name'] . ' Main Brand',
                'slug' => Str::slug($validated['organization_name'] . '-main'),
                'active' => true,
                'settings' => [
                    'timezone' => $validated['timezone'] ?? 'UTC'
                ]
            ]);

            // Create membership
            $membership = Membership::create([
                'user_id' => $user->_id,
                'organization_id' => $organization->_id,
                'brand_id' => $brand->_id,
                'role' => 'OWNER',
                'status' => 'active',
                'permissions' => [
                    'manage_brand',
                    'manage_team',
                    'create_posts',
                    'edit_posts',
                    'delete_posts',
                    'schedule_posts',
                    'view_analytics',
                    'manage_channels'
                ],
                'joined_at' => now()
            ]);

            // 🔥 TEMPORARY FIX: Create simple token instead of Sanctum
            $tokenString = 'mongodb_token_' . $user->_id . '_' . time() . '_' . uniqid();

            // Store token in user document (MongoDB way)
            $user->update([
                'api_tokens' => [
                    [
                        'token' => hash('sha256', $tokenString),
                        'name' => 'api-token',
                        'abilities' => ['*'],
                        'created_at' => now(),
                        'last_used_at' => null,
                    ]
                ]
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'User registered successfully with organization and brand',
                'data' => [
                    'user' => $user,
                    'organization' => $organization,
                    'brand' => $brand,
                    'membership' => $membership,
                    'token' => $tokenString, // Return plain token
                    'subscription' => $user->getSubscriptionLimits(),
                    'permissions' => $user->getAllPermissions()
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed: ' . $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
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

        // Get organizations and memberships
        $organizations = Organization::where('owner_id', $user->_id)
            ->orWhereHas('memberships', function ($query) use ($user) {
                $query->where('user_id', $user->_id);
            })
            ->with(['brands'])
            ->get();

        $memberships = Membership::where('user_id', $user->_id)
            ->with(['organization', 'brand'])
            ->get();

        // Create custom token (same as registration)
        $deviceName = $validated['device_name'] ?? 'Unknown Device';
        $tokenString = 'mongodb_token_' . $user->_id . '_' . time() . '_' . uniqid();
        
        // Get existing tokens or create empty array
        $existingTokens = $user->api_tokens ?? [];
        
        // Add new token
        $newToken = [
            'token' => hash('sha256', $tokenString),
            'name' => $deviceName,
            'abilities' => ['*'],
            'created_at' => now(),
            'last_used_at' => now(),
        ];
        
        $existingTokens[] = $newToken;
        
        // Update user with new token
        $user->update([
            'api_tokens' => $existingTokens
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Login successful',
            'data' => [
                'user' => $user,
                'organizations' => $organizations,
                'memberships' => $memberships,
                'current_organization' => $organizations->first(),
                'current_brand' => $organizations->first()?->brands->first(),
                'token' => $tokenString,
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
            'message' => 'Login failed: ' . $e->getMessage(),
            'error_details' => [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
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

            // Load user's organizations and memberships
            $organizations = Organization::where('owner_id', $user->_id)
                ->orWhereHas('memberships', function ($query) use ($user) {
                    $query->where('user_id', $user->_id);
                })
                ->with(['brands'])
                ->get();

            $memberships = Membership::where('user_id', $user->_id)
                ->with(['organization', 'brand'])
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'user' => $user,
                    'organizations' => $organizations,
                    'memberships' => $memberships,
                    'current_organization' => $organizations->first(),
                    'current_brand' => $organizations->first()?->brands->first(),
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

    // ... rest of your methods (logout, logoutAll, refresh, sessions, revokeSession) remain the same
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

            $tokens = $user->tokens()->get()->map(function ($token) {
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
