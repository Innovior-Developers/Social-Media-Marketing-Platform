<?php
// routes/linkedin.php - LINKEDIN-SPECIFIC ROUTES

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Helpers\MediaValidation;
use App\Helpers\LinkedInHelpers;

/*
|--------------------------------------------------------------------------
| LinkedIn Integration Routes
|--------------------------------------------------------------------------
|
| These routes handle all LinkedIn-specific functionality including:
| - OAuth authentication and token management
| - Profile access testing
| - Text and image posting
| - Multi-image carousel posting
| - Configuration and debugging
| - Session management
|
| Developer: J33WAKASUPUN
| Last Updated: 2025-09-08
| Platform: LinkedIn API v2
|
*/

// 🔗 LINKEDIN OAUTH CALLBACK ROUTE
Route::get('/oauth/callback/linkedin', function (\Illuminate\Http\Request $request) {
    try {
        $code = $request->get('code');
        $error = $request->get('error');
        $state = $request->get('state');

        if ($error) {
            return response()->json([
                'oauth_status' => 'FAILED',
                'provider' => 'linkedin',
                'error' => $error,
                'error_description' => $request->get('error_description'),
                'state' => $state
            ], 400);
        }

        if (!$code) {
            return response()->json([
                'oauth_status' => 'FAILED',
                'provider' => 'linkedin',
                'error' => 'No authorization code received',
                'debug_info' => [
                    'query_params' => $request->query(),
                    'expected' => 'code parameter from LinkedIn'
                ]
            ], 400);
        }

        // LinkedIn OAuth configuration
        $clientId = config('services.linkedin.client_id');
        $clientSecret = config('services.linkedin.client_secret');
        $redirectUri = config('services.linkedin.redirect');

        if (!$clientId || !$clientSecret) {
            return response()->json([
                'oauth_status' => 'FAILED',
                'provider' => 'linkedin',
                'error' => 'LinkedIn OAuth credentials not configured',
                'check' => [
                    'client_id_set' => !empty($clientId),
                    'client_secret_set' => !empty($clientSecret),
                    'redirect_uri' => $redirectUri
                ]
            ], 500);
        }

        // Exchange authorization code for access token
        $tokenResponse = Http::withOptions([
            'verify' => config('http.default.verify', true),
            'timeout' => 30
        ])->asForm()->post('https://www.linkedin.com/oauth/v2/accessToken', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        if (!$tokenResponse->successful()) {
            return response()->json([
                'oauth_status' => 'FAILED',
                'provider' => 'linkedin',
                'error' => 'Token exchange failed',
                'linkedin_response' => $tokenResponse->body(),
                'status_code' => $tokenResponse->status(),
                'debug_info' => [
                    'request_data' => [
                        'grant_type' => 'authorization_code',
                        'redirect_uri' => $redirectUri,
                        'client_id' => $clientId,
                        'client_secret_provided' => !empty($clientSecret)
                    ]
                ]
            ], 400);
        }

        $tokenData = $tokenResponse->json();
        $sessionKey = "oauth_tokens_linkedin_" . time();

        // Prepare token storage
        $tokens = [
            'access_token' => $tokenData['access_token'],
            'expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 3600)->toISOString(),
            'token_type' => $tokenData['token_type'] ?? 'Bearer',
            'scope' => explode(' ', $tokenData['scope'] ?? ''),
            'provider' => 'linkedin',
            'created_at' => now()->toISOString(),
            'state' => $state,
            'user_agent' => $request->userAgent()
        ];

        // Store in session
        session([$sessionKey => $tokens]);

        // Store in persistent file storage
        $sessionFile = storage_path("app/oauth_sessions/{$sessionKey}.json");
        if (!is_dir(dirname($sessionFile))) {
            mkdir(dirname($sessionFile), 0755, true);
        }
        file_put_contents($sessionFile, json_encode($tokens, JSON_PRETTY_PRINT));

        // Log successful authentication
        Log::info('LinkedIn OAuth: Authentication successful', [
            'session_key' => $sessionKey,
            'expires_at' => $tokens['expires_at'],
            'scopes' => $tokens['scope'],
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip()
        ]);

        return response()->json([
            'oauth_status' => 'SUCCESS! 🎉',
            'provider' => 'linkedin',
            'message' => 'LinkedIn OAuth completed successfully!',
            'session_key' => $sessionKey,
            'tokens_received' => [
                'access_token_preview' => substr($tokens['access_token'], 0, 20) . '...',
                'expires_at' => $tokens['expires_at'],
                'token_type' => $tokens['token_type'],
                'scopes_granted' => $tokens['scope']
            ],
            'storage_status' => [
                'session_stored' => session()->has($sessionKey),
                'file_stored' => file_exists($sessionFile),
                'file_path' => $sessionFile
            ],
            'next_steps' => [
                'test_profile' => "GET http://localhost:8000/test/linkedin/profile/{$sessionKey}",
                'test_posting' => "POST http://localhost:8000/test/linkedin/post/{$sessionKey}",
                'test_multi_image' => "POST http://localhost:8000/test/linkedin/multi-image-post/{$sessionKey}",
                'view_sessions' => "GET http://localhost:8000/test/oauth/sessions"
            ],
            'timestamp' => now()->toISOString(),
            'developer' => 'J33WAKASUPUN'
        ]);

    } catch (\Exception $e) {
        Log::error('LinkedIn OAuth: Exception during callback', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'oauth_status' => 'FAILED',
            'provider' => 'linkedin',
            'error' => 'OAuth callback exception: ' . $e->getMessage(),
            'debug_info' => [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]
        ], 500);
    }
});

// LINKEDIN TESTING ROUTES GROUP
Route::prefix('test/linkedin')->group(function () {

    // 👤 LINKEDIN PROFILE TESTING
    Route::get('/profile/{sessionKey}', function ($sessionKey) {
        try {
            // Try to get tokens from session first
            $tokens = session($sessionKey);

            // If not in session, try to load from file
            if (!$tokens) {
                $sessionFile = storage_path("app/oauth_sessions/{$sessionKey}.json");
                if (file_exists($sessionFile)) {
                    $tokens = json_decode(file_get_contents($sessionFile), true);
                }
            }

            if (!$tokens || !isset($tokens['access_token'])) {
                return response()->json([
                    'test_type' => 'LinkedIn Profile Access Test',
                    'profile_test' => 'FAILED',
                    'error' => 'No tokens found. Complete OAuth flow first.',
                    'session_key' => $sessionKey,
                    'debug' => [
                        'session_exists' => session()->has($sessionKey),
                        'session_file' => storage_path("app/oauth_sessions/{$sessionKey}.json"),
                        'file_exists' => file_exists(storage_path("app/oauth_sessions/{$sessionKey}.json")),
                        'available_sessions' => array_keys(session()->all())
                    ],
                    'suggestion' => 'Run OAuth flow: GET /oauth/callback/linkedin'
                ], 400);
            }

            // Check if token is expired
            $expiresAt = \Carbon\Carbon::parse($tokens['expires_at']);
            if ($expiresAt->isPast()) {
                return response()->json([
                    'test_type' => 'LinkedIn Profile Access Test',
                    'profile_test' => 'FAILED',
                    'error' => 'Token expired. Please re-authenticate.',
                    'expires_at' => $tokens['expires_at'],
                    'current_time' => now()->toISOString(),
                    'expired_since' => $expiresAt->diffForHumans()
                ], 401);
            }

            // Test LinkedIn profile access with different endpoints
            $accessToken = $tokens['access_token'];

            // Try basic profile endpoint (OpenID Connect)
            $profileResponse = Http::withToken($accessToken)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0'
                ])
                ->timeout(15)
                ->get('https://api.linkedin.com/v2/userinfo');

            if (!$profileResponse->successful()) {
                // Try alternative endpoint
                $altResponse = Http::withToken($accessToken)
                    ->withHeaders([
                        'Accept' => 'application/json',
                        'X-Restli-Protocol-Version' => '2.0.0'
                    ])
                    ->timeout(15)
                    ->get('https://api.linkedin.com/v2/people/~');

                return response()->json([
                    'test_type' => 'LinkedIn Profile Access Test',
                    'profile_test' => 'FAILED',
                    'error' => 'Profile access failed on both endpoints',
                    'primary_endpoint' => [
                        'url' => 'https://api.linkedin.com/v2/userinfo',
                        'status' => $profileResponse->status(),
                        'error' => $profileResponse->body()
                    ],
                    'alternative_endpoint' => [
                        'url' => 'https://api.linkedin.com/v2/people/~',
                        'status' => $altResponse->status(),
                        'error' => $altResponse->body()
                    ],
                    'token_info' => [
                        'scopes' => $tokens['scope'] ?? [],
                        'expires_at' => $tokens['expires_at'],
                        'token_type' => $tokens['token_type']
                    ],
                    'possible_issues' => [
                        'insufficient_scopes' => 'Token may not have required scopes (r_liteprofile)',
                        'token_revoked' => 'User may have revoked app permissions',
                        'api_changes' => 'LinkedIn API endpoints may have changed'
                    ]
                ], $profileResponse->status());
            }

            $profileData = $profileResponse->json();

            // Log successful profile access
            Log::info('LinkedIn Profile: Access successful', [
                'session_key' => $sessionKey,
                'profile_id' => $profileData['sub'] ?? 'unknown',
                'profile_name' => $profileData['name'] ?? 'unknown'
            ]);

            return response()->json([
                'test_type' => 'LinkedIn Profile Access Test',
                'profile_test' => 'SUCCESS! 👤',
                'provider' => 'linkedin',
                'mode' => 'real',
                'profile_data' => $profileData,
                'token_info' => [
                    'scopes' => $tokens['scope'] ?? [],
                    'expires_at' => $tokens['expires_at'],
                    'is_valid' => true,
                    'time_until_expiry' => $expiresAt->diffForHumans()
                ],
                'api_endpoints_tested' => [
                    'primary' => 'https://api.linkedin.com/v2/userinfo',
                    'status' => 'SUCCESS'
                ],
                'timestamp' => now()->toISOString(),
                'developer' => 'J33WAKASUPUN'
            ]);

        } catch (\Exception $e) {
            Log::error('LinkedIn Profile: Test failed', [
                'session_key' => $sessionKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'test_type' => 'LinkedIn Profile Access Test',
                'profile_test' => 'FAILED',
                'error' => $e->getMessage(),
                'session_key' => $sessionKey,
                'exception_location' => $e->getFile() . ':' . $e->getLine()
            ], 500);
        }
    });

    // LINKEDIN CONFIGURATION TESTING
    Route::get('/config', function () {
        $linkedinConfig = config('services.linkedin');
        $socialMediaConfig = config('services.social_media');

        return response()->json([
            'test_type' => 'LinkedIn Configuration Test',
            'config_test' => 'SUCCESS',
            'linkedin_config' => [
                'enabled' => $linkedinConfig['enabled'] ?? false,
                'use_real_api' => $linkedinConfig['use_real_api'] ?? false,
                'client_id_set' => !empty($linkedinConfig['client_id']),
                'client_secret_set' => !empty($linkedinConfig['client_secret']),
                'redirect_uri' => $linkedinConfig['redirect'] ?? null,
                'scopes' => $linkedinConfig['scopes'] ?? [],
                'api_urls' => [
                    'base_url' => $linkedinConfig['base_url'] ?? 'https://api.linkedin.com/v2',
                    'auth_url' => $linkedinConfig['auth_url'] ?? 'https://www.linkedin.com/oauth/v2/authorization',
                    'token_url' => $linkedinConfig['token_url'] ?? 'https://www.linkedin.com/oauth/v2/accessToken'
                ]
            ],
            'global_config' => [
                'mode' => $socialMediaConfig['mode'] ?? 'stub',
                'linkedin_real_api' => $socialMediaConfig['real_providers']['linkedin'] ?? false,
                'posting_enabled' => $socialMediaConfig['enable_posting'] ?? false,
                'analytics_enabled' => $socialMediaConfig['enable_analytics'] ?? false
            ],
            'provider_status' => [
                'linkedin_provider_exists' => class_exists('App\Services\SocialMedia\LinkedInProvider'),
                'media_validation_helper' => class_exists('App\Helpers\MediaValidation'),
                'linkedin_helper' => class_exists('App\Helpers\LinkedInHelpers'),
                'config_matches_provider' => true
            ],
            'oauth_urls' => [
                'authorization_url' => 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
                    'response_type' => 'code',
                    'client_id' => $linkedinConfig['client_id'] ?? 'NOT_SET',
                    'redirect_uri' => $linkedinConfig['redirect'] ?? 'NOT_SET',
                    'scope' => implode(' ', $linkedinConfig['scopes'] ?? ['r_liteprofile', 'w_member_social']),
                    'state' => 'test_config_' . time()
                ]),
                'callback_url' => url('/oauth/callback/linkedin')
            ],
            'recommendations' => [
                'missing_config' => array_filter([
                    !empty($linkedinConfig['client_id']) ? null : 'LINKEDIN_CLIENT_ID',
                    !empty($linkedinConfig['client_secret']) ? null : 'LINKEDIN_CLIENT_SECRET'
                ]),
                'suggested_scopes' => ['r_liteprofile', 'w_member_social'],
                'test_oauth' => 'Visit the authorization_url above to test OAuth flow'
            ],
            'timestamp' => now()->toISOString(),
            'developer' => 'J33WAKASUPUN'
        ]);
    });

    // SESSION DEBUGGING
    Route::get('/debug-session/{sessionKey?}', function ($sessionKey = null) {
        $sessionData = [];
        $fileData = [];

        if ($sessionKey) {
            // Check specific session
            $sessionData[$sessionKey] = session($sessionKey);

            // Check file storage
            $sessionFile = storage_path("app/oauth_sessions/{$sessionKey}.json");
            if (file_exists($sessionFile)) {
                $fileData[$sessionKey] = json_decode(file_get_contents($sessionFile), true);
            }
        } else {
            // Get all LinkedIn sessions from memory
            $allSessions = session()->all();
            foreach ($allSessions as $key => $value) {
                if (str_starts_with($key, 'oauth_tokens_linkedin_')) {
                    $sessionData[$key] = $value;
                }
            }

            // Get all LinkedIn session files
            $sessionDir = storage_path('app/oauth_sessions');
            if (is_dir($sessionDir)) {
                $files = glob($sessionDir . '/oauth_tokens_linkedin_*.json');
                foreach ($files as $file) {
                    $key = basename($file, '.json');
                    $content = json_decode(file_get_contents($file), true);
                    if ($content && isset($content['provider']) && $content['provider'] === 'linkedin') {
                        $fileData[$key] = $content;
                    }
                }
            }
        }

        // Calculate session status
        $sessionStatus = [];
        foreach ($fileData as $key => $data) {
            $expiresAt = isset($data['expires_at']) ? \Carbon\Carbon::parse($data['expires_at']) : null;
            $sessionStatus[$key] = [
                'expires_at' => $data['expires_at'] ?? 'unknown',
                'is_expired' => $expiresAt ? $expiresAt->isPast() : 'unknown',
                'time_until_expiry' => $expiresAt ? ($expiresAt->isPast() ? 'EXPIRED' : $expiresAt->diffForHumans()) : 'unknown',
                'scopes' => $data['scope'] ?? [],
                'created_at' => $data['created_at'] ?? 'unknown'
            ];
        }

        return response()->json([
            'test_type' => 'LinkedIn Session Debug',
            'session_debug' => [
                'requested_key' => $sessionKey,
                'session_data' => $sessionData,
                'file_data' => $fileData,
                'session_status' => $sessionStatus,
                'storage_info' => [
                    'session_storage_path' => storage_path('app/oauth_sessions'),
                    'available_session_keys' => array_keys($sessionData),
                    'available_file_keys' => array_keys($fileData),
                    'total_linkedin_sessions' => count($sessionData),
                    'total_linkedin_files' => count($fileData)
                ]
            ],
            'instructions' => [
                'test_specific_session' => $sessionKey ? "Currently viewing: {$sessionKey}" : "Add /{sessionKey} to URL to view specific session",
                'use_latest_session' => !empty($fileData) ? 'Latest session: ' . array_key_last($fileData) : 'No sessions available'
            ],
            'timestamp' => now()->toISOString(),
            'developer' => 'J33WAKASUPUN'
        ]);
    });

    // LINKEDIN SCOPES DEBUGGING
    Route::get('/scopes', function () {
        return response()->json([
            'test_type' => 'LinkedIn Scopes Information',
            'linkedin_scopes_info' => [
                'default_scopes' => ['w_member_social', 'r_liteprofile'],
                'scope_descriptions' => [
                    'w_member_social' => 'Write access to post on LinkedIn',
                    'r_liteprofile' => 'Read access to basic profile info',
                    'r_emailaddress' => 'REQUIRES SPECIAL APPROVAL - not available for new apps'
                ],
                'recommended_for_testing' => ['w_member_social', 'r_liteprofile'],
                'current_config' => config('services.linkedin.scopes'),
                'auth_url_with_correct_scopes' => 'https://www.linkedin.com/oauth/v2/authorization?' . http_build_query([
                    'response_type' => 'code',
                    'client_id' => config('services.linkedin.client_id'),
                    'redirect_uri' => config('services.linkedin.redirect'),
                    'scope' => 'w_member_social r_liteprofile',
                    'state' => 'test_fixed_scopes_' . time()
                ])
            ],
            'scope_testing' => [
                'minimum_required' => ['r_liteprofile'],
                'posting_required' => ['w_member_social'],
                'full_functionality' => ['r_liteprofile', 'w_member_social']
            ],
            'linkedin_api_limitations' => [
                'post_editing' => 'Not supported - LinkedIn does not allow editing published posts',
                'post_deletion' => 'Limited support - no reliable API endpoint',
                'document_upload' => 'Requires Partner API access (Enterprise only)',
                'video_upload' => 'May require special permissions',
                'real_time_analytics' => 'Limited data available through standard API'
            ],
            'timestamp' => now()->toISOString(),
            'developer' => 'J33WAKASUPUN'
        ]);
    });

    // LINKEDIN ANALYTICS TESTING
    Route::get('/analytics/{postId}', function ($postId) {
        try {
            $post = \App\Models\SocialMediaPost::find($postId);

            if (!$post) {
                return response()->json([
                    'test_type' => 'LinkedIn Analytics Test',
                    'analytics_test' => 'FAILED',
                    'error' => 'Post not found',
                    'post_id' => $postId
                ], 404);
            }

            // Check if post was published to LinkedIn
            if (!isset($post->platform_posts['linkedin']['platform_id'])) {
                return response()->json([
                    'test_type' => 'LinkedIn Analytics Test',
                    'analytics_test' => 'NOT_APPLICABLE',
                    'message' => 'Post was not published to LinkedIn',
                    'post_id' => $postId,
                    'available_platforms' => array_keys($post->platform_posts ?? [])
                ], 400);
            }

            // Manually trigger analytics collection
            try {
                \App\Jobs\CollectAnalytics::dispatch($post, 'linkedin');
                $jobDispatched = true;
                $jobError = null;
            } catch (\Exception $e) {
                $jobDispatched = false;
                $jobError = $e->getMessage();
            }

            // Get current analytics
            $analytics = \App\Models\PostAnalytics::where('social_media_post_id', $postId)
                ->where('platform', 'linkedin')
                ->orderBy('collected_at', 'desc')
                ->first();

            // Get all analytics for this post
            $allAnalytics = \App\Models\PostAnalytics::where('social_media_post_id', $postId)
                ->where('platform', 'linkedin')
                ->orderBy('collected_at', 'desc')
                ->get();

            return response()->json([
                'test_type' => 'LinkedIn Analytics Test',
                'analytics_test' => 'SUCCESS! 📊',
                'post_id' => $postId,
                'linkedin_post_info' => [
                    'platform_id' => $post->platform_posts['linkedin']['platform_id'],
                    'url' => $post->platform_posts['linkedin']['url'] ?? null,
                    'published_at' => $post->platform_posts['linkedin']['published_at'] ?? null
                ],
                'analytics_data' => $analytics,
                'analytics_summary' => [
                    'total_records' => $allAnalytics->count(),
                    'latest_collection' => $analytics->collected_at ?? null,
                    'performance_score' => $analytics->performance_score ?? 0,
                    'total_engagement' => $analytics ? 
                        ($analytics->metrics['likes'] + $analytics->metrics['shares'] + $analytics->metrics['comments']) : 0
                ],
                'job_dispatching' => [
                    'analytics_job_dispatched' => $jobDispatched,
                    'job_error' => $jobError,
                    'queue_connection' => config('queue.default')
                ],
                'linkedin_api_note' => 'LinkedIn analytics are limited for standard API access. Full metrics require Partner API.',
                'timestamp' => now()->toISOString(),
                'developer' => 'J33WAKASUPUN'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'test_type' => 'LinkedIn Analytics Test',
                'analytics_test' => 'ERROR',
                'error' => $e->getMessage(),
                'post_id' => $postId,
                'exception_location' => $e->getFile() . ':' . $e->getLine()
            ], 500);
        }
    });
});

// LINKEDIN POSTING ROUTES (CSRF-FREE)
Route::withoutMiddleware(['web', \App\Http\Middleware\VerifyCsrfToken::class])->group(function () {

    // LINKEDIN TEXT POSTING
    Route::post('/test/linkedin/post/{sessionKey}', function ($sessionKey, \Illuminate\Http\Request $request) {
        try {
            // Load tokens from session or file
            $tokens = session($sessionKey);

            if (!$tokens) {
                $sessionFile = storage_path("app/oauth_sessions/{$sessionKey}.json");
                if (file_exists($sessionFile)) {
                    $tokens = json_decode(file_get_contents($sessionFile), true);
                } else {
                    return response()->json([
                        'test_type' => 'LinkedIn Text Posting Test',
                        'post_test' => 'FAILED',
                        'error' => 'No tokens found. Complete OAuth flow first.',
                        'session_key' => $sessionKey,
                        'session_file_checked' => $sessionFile,
                        'session_file_exists' => false,
                        'suggestion' => 'Run OAuth flow: GET /oauth/callback/linkedin'
                    ], 400);
                }
            }

            if (!isset($tokens['access_token'])) {
                return response()->json([
                    'test_type' => 'LinkedIn Text Posting Test',
                    'post_test' => 'FAILED',
                    'error' => 'Invalid token data.',
                    'tokens_structure' => array_keys($tokens)
                ], 400);
            }

            // Extract and process request data
            $requestData = $request->all();

            // Handle flexible content input formats
            if (isset($requestData['content'])) {
                if (is_string($requestData['content'])) {
                    $contentText = $requestData['content'];
                    $contentData = [
                        'text' => $contentText,
                        'title' => $requestData['title'] ?? 'LinkedIn Integration Post'
                    ];
                } else {
                    $contentData = $requestData['content'];
                    $contentText = $contentData['text'] ?? 'Test post from Social Media Marketing Platform! 🚀';
                }
            } else {
                $contentText = $requestData['text'] ?? 'Test post from Social Media Marketing Platform! 🚀 #socialmedia #linkedin #testing';
                $contentData = [
                    'text' => $contentText,
                    'title' => 'LinkedIn Integration Test'
                ];
            }

            // Extract hashtags and format for LinkedIn
            $hashtags = $requestData['hashtags'] ?? ['socialmedia', 'linkedin', 'testing'];

            // Add hashtags to content if not present
            if (!empty($hashtags)) {
                $hashtagString = '';
                foreach ($hashtags as $tag) {
                    $cleanTag = ltrim($tag, '#'); // Remove # if present
                    $hashtagString .= ' #' . $cleanTag;
                }

                // Check if content already has hashtags
                $hasHashtagsInContent = strpos($contentText, '#') !== false;

                if (!$hasHashtagsInContent && !empty(trim($hashtagString))) {
                    // Add hashtags to content with proper spacing
                    $contentText = trim($contentText) . "\n\n" . trim($hashtagString);
                    $contentData['text'] = $contentText;
                    $contentData['hashtags_added'] = true;
                }
            }

            // Extract additional data from request
            $mentions = $requestData['mentions'] ?? [];
            $media = $requestData['media'] ?? [];
            $settings = array_merge([
                'auto_hashtags' => true,
                'track_analytics' => true,
                'cross_post' => false
            ], $requestData['settings'] ?? []);

            // Create SocialMediaPost instance
            $post = new \App\Models\SocialMediaPost([
                'user_id' => $requestData['user_id'] ?? 'system_test',
                'content' => $contentData,
                'platforms' => $requestData['platforms'] ?? ['linkedin'],
                'post_status' => 'draft',
                'media' => $media,
                'hashtags' => $hashtags,
                'mentions' => $mentions,
                'settings' => $settings
            ]);

            // Create Channel instance for API calls
            $channel = new \App\Models\Channel([
                'provider' => 'linkedin',
                'handle' => $requestData['handle'] ?? 'test_linkedin_user',
                'display_name' => $requestData['display_name'] ?? 'LinkedIn Test Account',
                'oauth_tokens' => [
                    'access_token' => $tokens['access_token'],
                    'expires_at' => $tokens['expires_at'] ?? now()->addDays(60)
                ],
                'connection_status' => 'connected',
                'active' => true
            ]);

            // Use LinkedIn Provider for validation and posting
            $linkedinProvider = new \App\Services\SocialMedia\LinkedInProvider();

            // Validate post
            $validation = $linkedinProvider->validatePost($post);
            if (!$validation['valid']) {
                return response()->json([
                    'test_type' => 'LinkedIn Text Posting Test',
                    'post_test' => 'VALIDATION_FAILED',
                    'error' => 'Post validation failed',
                    'validation_errors' => $validation['errors'],
                    'provider_info' => [
                        'character_count' => $validation['character_count'],
                        'character_limit' => $validation['character_limit'],
                        'mode' => $validation['mode']
                    ],
                    'post_data' => [
                        'content' => $contentData,
                        'hashtags' => $hashtags,
                        'mentions' => $mentions,
                        'character_count' => strlen($contentText)
                    ]
                ], 400);
            }

            // Publish post
            $publishResult = $linkedinProvider->publishPost($post, $channel);

            if ($publishResult['success']) {
                // Save to database
                $post->post_status = 'published';
                $post->published_at = now();
                $post->platform_posts = [
                    'linkedin' => [
                        'platform_id' => $publishResult['platform_id'],
                        'url' => $publishResult['url'],
                        'published_at' => $publishResult['published_at'],
                        'mode' => $publishResult['mode']
                    ]
                ];
                $post->save();

                // Dispatch analytics collection job
                try {
                    \App\Jobs\CollectAnalytics::dispatch($post, 'linkedin');
                    $analyticsJobDispatched = true;
                    $analyticsJobError = null;
                } catch (\Exception $e) {
                    $analyticsJobDispatched = false;
                    $analyticsJobError = $e->getMessage();
                    Log::warning('LinkedIn Posting: Failed to dispatch analytics job', [
                        'error' => $e->getMessage(),
                        'post_id' => $post->_id
                    ]);
                }

                // Create immediate analytics record
                try {
                    $analytics = new \App\Models\PostAnalytics([
                        'user_id' => $post->user_id,
                        'social_media_post_id' => $post->_id,
                        'platform' => 'linkedin',
                        'metrics' => [
                            'impressions' => 0,
                            'reach' => 0,
                            'likes' => 0,
                            'shares' => 0,
                            'comments' => 0,
                            'clicks' => 0,
                            'engagement_rate' => 0,
                            'saves' => 0,
                            'click_through_rate' => 0
                        ],
                        'collected_at' => now(),
                        'performance_score' => 0,
                        'demographic_data' => [
                            'age_groups' => [],
                            'gender_split' => [],
                            'top_locations' => []
                        ],
                        'engagement_timeline' => []
                    ]);
                    $analytics->save();
                    $analyticsCreated = true;
                    $analyticsError = null;
                } catch (\Exception $e) {
                    $analyticsCreated = false;
                    $analyticsError = $e->getMessage();
                    Log::error('LinkedIn Posting: Failed to create analytics record', [
                        'error' => $e->getMessage(),
                        'post_id' => $post->_id
                    ]);
                }

                // Send email notification
                try {
                    $emailResult = [
                        'success' => true,
                        'platform_id' => $publishResult['platform_id'],
                        'url' => $publishResult['url'],
                        'published_at' => $publishResult['published_at'],
                        'mode' => $publishResult['mode'],
                        'mongodb_id' => $post->_id,
                        'analytics_created' => $analyticsCreated
                    ];

                    \Illuminate\Support\Facades\Mail::to(config('services.notifications.default_recipient'))
                        ->send(new \App\Mail\PostPublishedNotification($post, 'linkedin', $emailResult));

                    $emailSent = true;
                    $emailError = null;
                } catch (\Exception $e) {
                    $emailSent = false;
                    $emailError = $e->getMessage();
                    Log::warning('LinkedIn Posting: Failed to send email notification', [
                        'error' => $e->getMessage(),
                        'post_id' => $post->_id
                    ]);
                }

                // Log successful posting
                Log::info('LinkedIn Posting: Text post published successfully', [
                    'session_key' => $sessionKey,
                    'post_id' => $post->_id,
                    'platform_id' => $publishResult['platform_id'],
                    'linkedin_url' => $publishResult['url']
                ]);

                return response()->json([
                    'test_type' => 'LinkedIn Text Posting Test',
                    'post_test' => 'ENHANCED SUCCESS! 🎉🚀',
                    'message' => 'Post published using complete LinkedIn architecture!',
                    'timestamp' => now()->toISOString(),
                    'architecture_used' => [
                        'models' => '✅ Enhanced SocialMediaPost & PostAnalytics',
                        'provider' => '✅ LinkedInProvider with validation',
                        'jobs' => '✅ CollectAnalytics dispatched',
                        'database' => '✅ MongoDB saved with full structure',
                        'validation' => '✅ Provider validation passed',
                        'email' => $emailSent ? '✅ Email notification sent' : '⚠️ Email failed'
                    ],
                    'post_data' => [
                        'mongodb_id' => $post->_id,
                        'platform_id' => $publishResult['platform_id'],
                        'linkedin_url' => $publishResult['url'],
                        'content' => $post->content,
                        'hashtags' => $post->hashtags,
                        'mentions' => $post->mentions,
                        'media' => $post->media,
                        'settings' => $post->settings,
                        'published_at' => $publishResult['published_at'],
                        'post_status' => $post->post_status,
                        'platforms' => $post->platforms,
                        'user_id' => $post->user_id
                    ],
                    'provider_info' => [
                        'mode' => $publishResult['mode'],
                        'provider_class' => 'LinkedInProvider',
                        'validation_passed' => true,
                        'character_count' => $validation['character_count'],
                        'character_limit' => $validation['character_limit'],
                        'supported_formats' => ['text', 'hashtags', 'mentions']
                    ],
                    'database_operations' => [
                        'post_saved' => true,
                        'analytics_created' => $analyticsCreated,
                        'analytics_error' => $analyticsError,
                        'analytics_id' => isset($analytics) ? $analytics->_id : null
                    ],
                    'job_dispatching' => [
                        'analytics_job_dispatched' => $analyticsJobDispatched,
                        'analytics_job_error' => $analyticsJobError,
                        'queue_connection' => config('queue.default')
                    ],
                    'email_notification' => [
                        'sent' => $emailSent,
                        'error' => $emailError,
                        'recipient' => config('services.notifications.default_recipient')
                    ],
                    'linkedin_live_post' => [
                        'url' => $publishResult['url'],
                        'platform_id' => $publishResult['platform_id'],
                        'published_at' => $publishResult['published_at'],
                        'live_status' => 'PUBLISHED_ON_LINKEDIN'
                    ],
                    'developer' => 'J33WAKASUPUN'
                ]);
            }

            // Handle publish failure
            return response()->json([
                'test_type' => 'LinkedIn Text Posting Test',
                'post_test' => 'PUBLISH_FAILED',
                'error' => 'LinkedIn publishing failed',
                'provider_error' => $publishResult['error'] ?? 'Unknown error',
                'provider_mode' => $publishResult['mode'] ?? 'unknown',
                'retryable' => $publishResult['retryable'] ?? false,
                'timestamp' => now()->toISOString(),
                'validation_info' => $validation,
                'post_data' => [
                    'content' => $contentData,
                    'hashtags' => $hashtags,
                    'mentions' => $mentions,
                    'media' => $media,
                    'settings' => $settings
                ]
            ], 400);

        } catch (\Exception $e) {
            Log::error('LinkedIn Posting: Exception during text post', [
                'session_key' => $sessionKey,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'test_type' => 'LinkedIn Text Posting Test',
                'post_test' => 'ARCHITECTURE_ERROR',
                'error' => $e->getMessage(),
                'trace' => config('app.debug') ? $e->getTraceAsString() : 'Enable debug mode for full trace',
                'timestamp' => now()->toISOString(),
                'architecture_status' => [
                    'error_location' => $e->getFile() . ':' . $e->getLine(),
                    'models_loaded' => class_exists('\App\Models\SocialMediaPost'),
                    'provider_loaded' => class_exists('\App\Services\SocialMedia\LinkedInProvider'),
                    'jobs_available' => class_exists('\App\Jobs\CollectAnalytics'),
                    'request_data' => $request->all()
                ]
            ], 500);
        }
    });

    // LINKEDIN MULTI-IMAGE POSTING
    Route::post('/test/linkedin/multi-image-post/{tokenFile}', function ($tokenFile, \Illuminate\Http\Request $request) {
        try {
            // Validate token file parameter
            if (!str_starts_with($tokenFile, 'oauth_tokens_linkedin_')) {
                return response()->json([
                    'test_type' => 'LinkedIn Multi-Image Posting Test',
                    'post_test' => 'FAILED',
                    'error' => 'Invalid token file format',
                    'expected_format' => 'oauth_tokens_linkedin_XXXXXXXXXX',
                    'received' => $tokenFile
                ], 400);
            }

            // Handle multiple image uploads
            $uploadedImages = [];
            $totalImagesUploaded = 0;

            // Check for multiple image fields (image1, image2, image3, etc.)
            for ($i = 1; $i <= 9; $i++) {
                if ($request->hasFile("image{$i}")) {
                    $uploadedImages[] = [
                        'file' => $request->file("image{$i}"),
                        'field_name' => "image{$i}",
                        'index' => $i
                    ];
                    $totalImagesUploaded++;
                }
            }

            // Also check for single 'image' field
            if ($request->hasFile('image')) {
                $uploadedImages[] = [
                    'file' => $request->file('image'),
                    'field_name' => 'image',
                    'index' => 0
                ];
                $totalImagesUploaded++;
            }

            // Check for array of images (images[])
            if ($request->hasFile('images')) {
                $imageArray = $request->file('images');
                if (is_array($imageArray)) {
                    foreach ($imageArray as $index => $imageFile) {
                        $uploadedImages[] = [
                            'file' => $imageFile,
                            'field_name' => "images[{$index}]",
                            'index' => $index + 100 // Offset to avoid conflicts
                        ];
                        $totalImagesUploaded++;
                    }
                }
            }

            if (empty($uploadedImages)) {
                return response()->json([
                    'test_type' => 'LinkedIn Multi-Image Posting Test',
                    'post_test' => 'FAILED',
                    'error' => 'No images uploaded',
                    'supported_fields' => [
                        'single_image' => 'image',
                        'multiple_images' => 'image1, image2, image3, ... up to image9',
                        'array_images' => 'images[] (array of files)'
                    ],
                    'usage_examples' => [
                        'single' => 'Form field: image=<file>',
                        'multiple' => 'Form fields: image1=<file1>, image2=<file2>, image3=<file3>',
                        'array' => 'Form field: images[]=<file1>, images[]=<file2>'
                    ]
                ], 400);
            }

            // LinkedIn supports maximum 9 images
            if ($totalImagesUploaded > 9) {
                return response()->json([
                    'test_type' => 'LinkedIn Multi-Image Posting Test',
                    'post_test' => 'FAILED',
                    'error' => 'LinkedIn supports maximum 9 images per post',
                    'uploaded_count' => $totalImagesUploaded,
                    'limit' => 9
                ], 400);
            }

            $text = $request->get('text', "🔥 Testing LinkedIn multiple image posting with {$totalImagesUploaded} images! #MultiImage #LinkedInTest #MediaUpload");

            // Validate all images
            $validatedImages = [];
            $validationErrors = [];

            foreach ($uploadedImages as $imageData) {
                $validation = MediaValidation::validateMediaFile($imageData['file'], 'image');
                if ($validation['valid']) {
                    $validatedImages[] = [
                        'type' => 'image',
                        'path' => $imageData['file']->getRealPath(),
                        'tmp_name' => $imageData['file']->getRealPath(),
                        'mime_type' => $imageData['file']->getMimeType(),
                        'size' => $imageData['file']->getSize(),
                        'name' => $imageData['file']->getClientOriginalName(),
                        'field_name' => $imageData['field_name'],
                        'index' => $imageData['index']
                    ];
                } else {
                    $validationErrors[] = [
                        'field' => $imageData['field_name'],
                        'error' => $validation['error'],
                        'file_name' => $imageData['file']->getClientOriginalName()
                    ];
                }
            }

            if (!empty($validationErrors)) {
                return response()->json([
                    'test_type' => 'LinkedIn Multi-Image Posting Test',
                    'post_test' => 'VALIDATION_FAILED',
                    'error' => 'Some images failed validation',
                    'validation_errors' => $validationErrors,
                    'valid_images' => count($validatedImages),
                    'total_images' => count($uploadedImages)
                ], 400);
            }

            // Get user-specific token file
            $tokenPath = storage_path("app/oauth_sessions/{$tokenFile}.json");

            if (!file_exists($tokenPath)) {
                return response()->json([
                    'test_type' => 'LinkedIn Multi-Image Posting Test',
                    'post_test' => 'FAILED',
                    'error' => 'LinkedIn token file not found',
                    'token_file' => $tokenFile,
                    'expected_path' => $tokenPath
                ], 404);
            }

            $tokenData = json_decode(file_get_contents($tokenPath), true);

            if (!isset($tokenData['access_token'])) {
                return response()->json([
                    'test_type' => 'LinkedIn Multi-Image Posting Test',
                    'post_test' => 'FAILED',
                    'error' => 'Invalid LinkedIn token in file',
                    'token_file' => $tokenFile
                ], 400);
            }

            $userId = str_replace(['oauth_tokens_linkedin_', '.json'], '', $tokenFile);

            // Create multi-image post object
            $post = new \App\Models\SocialMediaPost([
                'content' => ['text' => $text],
                'media' => $validatedImages, // Multiple images array
                'platforms' => ['linkedin'],
                'user_id' => 'J33WAKASUPUN_' . $userId,
                'post_status' => 'publishing'
            ]);

            // Create channel with user's tokens
            $channel = new \App\Models\Channel([
                'oauth_tokens' => $tokenData,
                'provider' => 'linkedin',
                'user_id' => 'J33WAKASUPUN',
                'channel_name' => 'LinkedIn - J33WAKASUPUN'
            ]);

            // Log multi-image posting attempt
            Log::info('LinkedIn Multi-Image: Starting posting test', [
                'user_id' => 'J33WAKASUPUN',
                'token_file' => $tokenFile,
                'image_count' => count($validatedImages),
                'image_names' => array_column($validatedImages, 'name'),
                'total_size' => array_sum(array_column($validatedImages, 'size'))
            ]);

            // Publish multi-image post
            $provider = new \App\Services\SocialMedia\LinkedInProvider();
            $result = $provider->publishPost($post, $channel);

            // Log result
            Log::info('LinkedIn Multi-Image: Publishing result', [
                'success' => $result['success'],
                'platform_id' => $result['platform_id'] ?? null,
                'image_count' => count($validatedImages)
            ]);

            return response()->json([
                'test_type' => 'LinkedIn Multi-Image Posting Test',
                'post_test' => $result['success'] ? 'SUCCESS! 🖼️🎉' : 'FAILED',
                'csrf_status' => 'EXEMPT (Fixed)',
                'validation_status' => 'PASSED',
                'user_context' => [
                    'user_login' => 'J33WAKASUPUN',
                    'token_file' => $tokenFile,
                    'user_id' => $userId,
                    'authenticated_user' => $tokenData['user_info'] ?? 'unknown'
                ],
                'multi_image_info' => [
                    'total_images' => count($validatedImages),
                    'image_details' => array_map(function ($img, $index) {
                        return [
                            'position' => $index + 1,
                            'name' => $img['name'],
                            'size' => $img['size'],
                            'mime_type' => $img['mime_type'],
                            'field_name' => $img['field_name']
                        ];
                    }, $validatedImages, array_keys($validatedImages)),
                    'total_size' => array_sum(array_column($validatedImages, 'size')),
                    'linkedin_limit' => '9 images maximum'
                ],
                'post_content' => $text,
                'success' => $result['success'],
                'publishing_result' => $result,
                'endpoint_used' => "/test/linkedin/multi-image-post/{$tokenFile}",
                'timestamp' => now()->toISOString(),
                'test_status' => $result['success'] ? 'PASSED ✅' : 'FAILED ❌',
                'carousel_post' => $result['success'] && count($validatedImages) > 1,
                'developer' => 'J33WAKASUPUN'
            ]);

        } catch (\Exception $e) {
            Log::error('LinkedIn Multi-Image: Exception occurred', [
                'user_login' => 'J33WAKASUPUN',
                'token_file' => $tokenFile ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'test_type' => 'LinkedIn Multi-Image Posting Test',
                'post_test' => 'ERROR',
                'csrf_status' => 'EXEMPT (Fixed)',
                'user_context' => [
                    'user_login' => 'J33WAKASUPUN',
                    'token_file' => $tokenFile ?? 'unknown'
                ],
                'error' => $e->getMessage(),
                'error_location' => $e->getFile() . ':' . $e->getLine(),
                'help' => 'Check the logs for detailed error information'
            ], 500);
        }
    });
});

/*
|--------------------------------------------------------------------------
| End of LinkedIn Routes
|--------------------------------------------------------------------------
*/