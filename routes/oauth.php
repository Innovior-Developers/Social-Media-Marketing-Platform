<?php
// routes/oauth.php - OAUTH & AUTHENTICATION ROUTES

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| OAuth & Authentication Routes
|--------------------------------------------------------------------------
|
| These routes handle OAuth flows and session management for all social
| media platforms. Currently focused on LinkedIn with infrastructure
| ready for Facebook, Twitter, Instagram, and other platforms.
|
| Developer: J33WAKASUPUN
| Last Updated: 2025-09-08 08:16:37 UTC
| Platforms: LinkedIn (active), Facebook (planned), Twitter (planned)
|
*/

// 🔗 GENERAL OAUTH CALLBACK HANDLER
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
                'common_errors' => [
                    'access_denied' => 'User denied permission or cancelled OAuth flow',
                    'invalid_request' => 'OAuth request parameters are invalid',
                    'unauthorized_client' => 'Client credentials are incorrect',
                    'unsupported_response_type' => 'OAuth configuration error'
                ],
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
                'debug_info' => [
                    'query_params' => $request->query(),
                    'expected' => 'code parameter from ' . ucfirst($provider)
                ],
                'developer' => 'J33WAKASUPUN'
            ], 400);
        }

        // Route to specific provider handler
        switch (strtolower($provider)) {
            case 'linkedin':
                return $this->handleLinkedInCallback($request, $code, $state);
                
            case 'facebook':
                return $this->handleFacebookCallback($request, $code, $state);
                
            case 'twitter':
                return $this->handleTwitterCallback($request, $code, $state);
                
            case 'instagram':
                return $this->handleInstagramCallback($request, $code, $state);
                
            default:
                Log::error('OAuth Callback: Unsupported provider', [
                    'provider' => $provider,
                    'supported_providers' => ['linkedin', 'facebook', 'twitter', 'instagram']
                ]);

                return response()->json([
                    'oauth_status' => 'FAILED',
                    'error' => "Provider '{$provider}' is not supported",
                    'supported_providers' => ['linkedin', 'facebook', 'twitter', 'instagram'],
                    'available_endpoints' => [
                        'linkedin' => '/oauth/callback/linkedin',
                        'facebook' => '/oauth/callback/facebook (coming soon)',
                        'twitter' => '/oauth/callback/twitter (coming soon)',
                        'instagram' => '/oauth/callback/instagram (coming soon)'
                    ],
                    'developer' => 'J33WAKASUPUN'
                ], 400);
        }

    } catch (\Exception $e) {
        Log::error('OAuth Callback: Exception occurred', [
            'provider' => $provider,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
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
})->where('provider', 'linkedin|facebook|twitter|instagram|youtube|tiktok');

// 📋 OAUTH TESTING AND MANAGEMENT ROUTES
Route::prefix('test/oauth')->group(function () {

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
                            'source' => 'file',
                            'file_path' => $file,
                            'file_size' => filesize($file)
                        ];
                    }
                }
            }

            // Combine and deduplicate sessions
            $allSessions = array_merge($sessions, $fileSessions);
            $uniqueSessions = [];
            $seenKeys = [];

            foreach ($allSessions as $session) {
                if (!in_array($session['session_key'], $seenKeys)) {
                    $uniqueSessions[] = $session;
                    $seenKeys[] = $session['session_key'];
                }
            }

            // Sort by creation time (newest first)
            usort($uniqueSessions, function ($a, $b) {
                $timeA = $a['created_at'] !== 'unknown' ? strtotime($a['created_at']) : 0;
                $timeB = $b['created_at'] !== 'unknown' ? strtotime($b['created_at']) : 0;
                return $timeB - $timeA;
            });

            // Calculate statistics
            $providerStats = [];
            $expiredCount = 0;
            $activeCount = 0;

            foreach ($uniqueSessions as $session) {
                $provider = $session['provider'];
                $providerStats[$provider] = ($providerStats[$provider] ?? 0) + 1;
                
                if ($session['is_expired'] === true) {
                    $expiredCount++;
                } elseif ($session['is_expired'] === false) {
                    $activeCount++;
                }
            }

            return response()->json([
                'test_type' => 'OAuth Sessions List',
                'sessions_status' => 'SUCCESS! 📋',
                'summary' => [
                    'total_sessions' => count($uniqueSessions),
                    'active_sessions' => $activeCount,
                    'expired_sessions' => $expiredCount,
                    'unknown_status' => count($uniqueSessions) - $activeCount - $expiredCount,
                    'providers' => $providerStats,
                    'storage_locations' => [
                        'session_storage' => count($sessions),
                        'file_storage' => count($fileSessions)
                    ]
                ],
                'active_sessions' => $uniqueSessions,
                'usage_instructions' => [
                    'linkedin_profile_test' => 'GET /test/linkedin/profile/{sessionKey}',
                    'linkedin_posting_test' => 'POST /test/linkedin/post/{sessionKey}',
                    'multi_image_posting' => 'POST /test/linkedin/multi-image-post/{sessionKey}',
                    'session_debugging' => 'GET /test/linkedin/debug-session/{sessionKey}'
                ],
                'session_management' => [
                    'latest_session' => !empty($uniqueSessions) ? $uniqueSessions[0]['session_key'] : null,
                    'recommended_session' => !empty($uniqueSessions) ? 
                        collect($uniqueSessions)->firstWhere('is_expired', false)['session_key'] ?? $uniqueSessions[0]['session_key'] 
                        : null
                ],
                'storage_info' => [
                    'session_directory' => $sessionDir,
                    'session_files_count' => count(glob($sessionDir . '/*.json')),
                    'cleanup_recommendation' => $expiredCount > 5 ? 'Consider cleaning up expired sessions' : 'No cleanup needed'
                ],
                'timestamp' => now()->toISOString(),
                'developer' => 'J33WAKASUPUN'
            ]);

        } catch (\Exception $e) {
            Log::error('OAuth Sessions: Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'test_type' => 'OAuth Sessions List',
                'sessions_status' => 'ERROR',
                'error' => $e->getMessage(),
                'exception_location' => $e->getFile() . ':' . $e->getLine()
            ], 500);
        }
    });

    // 🧹 CLEANUP EXPIRED OAUTH SESSIONS
    Route::delete('/sessions/cleanup', function () {
        try {
            $cleanedUp = [
                'session_storage' => 0,
                'file_storage' => 0,
                'total_cleaned' => 0
            ];

            // Clean up expired sessions from memory
            $sessionKeys = array_keys(session()->all());
            foreach ($sessionKeys as $key) {
                if (str_starts_with($key, 'oauth_tokens_')) {
                    $sessionData = session($key);
                    if (isset($sessionData['expires_at'])) {
                        $expiresAt = \Carbon\Carbon::parse($sessionData['expires_at']);
                        if ($expiresAt->isPast()) {
                            session()->forget($key);
                            $cleanedUp['session_storage']++;
                        }
                    }
                }
            }

            // Clean up expired sessions from file storage
            $sessionDir = storage_path('app/oauth_sessions');
            if (is_dir($sessionDir)) {
                $files = glob($sessionDir . '/oauth_tokens_*.json');
                foreach ($files as $file) {
                    $content = json_decode(file_get_contents($file), true);
                    if ($content && isset($content['expires_at'])) {
                        $expiresAt = \Carbon\Carbon::parse($content['expires_at']);
                        if ($expiresAt->isPast()) {
                            unlink($file);
                            $cleanedUp['file_storage']++;
                        }
                    }
                }
            }

            $cleanedUp['total_cleaned'] = $cleanedUp['session_storage'] + $cleanedUp['file_storage'];

            Log::info('OAuth Sessions: Cleanup completed', [
                'cleaned_up' => $cleanedUp,
                'performed_by' => 'J33WAKASUPUN'
            ]);

            return response()->json([
                'test_type' => 'OAuth Sessions Cleanup',
                'cleanup_status' => $cleanedUp['total_cleaned'] > 0 ? 'SUCCESS! 🧹' : 'NO_CLEANUP_NEEDED',
                'message' => $cleanedUp['total_cleaned'] > 0 ? 
                    "Cleaned up {$cleanedUp['total_cleaned']} expired OAuth sessions" : 
                    'No expired sessions found to clean up',
                'cleanup_summary' => $cleanedUp,
                'recommendation' => $cleanedUp['total_cleaned'] > 0 ? 
                    'Run this cleanup periodically to maintain session storage' : 
                    'Session storage is clean',
                'timestamp' => now()->toISOString(),
                'developer' => 'J33WAKASUPUN'
            ]);

        } catch (\Exception $e) {
            Log::error('OAuth Sessions Cleanup: Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'test_type' => 'OAuth Sessions Cleanup',
                'cleanup_status' => 'ERROR',
                'error' => $e->getMessage(),
                'exception_location' => $e->getFile() . ':' . $e->getLine()
            ], 500);
        }
    });

    // 📊 OAUTH STATISTICS AND INSIGHTS
    Route::get('/stats', function () {
        try {
            $stats = [
                'session_analysis' => [],
                'provider_breakdown' => [],
                'token_health' => [],
                'usage_patterns' => []
            ];

            // Collect all OAuth sessions
            $allSessions = [];

            // From session storage
            foreach (session()->all() as $key => $value) {
                if (str_starts_with($key, 'oauth_tokens_')) {
                    $allSessions[] = array_merge($value, ['session_key' => $key, 'source' => 'session']);
                }
            }

            // From file storage
            $sessionDir = storage_path('app/oauth_sessions');
            if (is_dir($sessionDir)) {
                $files = glob($sessionDir . '/oauth_tokens_*.json');
                foreach ($files as $file) {
                    $content = json_decode(file_get_contents($file), true);
                    if ($content) {
                        $allSessions[] = array_merge($content, [
                            'session_key' => basename($file, '.json'),
                            'source' => 'file'
                        ]);
                    }
                }
            }

            // Analyze sessions
            $providerCount = [];
            $expiredCount = 0;
            $activeCount = 0;
            $totalSessions = count($allSessions);

            foreach ($allSessions as $session) {
                $provider = $session['provider'] ?? 'unknown';
                $providerCount[$provider] = ($providerCount[$provider] ?? 0) + 1;

                if (isset($session['expires_at'])) {
                    $expiresAt = \Carbon\Carbon::parse($session['expires_at']);
                    if ($expiresAt->isPast()) {
                        $expiredCount++;
                    } else {
                        $activeCount++;
                    }
                }
            }

            $stats['session_analysis'] = [
                'total_sessions' => $totalSessions,
                'active_sessions' => $activeCount,
                'expired_sessions' => $expiredCount,
                'health_percentage' => $totalSessions > 0 ? round(($activeCount / $totalSessions) * 100, 1) : 0
            ];

            $stats['provider_breakdown'] = $providerCount;

            $stats['token_health'] = [
                'expiring_soon' => collect($allSessions)->filter(function ($session) {
                    if (!isset($session['expires_at'])) return false;
                    $expiresAt = \Carbon\Carbon::parse($session['expires_at']);
                    return !$expiresAt->isPast() && $expiresAt->diffInHours() < 24;
                })->count(),
                'long_term_valid' => collect($allSessions)->filter(function ($session) {
                    if (!isset($session['expires_at'])) return false;
                    $expiresAt = \Carbon\Carbon::parse($session['expires_at']);
                    return !$expiresAt->isPast() && $expiresAt->diffInDays() > 7;
                })->count()
            ];

            $stats['usage_patterns'] = [
                'linkedin_integration_ready' => isset($providerCount['linkedin']) && $providerCount['linkedin'] > 0,
                'multi_provider_setup' => count($providerCount) > 1,
                'facebook_integration_ready' => isset($providerCount['facebook']) && $providerCount['facebook'] > 0,
                'storage_distribution' => [
                    'session_count' => collect($allSessions)->where('source', 'session')->count(),
                    'file_count' => collect($allSessions)->where('source', 'file')->count()
                ]
            ];

            return response()->json([
                'test_type' => 'OAuth Statistics & Insights',
                'stats_status' => 'SUCCESS! 📊',
                'oauth_statistics' => $stats,
                'recommendations' => [
                    'cleanup_needed' => $expiredCount > 5 ? 'Run cleanup endpoint to remove expired sessions' : null,
                    'token_refresh' => $stats['token_health']['expiring_soon'] > 0 ? 'Some tokens expire within 24 hours' : null,
                    'integration_status' => $stats['usage_patterns']['linkedin_integration_ready'] ? 'LinkedIn ready for production use' : 'Set up LinkedIn OAuth first'
                ],
                'quick_actions' => [
                    'cleanup_expired' => 'DELETE /test/oauth/sessions/cleanup',
                    'view_sessions' => 'GET /test/oauth/sessions',
                    'linkedin_auth' => !$stats['usage_patterns']['linkedin_integration_ready'] ? 'Set up LinkedIn OAuth flow' : null
                ],
                'developer_notes' => [
                    'session_health' => $stats['session_analysis']['health_percentage'] > 80 ? 'Excellent' : 
                                      ($stats['session_analysis']['health_percentage'] > 50 ? 'Good' : 'Needs attention'),
                    'integration_progress' => $stats['usage_patterns']['linkedin_integration_ready'] ? 
                        'LinkedIn integration complete and tested' : 'LinkedIn integration in progress'
                ],
                'timestamp' => now()->toISOString(),
                'developer' => 'J33WAKASUPUN'
            ]);

        } catch (\Exception $e) {
            Log::error('OAuth Statistics: Exception occurred', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'test_type' => 'OAuth Statistics & Insights',
                'stats_status' => 'ERROR',
                'error' => $e->getMessage(),
                'exception_location' => $e->getFile() . ':' . $e->getLine()
            ], 500);
        }
    });
});

// 🔧 PROVIDER-SPECIFIC OAUTH HANDLERS (Private Methods - Implementation Template)
// Note: These would typically be implemented as private methods in a controller
// but are shown here as implementation guidance for future development

/*
|--------------------------------------------------------------------------
| Provider-Specific OAuth Handler Templates
|--------------------------------------------------------------------------
|
| Implementation templates for handling OAuth callbacks from different
| social media providers. Currently LinkedIn is fully implemented in
| routes/linkedin.php, these are templates for future providers.
|
*/

/**
 * Handle LinkedIn OAuth Callback
 * Note: Full implementation is in routes/linkedin.php
 */
function handleLinkedInCallback($request, $code, $state) {
    // This functionality is implemented in routes/linkedin.php
    // /oauth/callback/linkedin route handles LinkedIn OAuth
    return redirect('/test/linkedin/profile/' . session()->getId());
}

/**
 * Handle Facebook OAuth Callback (Template for future implementation)
 */
function handleFacebookCallback($request, $code, $state) {
    // Template for Facebook OAuth implementation
    return response()->json([
        'oauth_status' => 'COMING_SOON',
        'provider' => 'facebook',
        'message' => 'Facebook OAuth integration is planned for future release',
        'current_status' => 'LinkedIn OAuth is fully functional',
        'redirect_to' => '/test/oauth/sessions',
        'developer' => 'J33WAKASUPUN'
    ]);
}

/**
 * Handle Twitter OAuth Callback (Template for future implementation)
 */
function handleTwitterCallback($request, $code, $state) {
    // Template for Twitter OAuth implementation
    return response()->json([
        'oauth_status' => 'COMING_SOON',
        'provider' => 'twitter',
        'message' => 'Twitter OAuth integration is planned for future release',
        'current_status' => 'LinkedIn OAuth is fully functional',
        'redirect_to' => '/test/oauth/sessions',
        'developer' => 'J33WAKASUPUN'
    ]);
}

/**
 * Handle Instagram OAuth Callback (Template for future implementation)
 */
function handleInstagramCallback($request, $code, $state) {
    // Template for Instagram OAuth implementation
    return response()->json([
        'oauth_status' => 'COMING_SOON',
        'provider' => 'instagram',
        'message' => 'Instagram OAuth integration is planned for future release',
        'current_status' => 'LinkedIn OAuth is fully functional',
        'redirect_to' => '/test/oauth/sessions',
        'developer' => 'J33WAKASUPUN'
    ]);
}

/*
|--------------------------------------------------------------------------
| End of OAuth Routes
|--------------------------------------------------------------------------
*/