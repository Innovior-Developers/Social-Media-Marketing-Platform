<?php
// routes/oauth.php - FIXED OAUTH & AUTHENTICATION ROUTES

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| OAuth & Authentication Routes - FIXED VERSION
|--------------------------------------------------------------------------
|
| These routes handle OAuth flows and session management for all social
| media platforms. LinkedIn is handled in linkedin.php, this handles
| the general OAuth infrastructure and Facebook implementation.
|
| Developer: J33WAKASUPUN
| Last Updated: 2025-01-08 16:02:15 UTC
| Platforms: LinkedIn (routes/linkedin.php), Facebook (implemented here)
|
*/

// 🔗 GENERAL OAUTH CALLBACK HANDLER - FIXED
Route::get('/oauth/callback/{provider}', function ($provider, \Illuminate\Http\Request $request) {
    try {
        $code = $request->get('code');
        $error = $request->get('error');
        $state = $request->get('state');
        $errorDescription = $request->get('error_description');

        // Log OAuth callback attempt
        Log::info('OAuth Callback: Processing request', [
            'provider' => $provider,
            'has_code' => !empty($code),
            'has_error' => !empty($error),
            'state' => $state,
            'user_agent' => $request->userAgent(),
            'ip_address' => $request->ip()
        ]);

        // Handle OAuth errors
        if ($error) {
            Log::warning('OAuth Callback: Error received from provider', [
                'provider' => $provider,
                'error' => $error,
                'error_description' => $errorDescription,
                'state' => $state
            ]);

            return response()->json([
                'oauth_status' => 'FAILED',
                'provider' => $provider,
                'error' => $error,
                'error_description' => $errorDescription,
                'state' => $state,
                'timestamp' => now()->toISOString(),
                'developer' => 'J33WAKASUPUN'
            ], 400);
        }

        // Validate authorization code
        if (!$code) {
            Log::error('OAuth Callback: No authorization code received', [
                'provider' => $provider,
                'query_params' => $request->query(),
                'state' => $state
            ]);

            return response()->json([
                'oauth_status' => 'FAILED',
                'provider' => $provider,
                'error' => 'No authorization code received',
                'expected_parameter' => 'code',
                'received_parameters' => array_keys($request->query()),
                'developer' => 'J33WAKASUPUN'
            ], 400);
        }

        // Route to specific provider - FIXED
        switch (strtolower($provider)) {
            case 'linkedin':
                // LinkedIn is handled by dedicated route in linkedin.php
                // This should redirect there or return info
                Log::info('OAuth: LinkedIn callback received - handled by linkedin.php');
                
                return response()->json([
                    'oauth_status' => 'REDIRECTED',
                    'provider' => 'linkedin',
                    'message' => 'LinkedIn OAuth is handled by dedicated route',
                    'actual_handler' => 'routes/linkedin.php',
                    'note' => 'This callback should not reach here - linkedin.php has priority',
                    'check_sessions' => '/test/oauth/sessions',
                    'developer' => 'J33WAKASUPUN'
                ]);
                
            case 'facebook':
                return handleFacebookOAuth($request, $code, $state);
                
            case 'twitter':
                return response()->json([
                    'oauth_status' => 'COMING_SOON',
                    'provider' => 'twitter',
                    'message' => 'Twitter OAuth integration is planned for future release',
                    'current_status' => 'LinkedIn ✅ | Facebook ✅ | Twitter ⏳',
                    'developer' => 'J33WAKASUPUN'
                ]);
                
            case 'instagram':
                return response()->json([
                    'oauth_status' => 'COMING_SOON',
                    'provider' => 'instagram',
                    'message' => 'Instagram OAuth integration is planned for future release',
                    'current_status' => 'LinkedIn ✅ | Facebook ✅ | Instagram ⏳',
                    'developer' => 'J33WAKASUPUN'
                ]);
                
            default:
                Log::error('OAuth Callback: Unsupported provider', [
                    'provider' => $provider,
                    'supported_providers' => ['linkedin', 'facebook', 'twitter', 'instagram']
                ]);

                return response()->json([
                    'oauth_status' => 'FAILED',
                    'error' => "Provider '{$provider}' is not supported",
                    'supported_providers' => ['linkedin', 'facebook', 'twitter', 'instagram'],
                    'implementation_status' => [
                        'linkedin' => '✅ Fully operational (routes/linkedin.php)',
                        'facebook' => '🔥 Implemented in this file',
                        'twitter' => '⏳ Coming soon',
                        'instagram' => '⏳ Coming soon'
                    ],
                    'developer' => 'J33WAKASUPUN'
                ], 400);
        }

    } catch (\Exception $e) {
        Log::error('OAuth Callback: Exception occurred', [
            'provider' => $provider,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);

        return response()->json([
            'oauth_status' => 'FAILED',
            'provider' => $provider,
            'error' => 'OAuth callback exception: ' . $e->getMessage(),
            'exception_location' => $e->getFile() . ':' . $e->getLine(),
            'timestamp' => now()->toISOString(),
            'developer' => 'J33WAKASUPUN'
        ], 500);
    }
})->where('provider', 'facebook|twitter|instagram|youtube|tiktok'); // 🔥 REMOVED linkedin to avoid conflict

// 🔥 FACEBOOK OAUTH HANDLER FUNCTION
function handleFacebookOAuth($request, $code, $state) {
    try {
        Log::info('Facebook OAuth: Processing callback', [
            'code_length' => strlen($code),
            'state' => $state
        ]);

        // Use FacebookProvider to exchange code for tokens
        $provider = new \App\Services\SocialMedia\FacebookProvider();

        if (!$provider->isConfigured()) {
            return response()->json([
                'oauth_status' => 'FAILED',
                'provider' => 'facebook',
                'error' => 'Facebook provider not configured',
                'required_config' => [
                    'FACEBOOK_CLIENT_ID' => 'Facebook App ID',
                    'FACEBOOK_CLIENT_SECRET' => 'Facebook App Secret',
                    'FACEBOOK_REDIRECT_URI' => 'OAuth redirect URI'
                ],
                'developer' => 'J33WAKASUPUN'
            ], 500);
        }

        if ($provider->isStubMode()) {
            // Handle stub mode
            $sessionKey = "oauth_tokens_facebook_" . time();
            $tokens = [
                'access_token' => 'facebook_stub_token_' . uniqid(),
                'expires_at' => now()->addDays(60)->toISOString(),
                'token_type' => 'Bearer',
                'scope' => ['pages_manage_posts', 'pages_read_engagement', 'pages_show_list'],
                'provider' => 'facebook',
                'created_at' => now()->toISOString(),
                'state' => $state,
                'mode' => 'stub'
            ];

            // Store tokens
            session([$sessionKey => $tokens]);
            
            $sessionFile = storage_path("app/oauth_sessions/{$sessionKey}.json");
            if (!is_dir(dirname($sessionFile))) {
                mkdir(dirname($sessionFile), 0755, true);
            }
            file_put_contents($sessionFile, json_encode($tokens, JSON_PRETTY_PRINT));

            return response()->json([
                'oauth_status' => 'SUCCESS! 🎉 (STUB MODE)',
                'provider' => 'facebook',
                'message' => 'Facebook OAuth completed in stub mode',
                'session_key' => $sessionKey,
                'mode' => 'stub',
                'tokens_received' => [
                    'access_token_preview' => substr($tokens['access_token'], 0, 20) . '...',
                    'expires_at' => $tokens['expires_at'],
                    'scopes_granted' => $tokens['scope']
                ],
                'next_steps' => [
                    'test_posting' => "POST /test/facebook/posts/publish-test",
                    'view_sessions' => "GET /test/oauth/sessions"
                ],
                'developer' => 'J33WAKASUPUN'
            ]);
        }

        // Handle real mode
        $tokens = $provider->exchangeCodeForTokens($code);
        $sessionKey = "oauth_tokens_facebook_" . time();

        $tokenData = [
            'access_token' => $tokens['access_token'],
            'expires_at' => $tokens['expires_at'],
            'token_type' => $tokens['token_type'] ?? 'Bearer',
            'scope' => $tokens['scope'] ?? $provider->getDefaultScopes(),
            'provider' => 'facebook',
            'created_at' => now()->toISOString(),
            'state' => $state,
            'mode' => 'real'
        ];

        // Store tokens
        session([$sessionKey => $tokenData]);
        
        $sessionFile = storage_path("app/oauth_sessions/{$sessionKey}.json");
        if (!is_dir(dirname($sessionFile))) {
            mkdir(dirname($sessionFile), 0755, true);
        }
        file_put_contents($sessionFile, json_encode($tokenData, JSON_PRETTY_PRINT));

        Log::info('Facebook OAuth: Success', [
            'session_key' => $sessionKey,
            'expires_at' => $tokenData['expires_at']
        ]);

        return response()->json([
            'oauth_status' => 'SUCCESS! 🎉',
            'provider' => 'facebook',
            'message' => 'Facebook OAuth completed successfully!',
            'session_key' => $sessionKey,
            'mode' => 'real',
            'tokens_received' => [
                'access_token_preview' => substr($tokenData['access_token'], 0, 20) . '...',
                'expires_at' => $tokenData['expires_at'],
                'token_type' => $tokenData['token_type'],
                'scopes_granted' => $tokenData['scope']
            ],
            'storage_status' => [
                'session_stored' => session()->has($sessionKey),
                'file_stored' => file_exists($sessionFile)
            ],
            'next_steps' => [
                'test_posting' => "POST /test/facebook/posts/publish-test",
                'get_pages' => "GET /test/facebook/pages/test",
                'view_sessions' => "GET /test/oauth/sessions",
                'comprehensive_test' => "GET /test/facebook/comprehensive"
            ],
            'timestamp' => now()->toISOString(),
            'developer' => 'J33WAKASUPUN'
        ]);

    } catch (\Exception $e) {
        Log::error('Facebook OAuth: Exception', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'oauth_status' => 'FAILED',
            'provider' => 'facebook',
            'error' => 'Facebook OAuth exception: ' . $e->getMessage(),
            'debug_info' => [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ],
            'developer' => 'J33WAKASUPUN'
        ], 500);
    }
}

// 📋 OAUTH TESTING AND MANAGEMENT ROUTES
Route::prefix('test/oauth')->group(function () {
    
    // All your existing /test/oauth/* routes stay the same...
    // (keeping the sessions, cleanup, stats routes you already have)
    
    // 📋 LIST ALL ACTIVE OAUTH SESSIONS
    Route::get('/sessions', function () {
        try {
            $sessions = [];
            $fileSessions = [];

            // Get OAuth sessions from memory
            foreach (session()->all() as $key => $value) {
                if (str_starts_with($key, 'oauth_tokens_')) {
                    $provider = explode('_', $key)[2] ?? 'unknown';
                    $expiresAt = isset($value['expires_at']) ? \Carbon\Carbon::parse($value['expires_at']) : null;
                    
                    $sessions[] = [
                        'session_key' => $key,
                        'provider' => $provider,
                        'created_at' => $value['created_at'] ?? 'unknown',
                        'expires_at' => $value['expires_at'] ?? 'unknown',
                        'is_expired' => $expiresAt ? $expiresAt->isPast() : 'unknown',
                        'time_until_expiry' => $expiresAt ? ($expiresAt->isPast() ? 'EXPIRED' : $expiresAt->diffForHumans()) : 'unknown',
                        'has_access_token' => !empty($value['access_token']),
                        'scopes' => $value['scope'] ?? [],
                        'token_type' => $value['token_type'] ?? 'unknown',
                        'mode' => $value['mode'] ?? 'unknown',
                        'source' => 'session'
                    ];
                }
            }

            // Get OAuth sessions from file storage
            $sessionDir = storage_path('app/oauth_sessions');
            if (is_dir($sessionDir)) {
                $files = glob($sessionDir . '/oauth_tokens_*.json');
                foreach ($files as $file) {
                    $key = basename($file, '.json');
                    $content = json_decode(file_get_contents($file), true);
                    
                    if ($content && isset($content['provider'])) {
                        $expiresAt = isset($content['expires_at']) ? \Carbon\Carbon::parse($content['expires_at']) : null;
                        
                        $fileSessions[] = [
                            'session_key' => $key,
                            'provider' => $content['provider'],
                            'created_at' => $content['created_at'] ?? 'unknown',
                            'expires_at' => $content['expires_at'] ?? 'unknown',
                            'is_expired' => $expiresAt ? $expiresAt->isPast() : 'unknown',
                            'time_until_expiry' => $expiresAt ? ($expiresAt->isPast() ? 'EXPIRED' : $expiresAt->diffForHumans()) : 'unknown',
                            'has_access_token' => !empty($content['access_token']),
                            'scopes' => $content['scope'] ?? [],
                            'token_type' => $content['token_type'] ?? 'unknown',
                            'mode' => $content['mode'] ?? 'unknown',
                            'source' => 'file',
                            'file_path' => $file,
                            'file_size' => filesize($file)
                        ];
                    }
                }
            }

            // Combine sessions
            $allSessions = array_merge($sessions, $fileSessions);
            
            return response()->json([
                'test_type' => 'OAuth Sessions List',
                'sessions_status' => 'SUCCESS! 📋',
                'summary' => [
                    'total_sessions' => count($allSessions),
                    'linkedin_sessions' => count(array_filter($allSessions, fn($s) => $s['provider'] === 'linkedin')),
                    'facebook_sessions' => count(array_filter($allSessions, fn($s) => $s['provider'] === 'facebook'))
                ],
                'active_sessions' => $allSessions,
                'implementation_status' => [
                    'linkedin' => '✅ Fully operational',
                    'facebook' => '🔥 OAuth implemented, ready for testing',
                    'twitter' => '⏳ Coming soon',
                    'instagram' => '⏳ Coming soon'
                ],
                'usage_instructions' => [
                    'linkedin_posting' => 'POST /test/linkedin/post/{sessionKey}',
                    'facebook_posting' => 'POST /test/facebook/posts/publish-test',
                    'facebook_oauth' => 'GET /test/facebook/oauth/url'
                ],
                'timestamp' => now()->toISOString(),
                'developer' => 'J33WAKASUPUN'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'test_type' => 'OAuth Sessions List',
                'sessions_status' => 'ERROR',
                'error' => $e->getMessage()
            ], 500);
        }
    });
});

/*
|--------------------------------------------------------------------------
| End of OAuth Routes
|--------------------------------------------------------------------------
*/