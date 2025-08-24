<?php
// app/Http/Controllers/Api/V1/UserController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Get authenticated user profile
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'user' => $user,
                    'stats' => [
                        'total_posts' => $user->posts()->count(),
                        'published_posts' => $user->posts()->where('post_status', 'published')->count(),
                        'scheduled_posts' => $user->posts()->where('post_status', 'scheduled')->count(),
                        'draft_posts' => $user->posts()->where('post_status', 'draft')->count(),
                        'connected_accounts' => $user->connectedSocialAccounts()->count(),
                        'remaining_posts' => $user->getRemainingPosts(),
                    ],
                    'subscription' => [
                        'plan' => $user->subscription['plan'] ?? 'free',
                        'limits' => $user->getSubscriptionLimits(),
                        'usage' => [
                            'posts_this_month' => $user->posts()
                                ->where('created_at', '>=', now()->startOfMonth())
                                ->count(),
                            'connected_accounts' => $user->connectedSocialAccounts()->count(),
                        ]
                    ],
                    'permissions' => [
                        'roles' => $user->getRoleNames(),
                        'all_permissions' => $user->getAllPermissions(),
                        'can_create_posts' => $user->hasPermission('create posts'),
                        'can_manage_team' => $user->hasPermission('manage team'),
                        'can_view_analytics' => $user->hasPermission('view analytics'),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'name' => 'string|max:255',
                'email' => 'email|unique:users,email,' . $user->_id,
                'profile' => 'array',
                'profile.bio' => 'string|max:500',
                'profile.avatar_url' => 'string|url',
                'profile.website' => 'string|url',
                'profile.location' => 'string|max:100',
                'profile.company' => 'string|max:100',
                'timezone' => 'string|timezone',
                'preferences' => 'array',
                'preferences.theme' => 'string|in:light,dark,auto',
                'preferences.language' => 'string|in:en,es,fr,de,it,pt',
                'preferences.notifications' => 'array',
                'preferences.posting' => 'array'
            ]);

            $user->update($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Profile updated successfully',
                'data' => $user->fresh()
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
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validated = $request->validate([
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed'
            ]);

            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            $user->update([
                'password' => Hash::make($validated['new_password'])
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Password changed successfully'
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
                'message' => 'Failed to change password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's social accounts
     */
    public function socialAccounts(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $socialAccounts = $user->getAttribute('social_accounts') ?? [];
            $connected = $user->connectedSocialAccounts();
            $limits = $user->getSubscriptionLimits();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'social_accounts' => $socialAccounts,
                    'connected_accounts' => $connected,
                    'stats' => [
                        'total_connected' => $connected->count(),
                        'max_allowed' => $limits['social_accounts'],
                        'can_add_more' => $user->canAddSocialAccount(),
                    ],
                    'supported_platforms' => [
                        'twitter' => 'Twitter/X',
                        'facebook' => 'Facebook',
                        'instagram' => 'Instagram',
                        'linkedin' => 'LinkedIn',
                        'youtube' => 'YouTube',
                        'tiktok' => 'TikTok'
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve social accounts',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Connect social account
     */
    public function connectSocialAccount(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (!$user->canAddSocialAccount()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You have reached the maximum number of social accounts for your plan'
                ], 429);
            }

            $validated = $request->validate([
                'platform' => 'required|string|in:twitter,facebook,instagram,linkedin,youtube,tiktok',
                'access_token' => 'required|string',
                'refresh_token' => 'string',
                'username' => 'required|string',
                'profile_data' => 'array'
            ]);

            $socialAccounts = $user->getAttribute('social_accounts') ?? [];
            $socialAccounts[$validated['platform']] = [
                'access_token' => $validated['access_token'],
                'refresh_token' => $validated['refresh_token'] ?? null,
                'username' => $validated['username'],
                'status' => 'active',
                'connected_at' => now(),
                'profile_data' => $validated['profile_data'] ?? []
            ];

            $user->setAttribute('social_accounts', $socialAccounts);
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => ucfirst($validated['platform']) . ' account connected successfully',
                'data' => [
                    'platform' => $validated['platform'],
                    'username' => $validated['username'],
                    'connected_accounts_count' => $user->connectedSocialAccounts()->count()
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
                'message' => 'Failed to connect social account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Disconnect social account
     */
    public function disconnectSocialAccount(Request $request, string $platform): JsonResponse
    {
        try {
            $user = $request->user();
            $socialAccounts = $user->getAttribute('social_accounts') ?? [];

            if (!isset($socialAccounts[$platform])) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Social account not found'
                ], 404);
            }

            unset($socialAccounts[$platform]);
            $user->setAttribute('social_accounts', $socialAccounts);
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => ucfirst($platform) . ' account disconnected successfully',
                'data' => [
                    'remaining_accounts' => $user->connectedSocialAccounts()->count()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to disconnect social account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user dashboard data
     */
    public function dashboard(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Recent posts
            $recentPosts = $user->posts()
                ->latest()
                ->limit(5)
                ->get();

            // Upcoming scheduled posts
            $upcomingPosts = $user->scheduledPosts()
                ->where('status', 'pending')
                ->where('scheduled_at', '>', now())
                ->orderBy('scheduled_at')
                ->limit(5)
                ->with('socialMediaPost')
                ->get();

            // Monthly stats
            $thisMonth = [
                'posts_published' => $user->posts()
                    ->where('post_status', 'published')
                    ->where('published_at', '>=', now()->startOfMonth())
                    ->count(),
                'total_engagement' => $user->posts()
                    ->where('published_at', '>=', now()->startOfMonth())
                    ->get()
                    ->sum(fn($post) => $post->getTotalEngagement()),
            ];

            return response()->json([
                'status' => 'success',
                'data' => [
                    'user' => $user,
                    'stats' => [
                        'total_posts' => $user->posts()->count(),
                        'connected_accounts' => $user->connectedSocialAccounts()->count(),
                        'remaining_posts' => $user->getRemainingPosts(),
                        'this_month' => $thisMonth,
                    ],
                    'recent_posts' => $recentPosts,
                    'upcoming_posts' => $upcomingPosts,
                    'subscription_status' => [
                        'plan' => $user->subscription['plan'] ?? 'free',
                        'limits' => $user->getSubscriptionLimits(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to load dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
