<?php
// routes/api.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\SocialMediaPostController;
use App\Http\Controllers\Api\V1\OrganizationController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\MembershipController;
use App\Http\Controllers\Api\V1\ChannelController;
use App\Http\Controllers\Api\V1\AnalyticsController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Health Check Route
Route::get('/health', function () {
    return response()->json([
        'status' => 'healthy',
        'timestamp' => now()->toISOString(),
        'version' => '1.0.0',
        'environment' => app()->environment()
    ]);
});

// API Version 1 Routes
Route::prefix('v1')->group(function () {
    
    // ============================================
    // 🔐 AUTHENTICATION ROUTES (PUBLIC)
    // ============================================
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
        
        // Protected auth routes
        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::post('/logout-all', [AuthController::class, 'logoutAll']);
            Route::post('/refresh', [AuthController::class, 'refresh']);
            Route::get('/user', [AuthController::class, 'user']);
            Route::get('/sessions', [AuthController::class, 'sessions']);
            Route::delete('/sessions/{tokenId}', [AuthController::class, 'revokeSession']);
        });
    });

    // ============================================
    // 🔒 PROTECTED ROUTES (REQUIRE AUTHENTICATION)
    // ============================================
    Route::middleware('auth:sanctum')->group(function () {
        
        // ============================================
        // 👤 USER MANAGEMENT
        // ============================================
        Route::prefix('user')->group(function () {
            Route::get('/profile', [UserController::class, 'profile']);
            Route::put('/profile', [UserController::class, 'updateProfile']);
            Route::post('/change-password', [UserController::class, 'changePassword']);
            Route::get('/dashboard', [UserController::class, 'dashboard']);
            
            // Social Account Management
            Route::get('/social-accounts', [UserController::class, 'socialAccounts']);
            Route::post('/social-accounts', [UserController::class, 'connectSocialAccount'])
                ->middleware('subscription:social_accounts');
            Route::delete('/social-accounts/{platform}', [UserController::class, 'disconnectSocialAccount']);
        });

        // ============================================
        // 🏢 ORGANIZATION MANAGEMENT
        // ============================================
        Route::apiResource('organizations', OrganizationController::class);
        Route::post('organizations/{id}/features', [OrganizationController::class, 'addFeature'])
            ->middleware('permission:manage organizations');

        // ============================================
        // 🏷️ BRAND MANAGEMENT
        // ============================================
        Route::apiResource('brands', BrandController::class);
        Route::post('brands/{id}/branding', [BrandController::class, 'updateBranding'])
            ->middleware('permission:manage_brand');

        // ============================================
        // 👥 MEMBERSHIP & TEAM MANAGEMENT
        // ============================================
        Route::apiResource('memberships', MembershipController::class);
        Route::get('brands/{brandId}/team', [MembershipController::class, 'teamMembers']);
        Route::get('brands/{brandId}/permissions', [MembershipController::class, 'checkPermissions']);

        // ============================================
        // 📱 CHANNEL MANAGEMENT
        // ============================================
        Route::apiResource('channels', ChannelController::class);
        Route::get('channels/providers', [ChannelController::class, 'providers']);
        Route::post('channels/{id}/connect', [ChannelController::class, 'connect']);
        Route::post('channels/{id}/disconnect', [ChannelController::class, 'disconnect']);
        Route::post('channels/{id}/sync', [ChannelController::class, 'sync']);

        // ============================================
        // 📝 SOCIAL MEDIA POSTS
        // ============================================
        Route::apiResource('posts', SocialMediaPostController::class);
        Route::post('posts', [SocialMediaPostController::class, 'store'])
            ->middleware('subscription:posts');
        Route::post('posts/{id}/publish', [SocialMediaPostController::class, 'publish'])
            ->middleware('permission:create_posts');
        Route::post('posts/{id}/duplicate', [SocialMediaPostController::class, 'duplicate'])
            ->middleware('subscription:posts');
        Route::get('posts/{id}/analytics', [SocialMediaPostController::class, 'analytics'])
            ->middleware('permission:view_analytics');

        // ============================================
        // 📊 ANALYTICS & REPORTS
        // ============================================
        Route::prefix('analytics')->middleware('permission:view_analytics')->group(function () {
            Route::get('/overview', [AnalyticsController::class, 'overview']);
            Route::get('/posts/{postId}', [AnalyticsController::class, 'postAnalytics']);
            Route::get('/platforms', [AnalyticsController::class, 'platformComparison']);
            Route::get('/timeline', [AnalyticsController::class, 'engagementTimeline']);
            Route::post('/report', [AnalyticsController::class, 'generateReport']);
        });

        // ============================================
        // 📅 CONTENT CALENDAR (Future Enhancement)
        // ============================================
        Route::prefix('calendar')->group(function () {
            Route::get('/', function () {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Content calendar endpoints coming soon',
                    'available_endpoints' => [
                        'GET /calendar' => 'List calendar entries',
                        'POST /calendar' => 'Create calendar entry',
                        'GET /calendar/{date}' => 'Get entries for specific date',
                        'PUT /calendar/{id}' => 'Update calendar entry'
                    ]
                ]);
            });
        });

        // ============================================
        // ⏰ SCHEDULED POSTS (Future Enhancement)
        // ============================================
        Route::prefix('scheduled')->group(function () {
            Route::get('/', function () {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Scheduled posts endpoints coming soon',
                    'available_endpoints' => [
                        'GET /scheduled' => 'List scheduled posts',
                        'POST /scheduled' => 'Schedule a post',
                        'DELETE /scheduled/{id}' => 'Cancel scheduled post'
                    ]
                ]);
            });
        });
    });

    // ============================================
    // 🔓 PUBLIC ENDPOINTS (NO AUTH REQUIRED)
    // ============================================
    Route::prefix('public')->group(function () {
        Route::get('/health', function () {
            return response()->json([
                'api_status' => 'operational',
                'database' => 'connected',
                'redis' => 'connected',
                'version' => '1.0.0'
            ]);
        });

        Route::get('/stats', function () {
            return response()->json([
                'total_users' => \App\Models\User::count(),
                'total_posts' => \App\Models\SocialMediaPost::count(),
                'total_organizations' => \App\Models\Organization::count(),
                'supported_platforms' => ['twitter', 'facebook', 'instagram', 'linkedin', 'youtube', 'tiktok']
            ]);
        });
    });
});

// ============================================
// 📋 API DOCUMENTATION ROUTE
// ============================================
Route::get('/docs', function () {
    return response()->json([
        'api_documentation' => 'J33WAKASUPUN Social Media Management API v1.0',
        'base_url' => url('/api/v1'),
        'authentication' => 'Bearer Token (Sanctum)',
        'endpoints' => [
            'Authentication' => [
                'POST /auth/register' => 'Register new user',
                'POST /auth/login' => 'Login user',
                'POST /auth/logout' => 'Logout current session',
                'GET /auth/user' => 'Get authenticated user'
            ],
            'Organizations' => [
                'GET /organizations' => 'List organizations',
                'POST /organizations' => 'Create organization',
                'GET /organizations/{id}' => 'Get organization details',
                'PUT /organizations/{id}' => 'Update organization'
            ],
            'Brands' => [
                'GET /brands' => 'List brands',
                'POST /brands' => 'Create brand',
                'GET /brands/{id}' => 'Get brand details',
                'PUT /brands/{id}' => 'Update brand'
            ],
            'Posts' => [
                'GET /posts' => 'List posts',
                'POST /posts' => 'Create post',
                'GET /posts/{id}' => 'Get post details',
                'POST /posts/{id}/publish' => 'Publish post'
            ],
            'Analytics' => [
                'GET /analytics/overview' => 'Get analytics overview',
                'GET /analytics/posts/{id}' => 'Get post analytics',
                'GET /analytics/platforms' => 'Compare platforms'
            ]
        ],
        'rate_limits' => [
            'default' => '100 requests per hour',
            'authenticated' => '1000 requests per hour'
        ]
    ]);
});