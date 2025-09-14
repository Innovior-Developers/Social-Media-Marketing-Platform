<?php
// routes/api.php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
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
        // 📘 FACEBOOK INTEGRATION ENDPOINTS
        // ============================================
        Route::prefix('facebook')->group(function () {
            
            // Facebook OAuth & Authentication
            Route::prefix('auth')->group(function () {
                Route::get('/url', function () {
                    try {
                        $provider = new \App\Services\SocialMedia\FacebookProvider();
                        $state = 'facebook_api_' . auth('sanctum')->id() . '_' . time();
                        $authUrl = $provider->getAuthUrl($state);
                        
                        return response()->json([
                            'success' => true,
                            'auth_url' => $authUrl,
                            'state' => $state,
                            'instructions' => [
                                'Copy the auth_url and open in browser',
                                'Complete Facebook OAuth authorization',
                                'Return to app to continue'
                            ]
                        ]);
                    } catch (\Exception $e) {
                        return response()->json([
                            'success' => false,
                            'error' => $e->getMessage()
                        ], 500);
                    }
                });
                
                Route::get('/status', function () {
                    try {
                        // Check if user has Facebook tokens
                        $oauthSessionsPath = storage_path('app/oauth_sessions');
                        $facebookFiles = glob($oauthSessionsPath . '/oauth_tokens_facebook_*.json');
                        
                        $hasAuth = !empty($facebookFiles);
                        $tokenCount = count($facebookFiles);
                        
                        if ($hasAuth) {
                            $latestTokenFile = end($facebookFiles);
                            $facebookToken = json_decode(file_get_contents($latestTokenFile), true);
                            
                            return response()->json([
                                'success' => true,
                                'authenticated' => true,
                                'token_count' => $tokenCount,
                                'expires_at' => $facebookToken['expires_at'] ?? null,
                                'scopes' => $facebookToken['scopes'] ?? [],
                                'mode' => $facebookToken['mode'] ?? 'real'
                            ]);
                        }
                        
                        return response()->json([
                            'success' => true,
                            'authenticated' => false,
                            'message' => 'No Facebook authentication found',
                            'auth_url_endpoint' => '/api/v1/facebook/auth/url'
                        ]);
                        
                    } catch (\Exception $e) {
                        return response()->json([
                            'success' => false,
                            'error' => $e->getMessage()
                        ], 500);
                    }
                });
            });
            
            // Facebook Pages Management
            Route::prefix('pages')->group(function () {
                Route::get('/', function () {
                    try {
                        $oauthSessionsPath = storage_path('app/oauth_sessions');
                        $facebookFiles = glob($oauthSessionsPath . '/oauth_tokens_facebook_*.json');
                        
                        if (empty($facebookFiles)) {
                            return response()->json([
                                'success' => false,
                                'error' => 'No Facebook authentication found',
                                'auth_required' => true
                            ], 401);
                        }
                        
                        $latestTokenFile = end($facebookFiles);
                        $facebookToken = json_decode(file_get_contents($latestTokenFile), true);
                        
                        $response = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
                            'access_token' => $facebookToken['access_token'],
                            'fields' => 'id,name,category,followers_count,picture,access_token'
                        ]);
                        
                        if ($response->successful()) {
                            $pages = $response->json()['data'] ?? [];
                            
                            return response()->json([
                                'success' => true,
                                'pages' => $pages,
                                'total_pages' => count($pages)
                            ]);
                        }
                        
                        return response()->json([
                            'success' => false,
                            'error' => 'Failed to fetch Facebook pages',
                            'facebook_error' => $response->json()
                        ], 400);
                        
                    } catch (\Exception $e) {
                        return response()->json([
                            'success' => false,
                            'error' => $e->getMessage()
                        ], 500);
                    }
                });
            });
            
            // Facebook Posts Management
            Route::prefix('posts')->group(function () {
                
                // Get posts for dashboard display
                Route::get('/dashboard', function () {
                    try {
                        $oauthSessionsPath = storage_path('app/oauth_sessions');
                        $facebookFiles = glob($oauthSessionsPath . '/oauth_tokens_facebook_*.json');
                        
                        if (empty($facebookFiles)) {
                            return response()->json([
                                'success' => false,
                                'error' => 'No Facebook authentication',
                                'auth_required' => true
                            ], 401);
                        }
                        
                        $latestTokenFile = end($facebookFiles);
                        $facebookToken = json_decode(file_get_contents($latestTokenFile), true);
                        
                        // Get pages
                        $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
                            'access_token' => $facebookToken['access_token'],
                            'fields' => 'id,name,access_token,picture'
                        ]);
                        
                        if (!$pagesResponse->successful()) {
                            return response()->json([
                                'success' => false,
                                'error' => 'Failed to get Facebook pages'
                            ], 400);
                        }
                        
                        $pages = $pagesResponse->json()['data'] ?? [];
                        if (empty($pages)) {
                            return response()->json([
                                'success' => false,
                                'error' => 'No Facebook pages found'
                            ], 404);
                        }
                        
                        $selectedPage = $pages[0];
                        $pageAccessToken = $selectedPage['access_token'];
                        $pageId = $selectedPage['id'];
                        
                        // Get recent posts
                        $postsResponse = Http::get("https://graph.facebook.com/v18.0/{$pageId}/posts", [
                            'fields' => 'id,created_time,type',
                            'limit' => 20,
                            'access_token' => $pageAccessToken
                        ]);
                        
                        if (!$postsResponse->successful()) {
                            return response()->json([
                                'success' => false,
                                'error' => 'Failed to get Facebook posts'
                            ], 400);
                        }
                        
                        $posts = $postsResponse->json()['data'] ?? [];
                        
                        // Transform posts for frontend
                        $dashboardPosts = array_map(function($post) use ($selectedPage) {
                            $postId = $post['id'];
                            $facebookUrl = "https://facebook.com/{$postId}";
                            
                            return [
                                'id' => $postId,
                                'platform' => 'facebook',
                                'created_at' => $post['created_time'],
                                'type' => $post['type'] ?? 'status',
                                'page_info' => [
                                    'id' => $selectedPage['id'],
                                    'name' => $selectedPage['name'],
                                    'picture' => $selectedPage['picture']['data']['url'] ?? null
                                ],
                                'urls' => [
                                    'direct_link' => $facebookUrl,
                                    'embed_url' => "https://www.facebook.com/plugins/post.php?" . http_build_query([
                                        'href' => $facebookUrl,
                                        'width' => '500',
                                        'show_text' => 'true'
                                    ]),
                                    'mobile_link' => "fb://post/{$postId}"
                                ],
                                'display_data' => [
                                    'title' => 'Facebook Post',
                                    'subtitle' => $selectedPage['name'],
                                    'timestamp' => $post['created_time'],
                                    'platform_icon' => '📘',
                                    'platform_color' => '#1877f2'
                                ]
                            ];
                        }, $posts);
                        
                        return response()->json([
                            'success' => true,
                            'data' => [
                                'posts' => $dashboardPosts,
                                'pagination' => [
                                    'total' => count($dashboardPosts),
                                    'current_page' => 1,
                                    'per_page' => 20,
                                    'has_more' => count($posts) >= 20
                                ],
                                'page_info' => [
                                    'name' => $selectedPage['name'],
                                    'id' => $selectedPage['id'],
                                    'picture' => $selectedPage['picture']['data']['url'] ?? null
                                ]
                            ]
                        ]);
                        
                    } catch (\Exception $e) {
                        return response()->json([
                            'success' => false,
                            'error' => $e->getMessage()
                        ], 500);
                    }
                });
                
                // Get specific post display data
                Route::get('/{postId}/display-data', function ($postId) {
                    try {
                        $oauthSessionsPath = storage_path('app/oauth_sessions');
                        $facebookFiles = glob($oauthSessionsPath . '/oauth_tokens_facebook_*.json');
                        
                        if (empty($facebookFiles)) {
                            return response()->json([
                                'success' => false,
                                'error' => 'No Facebook authentication',
                                'fallback' => [
                                    'direct_link' => "https://facebook.com/{$postId}",
                                    'display_text' => 'View Post on Facebook'
                                ]
                            ], 401);
                        }
                        
                        $latestTokenFile = end($facebookFiles);
                        $facebookToken = json_decode(file_get_contents($latestTokenFile), true);
                        
                        // Get page info
                        $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
                            'access_token' => $facebookToken['access_token'],
                            'fields' => 'id,name,access_token,picture'
                        ]);
                        
                        if (!$pagesResponse->successful()) {
                            return response()->json([
                                'success' => false,
                                'error' => 'Failed to get page info',
                                'fallback' => [
                                    'direct_link' => "https://facebook.com/{$postId}"
                                ]
                            ], 400);
                        }
                        
                        $pages = $pagesResponse->json()['data'] ?? [];
                        if (empty($pages)) {
                            return response()->json([
                                'success' => false,
                                'error' => 'No pages found',
                                'fallback' => [
                                    'direct_link' => "https://facebook.com/{$postId}"
                                ]
                            ], 404);
                        }
                        
                        $selectedPage = $pages[0];
                        $pageAccessToken = $selectedPage['access_token'];
                        
                        // Try to get minimal post data
                        $postResponse = Http::get("https://graph.facebook.com/v18.0/{$postId}", [
                            'fields' => 'id,created_time,type',
                            'access_token' => $pageAccessToken
                        ]);
                        
                        // Generate Facebook URLs
                        $facebookPostUrl = "https://facebook.com/{$postId}";
                        $facebookEmbedUrl = "https://www.facebook.com/plugins/post.php?" . http_build_query([
                            'href' => $facebookPostUrl,
                            'width' => '500',
                            'show_text' => 'true'
                        ]);
                        
                        // Create frontend-optimized response
                        $displayData = [
                            'success' => true,
                            'data' => [
                                'post_id' => $postId,
                                'created_at' => $postResponse->successful() ? 
                                    ($postResponse->json()['created_time'] ?? now()->toISOString()) : 
                                    now()->toISOString(),
                                'type' => $postResponse->successful() ? 
                                    ($postResponse->json()['type'] ?? 'status') : 
                                    'status',
                                'platform' => 'facebook',
                                'page_info' => [
                                    'id' => $selectedPage['id'],
                                    'name' => $selectedPage['name'],
                                    'picture' => $selectedPage['picture']['data']['url'] ?? null
                                ],
                                'urls' => [
                                    'direct_link' => $facebookPostUrl,
                                    'embed_iframe' => $facebookEmbedUrl,
                                    'mobile_link' => "fb://post/{$postId}"
                                ],
                                'display_options' => [
                                    'embedded_post' => [
                                        'method' => 'iframe',
                                        'url' => $facebookEmbedUrl,
                                        'width' => '100%',
                                        'height' => 'auto',
                                        'recommended' => true
                                    ],
                                    'preview_card' => [
                                        'method' => 'custom_card',
                                        'title' => 'Facebook Post',
                                        'description' => "Post from {$selectedPage['name']}",
                                        'thumbnail' => $selectedPage['picture']['data']['url'] ?? null,
                                        'click_action' => 'open_facebook_url'
                                    ],
                                    'direct_button' => [
                                        'method' => 'link_button',
                                        'text' => 'View on Facebook',
                                        'url' => $facebookPostUrl,
                                        'target' => '_blank'
                                    ]
                                ],
                                'api_limitations' => [
                                    'content_preview' => 'Use Facebook embed for full content',
                                    'engagement_metrics' => 'Available only in Facebook Business Suite',
                                    'recommendation' => 'Use embedded Facebook post for best user experience'
                                ]
                            ]
                        ];
                        
                        return response()->json($displayData);
                        
                    } catch (\Exception $e) {
                        return response()->json([
                            'success' => false,
                            'error' => $e->getMessage(),
                            'fallback' => [
                                'direct_link' => "https://facebook.com/{$postId}",
                                'display_text' => 'View Post on Facebook'
                            ]
                        ], 500);
                    }
                });
                
                // Create new Facebook post
                Route::post('/create', function (Request $request) {
                    try {
                        $request->validate([
                            'message' => 'required|string|max:63206',
                            'link' => 'nullable|url',
                            'media' => 'nullable|array',
                            'media.*' => 'file|mimes:jpg,jpeg,png,gif,mp4,mov|max:102400' // 100MB
                        ]);
                        
                        $oauthSessionsPath = storage_path('app/oauth_sessions');
                        $facebookFiles = glob($oauthSessionsPath . '/oauth_tokens_facebook_*.json');
                        
                        if (empty($facebookFiles)) {
                            return response()->json([
                                'success' => false,
                                'error' => 'No Facebook authentication found'
                            ], 401);
                        }
                        
                        $latestTokenFile = end($facebookFiles);
                        $facebookToken = json_decode(file_get_contents($latestTokenFile), true);
                        
                        // Get page access token
                        $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
                            'access_token' => $facebookToken['access_token'],
                            'fields' => 'id,name,access_token'
                        ]);
                        
                        if (!$pagesResponse->successful()) {
                            return response()->json([
                                'success' => false,
                                'error' => 'Failed to get Facebook pages'
                            ], 400);
                        }
                        
                        $pages = $pagesResponse->json()['data'] ?? [];
                        if (empty($pages)) {
                            return response()->json([
                                'success' => false,
                                'error' => 'No Facebook pages found'
                            ], 404);
                        }
                        
                        $selectedPage = $pages[0];
                        $pageAccessToken = $selectedPage['access_token'];
                        $pageId = $selectedPage['id'];
                        
                        // Create post data
                        $postData = [
                            'message' => $request->input('message'),
                            'access_token' => $pageAccessToken
                        ];
                        
                        // Add link if provided
                        if ($request->has('link')) {
                            $postData['link'] = $request->input('link');
                        }
                        
                        // Post to Facebook
                        $response = Http::post("https://graph.facebook.com/v18.0/{$pageId}/feed", $postData);
                        
                        if ($response->successful()) {
                            $postResponse = $response->json();
                            $newPostId = $postResponse['id'];
                            
                            return response()->json([
                                'success' => true,
                                'data' => [
                                    'post_id' => $newPostId,
                                    'facebook_url' => "https://facebook.com/{$newPostId}",
                                    'page_name' => $selectedPage['name'],
                                    'created_at' => now()->toISOString(),
                                    'message' => 'Post created successfully on Facebook'
                                ]
                            ]);
                        }
                        
                        return response()->json([
                            'success' => false,
                            'error' => 'Failed to create Facebook post',
                            'facebook_error' => $response->json()
                        ], 400);
                        
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        return response()->json([
                            'success' => false,
                            'error' => 'Validation failed',
                            'validation_errors' => $e->errors()
                        ], 422);
                    } catch (\Exception $e) {
                        return response()->json([
                            'success' => false,
                            'error' => $e->getMessage()
                        ], 500);
                    }
                });
            });
            
            // Facebook Analytics (Limited due to API restrictions)
            Route::prefix('analytics')->group(function () {
                Route::get('/limitations', function () {
                    return response()->json([
                        'success' => true,
                        'facebook_analytics_status' => [
                            'api_limitations' => [
                                'message' => 'Facebook severely limits analytics access via Graph API',
                                'content_access' => 'Post content requires app review',
                                'engagement_data' => 'Detailed metrics require business verification'
                            ],
                            'available_alternatives' => [
                                [
                                    'name' => 'Facebook Insights',
                                    'url' => 'https://www.facebook.com/insights',
                                    'description' => 'Official Facebook analytics dashboard'
                                ],
                                [
                                    'name' => 'Facebook Business Suite',
                                    'url' => 'https://business.facebook.com/latest/insights',
                                    'description' => 'Comprehensive business analytics'
                                ],
                                [
                                    'name' => 'Meta Business API',
                                    'url' => 'https://developers.facebook.com/docs/marketing-api/insights',
                                    'description' => 'Advanced API for verified businesses'
                                ]
                            ],
                            'development_solution' => [
                                'stub_mode' => 'Enable stub mode for development analytics',
                                'endpoint' => 'Switch Facebook provider to stub mode'
                            ]
                        ]
                    ]);
                });
            });
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
            'Facebook Integration' => [
                'GET /facebook/auth/url' => 'Get Facebook OAuth URL',
                'GET /facebook/auth/status' => 'Check Facebook auth status',
                'GET /facebook/pages' => 'List Facebook pages',
                'GET /facebook/posts/dashboard' => 'Get Facebook posts for dashboard',
                'GET /facebook/posts/{id}/display-data' => 'Get post display data',
                'POST /facebook/posts/create' => 'Create Facebook post',
                'GET /facebook/analytics/limitations' => 'Facebook analytics info'
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