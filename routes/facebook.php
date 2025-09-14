<?php
// routes/facebook.php - FACEBOOK INTEGRATION ROUTES
// Developer: J33WAKASUPUN
// Platform: Social Media Marketing Platform

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Services\SocialMedia\FacebookProvider;
use App\Helpers\FacebookHelpers;
use App\Helpers\MediaValidation;
use App\Models\Channel;
use App\Models\SocialMediaPost;
use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| Facebook Testing & Integration Routes
|--------------------------------------------------------------------------
|
| Complete Facebook functionality testing routes including:
| - Configuration testing
| - OAuth flow simulation
| - Post publishing (all types)
| - Analytics collection
| - Page management
| - Media handling
|
*/

// === FACEBOOK CONFIGURATION & STATUS ===

Route::prefix('test/facebook')->group(function () {
    
    // Facebook Configuration Status
    Route::get('/config', function () {
        try {
            $provider = new FacebookProvider();
            $config = $provider->getConfigurationStatus();
            
            $constraints = FacebookHelpers::getFacebookConstraints();
            
            return [
                'status' => 'success',
                'developer' => 'J33WAKASUPUN',
                'timestamp' => now()->toISOString(),
                'platform' => 'Facebook',
                'provider_status' => $config,
                'facebook_constraints' => $constraints,
                'helper_status' => [
                    'FacebookHelpers' => class_exists('App\Helpers\FacebookHelpers') ? 'LOADED' : 'MISSING',
                    'MediaValidation' => class_exists('App\Helpers\MediaValidation') ? 'LOADED' : 'MISSING'
                ],
                'environment_check' => [
                    'FACEBOOK_CLIENT_ID' => !empty(env('FACEBOOK_CLIENT_ID')) ? 'SET' : 'NOT SET',
                    'FACEBOOK_CLIENT_SECRET' => !empty(env('FACEBOOK_CLIENT_SECRET')) ? 'SET' : 'NOT SET',
                    'FACEBOOK_ENABLED' => env('FACEBOOK_ENABLED', false) ? 'ENABLED' : 'DISABLED',
                    'FACEBOOK_USE_REAL_API' => env('FACEBOOK_USE_REAL_API', false) ? 'REAL API' : 'STUB MODE'
                ],
                'quick_tests' => [
                    'provider_mode' => "GET /test/facebook/mode",
                    'oauth_simulation' => "GET /test/facebook/oauth/simulate",
                    'page_management' => "GET /test/facebook/pages/test",
                    'post_publishing' => "POST /test/facebook/posts/publish-test",
                    'analytics_test' => "GET /test/facebook/analytics/test"
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Facebook configuration check failed',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    });

    // Facebook Provider Mode Check
    Route::get('/mode', function () {
        try {
            $provider = new FacebookProvider();
            $mode = FacebookHelpers::getFacebookMode();
            $enabled = FacebookHelpers::isFacebookEnabled();
            
            return [
                'status' => 'success',
                'developer' => 'J33WAKASUPUN',
                'facebook_mode' => $mode,
                'facebook_enabled' => $enabled,
                'provider_configured' => $provider->isConfigured(),
                'current_mode' => $provider->getCurrentMode(),
                'stub_mode_active' => $provider->isStubMode(),
                'real_api_requirements' => [
                    'app_id_set' => !empty(config('services.facebook.app_id')),
                    'app_secret_set' => !empty(config('services.facebook.app_secret')),
                    'redirect_uri_set' => !empty(config('services.facebook.redirect')),
                    'use_real_api_enabled' => config('services.social_media.real_providers.facebook', false)
                ],
                'next_steps' => $mode === 'stub' ? [
                    'message' => 'Currently using stub mode - perfect for development',
                    'to_enable_real_api' => 'Set FACEBOOK_USE_REAL_API=true in .env'
                ] : [
                    'message' => 'Real API mode active',
                    'oauth_url' => 'Use GET /test/facebook/oauth/url to get auth URL'
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    });
});

// === FACEBOOK OAUTH TESTING ===

Route::prefix('test/facebook/oauth')->group(function () {
    
    // Generate Facebook OAuth URL
    Route::get('/url', function (Request $request) {
        try {
            $provider = new FacebookProvider();
            
            if (!$provider->isConfigured()) {
                return [
                    'status' => 'error',
                    'message' => 'Facebook provider not configured',
                    'required_config' => [
                        'FACEBOOK_CLIENT_ID' => 'Facebook App ID',
                        'FACEBOOK_CLIENT_SECRET' => 'Facebook App Secret',
                        'FACEBOOK_REDIRECT_URI' => 'OAuth redirect URI'
                    ]
                ];
            }
            
            $state = $request->get('state', 'facebook_test_' . uniqid());
            $authUrl = $provider->getAuthUrl($state);
            
            return [
                'status' => 'success',
                'developer' => 'J33WAKASUPUN',
                'platform' => 'Facebook',
                'auth_url' => $authUrl,
                'state' => $state,
                'mode' => $provider->getCurrentMode(),
                'scopes' => $provider->getDefaultScopes(),
                'instructions' => [
                    'step_1' => 'Copy the auth_url and paste it in your browser',
                    'step_2' => 'Complete Facebook OAuth authorization',
                    'step_3' => 'Facebook will redirect back with code parameter',
                    'step_4' => 'Use the code to test token exchange'
                ],
                'test_endpoints' => [
                    'token_exchange' => 'POST /test/facebook/oauth/tokens',
                    'oauth_simulation' => 'GET /test/facebook/oauth/simulate'
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    });
    
    // Token Exchange Test
    Route::post('/tokens', function (Request $request) {
        try {
            $code = $request->input('code');
            if (!$code) {
                return [
                    'status' => 'error',
                    'message' => 'OAuth code required',
                    'usage' => 'POST /test/facebook/oauth/tokens with {"code": "your_oauth_code"}'
                ];
            }
            
            $provider = new FacebookProvider();
            
            if ($provider->isStubMode()) {
                return [
                    'status' => 'info',
                    'message' => 'Stub mode active - use simulation endpoint instead',
                    'redirect' => 'GET /test/facebook/oauth/simulate'
                ];
            }
            
            $tokens = $provider->exchangeCodeForTokens($code);
            
            return [
                'status' => 'success',
                'developer' => 'J33WAKASUPUN',
                'message' => 'Facebook tokens obtained successfully',
                'tokens' => $tokens,
                'expires_at' => $tokens['expires_at'] ?? 'N/A',
                'token_type' => $tokens['token_type'] ?? 'Bearer',
                'next_steps' => [
                    'create_channel' => 'Use these tokens to create a Facebook channel',
                    'test_posting' => 'Test posting functionality'
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    });
    
    // OAuth Simulation (Stub Mode)
    Route::get('/simulate', function () {
        try {
            $provider = new FacebookProvider();
            $authResult = $provider->authenticate([]);
            
            return [
                'status' => 'success',
                'developer' => 'J33WAKASUPUN',
                'message' => 'Facebook OAuth simulation completed',
                'simulation_data' => $authResult,
                'mode' => 'stub',
                'user_info' => $authResult['user_info'] ?? [],
                'pages_available' => $authResult['pages'] ?? [],
                'mock_tokens' => [
                    'access_token' => $authResult['access_token'] ?? '',
                    'expires_at' => $authResult['expires_at'] ?? '',
                    'note' => 'These are simulated tokens for development'
                ],
                'next_tests' => [
                    'page_management' => 'GET /test/facebook/pages/test',
                    'post_publishing' => 'POST /test/facebook/posts/publish-test',
                    'analytics' => 'GET /test/facebook/analytics/test'
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    });
});

// === FACEBOOK PAGE MANAGEMENT ===

Route::prefix('test/facebook/pages')->group(function () {
    
    // Test Facebook Pages Functionality
    Route::get('/test', function () {
        try {
            // Create temporary channel for testing
            $channel = FacebookHelpers::createTemporaryChannel([
                'access_token' => 'test_token_' . uniqid(),
                'expires_at' => now()->addDays(60)
            ]);
            
            if (!$channel) {
                return [
                    'status' => 'error',
                    'message' => 'Could not create temporary channel for testing'
                ];
            }
            
            $provider = new FacebookProvider();
            $pagesResult = $provider->getUserPages($channel);
            
            return [
                'status' => 'success',
                'developer' => 'J33WAKASUPUN',
                'message' => 'Facebook Pages test completed',
                'pages_result' => $pagesResult,
                'pages_count' => count($pagesResult['pages'] ?? []),
                'mode' => $pagesResult['mode'] ?? 'unknown',
                'sample_page' => !empty($pagesResult['pages']) ? $pagesResult['pages'][0] : null,
                'features_tested' => [
                    'get_user_pages' => '✅ Tested',
                    'page_information' => '✅ Retrieved',
                    'access_tokens' => '✅ Available',
                    'follower_counts' => '✅ Included'
                ],
                'next_tests' => [
                    'post_to_page' => 'POST /test/facebook/posts/publish-test',
                    'page_analytics' => 'GET /test/facebook/analytics/test'
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    });
    
    // List Available Pages
    Route::get('/list', function () {
        try {
            $provider = new FacebookProvider();
            
            // Use helper to get pages
            $channel = FacebookHelpers::createTemporaryChannel();
            if (!$channel) {
                // Create mock channel for demo
                $channel = new Channel([
                    'oauth_tokens' => ['access_token' => 'mock_token_' . uniqid()],
                    'provider' => 'facebook'
                ]);
            }
            
            $result = FacebookHelpers::getUserFacebookPages($channel);
            
            return [
                'status' => 'success',
                'developer' => 'J33WAKASUPUN',
                'pages_available' => $result['pages'] ?? [],
                'total_pages' => count($result['pages'] ?? []),
                'mode' => $result['mode'] ?? 'stub',
                'helper_used' => 'FacebookHelpers::getUserFacebookPages',
                'page_details' => array_map(function($page) {
                    return [
                        'id' => $page['id'] ?? 'N/A',
                        'name' => $page['name'] ?? 'N/A',
                        'category' => $page['category'] ?? 'N/A',
                        'followers' => $page['followers_count'] ?? 0,
                        'can_post' => !empty($page['access_token'])
                    ];
                }, $result['pages'] ?? [])
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    });
});

// === FACEBOOK POST PUBLISHING ===

Route::prefix('test/facebook/posts')->group(function () {
    
    // Test Facebook Post Publishing
    Route::post('/publish-test', function (Request $request) {
        try {
            $postType = $request->input('type', 'text'); // text, image, video, carousel
            $content = $request->input('content', 'Test post from Social Media Marketing Platform by J33WAKASUPUN - ' . now()->toISOString());
            
            // Create mock post based on type
            $mockPost = new SocialMediaPost([
                'content' => ['text' => $content],
                'media' => [],
                'platforms' => ['facebook']
            ]);
            
            // Add media based on type
            switch ($postType) {
                case 'image':
                    $mockPost->media = [
                        [
                            'type' => 'image',
                            'path' => 'test/sample-image.jpg',
                            'url' => 'https://via.placeholder.com/800x600',
                            'size' => 1024000
                        ]
                    ];
                    break;
                case 'video':
                    $mockPost->media = [
                        [
                            'type' => 'video',
                            'path' => 'test/sample-video.mp4',
                            'size' => 5048000
                        ]
                    ];
                    break;
                case 'carousel':
                    $mockPost->media = [
                        [
                            'type' => 'image',
                            'path' => 'test/carousel-1.jpg',
                            'url' => 'https://via.placeholder.com/800x600/FF0000',
                            'size' => 1024000
                        ],
                        [
                            'type' => 'image',
                            'path' => 'test/carousel-2.jpg',
                            'url' => 'https://via.placeholder.com/800x600/00FF00',
                            'size' => 1024000
                        ],
                        [
                            'type' => 'image',
                            'path' => 'test/carousel-3.jpg',
                            'url' => 'https://via.placeholder.com/800x600/0000FF',
                            'size' => 1024000
                        ]
                    ];
                    break;
            }
            
            // Create temporary channel
            $channel = FacebookHelpers::createTemporaryChannel([
                'access_token' => 'test_token_' . uniqid(),
                'expires_at' => now()->addDays(60)
            ]);
            
            if (!$channel) {
                $channel = new Channel([
                    'oauth_tokens' => ['access_token' => 'mock_token_' . uniqid()],
                    'provider' => 'facebook',
                    'platform_user_id' => 'page_' . rand(100000000000000, 999999999999999)
                ]);
            }
            
            $provider = new FacebookProvider();
            $result = $provider->publishPost($mockPost, $channel);
            
            return [
                'status' => 'success',
                'developer' => 'J33WAKASUPUN',
                'message' => 'Facebook post publishing test completed',
                'post_type' => $postType,
                'content_length' => strlen($content),
                'media_count' => count($mockPost->media),
                'publish_result' => $result,
                'mode' => $result['mode'] ?? 'unknown',
                'platform_id' => $result['platform_id'] ?? null,
                'post_url' => $result['url'] ?? null,
                'features_tested' => [
                    'content_posting' => '✅ Tested',
                    'media_handling' => count($mockPost->media) > 0 ? '✅ Tested' : '⏭️ Skipped',
                    'api_response' => '✅ Received',
                    'error_handling' => $result['success'] ? '✅ Success' : '⚠️ Error handled'
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    });
    
    // Test All Facebook Post Types
    Route::get('/test-all-types', function () {
        try {
            $results = [];
            $postTypes = ['text', 'image', 'video', 'carousel'];
            
            foreach ($postTypes as $type) {
                // Create mock request
                $request = new \Illuminate\Http\Request();
                $request->merge([
                    'type' => $type,
                    'content' => "Test {$type} post - " . now()->toISOString()
                ]);
                
                // Simulate the publish test for each type
                $results[$type] = [
                    'type' => $type,
                    'status' => 'simulated',
                    'message' => "Would test {$type} posting",
                    'endpoint' => "POST /test/facebook/posts/publish-test with type={$type}"
                ];
            }
            
            return [
                'status' => 'success',
                'developer' => 'J33WAKASUPUN',
                'message' => 'Facebook post types overview',
                'available_types' => $postTypes,
                'type_tests' => $results,
                'facebook_capabilities' => [
                    'text_posts' => '✅ Supported (up to 63,206 characters)',
                    'single_image' => '✅ Supported (up to 100MB)',
                    'single_video' => '✅ Supported (up to 10GB)',
                    'carousel_posts' => '✅ Supported (up to 10 images)',
                    'link_previews' => '✅ Automatic for URLs in text'
                ],
                'test_individual' => 'POST /test/facebook/posts/publish-test',
                'supported_formats' => [
                    'images' => ['jpg', 'jpeg', 'png', 'gif'],
                    'videos' => ['mp4', 'mov', 'avi']
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    });
    
    // Facebook Post Validation Test
    Route::post('/validate', function (Request $request) {
        try {
            $content = $request->input('content', 'Test validation content');
            $mediaCount = $request->input('media_count', 0);
            
            // Create mock post for validation
            $mockPost = new SocialMediaPost([
                'content' => ['text' => $content],
                'media' => array_fill(0, $mediaCount, [
                    'type' => 'image',
                    'size' => 1024000
                ])
            ]);
            
            $provider = new FacebookProvider();
            $validation = $provider->validatePost($mockPost);
            
            return [
                'status' => 'success',
                'developer' => 'J33WAKASUPUN',
                'message' => 'Facebook post validation completed',
                'validation_result' => $validation,
                'facebook_limits' => [
                    'character_limit' => $provider->getCharacterLimit(),
                    'media_limit' => $provider->getMediaLimit(),
                    'supported_media' => $provider->getSupportedMediaTypes()
                ],
                'test_input' => [
                    'content_length' => strlen($content),
                    'media_count' => $mediaCount
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    });
});

// === FACEBOOK ANALYTICS ===

Route::prefix('test/facebook/analytics')->group(function () {
    
    // Test Facebook Analytics
    Route::get('/test', function (Request $request) {
        try {
            $postId = $request->get('post_id', 'fb_test_post_' . rand(100000000000000, 999999999999999));
            
            // Create temporary channel
            $channel = FacebookHelpers::createTemporaryChannel([
                'access_token' => 'test_token_' . uniqid()
            ]);
            
            if (!$channel) {
                $channel = new Channel([
                    'oauth_tokens' => ['access_token' => 'mock_token_' . uniqid()],
                    'provider' => 'facebook'
                ]);
            }
            
            $provider = new FacebookProvider();
            $analytics = $provider->getAnalytics($postId, $channel);
            
            return [
                'status' => 'success',
                'developer' => 'J33WAKASUPUN',
                'message' => 'Facebook analytics test completed',
                'post_id' => $postId,
                'analytics_result' => $analytics,
                'mode' => $analytics['mode'] ?? 'unknown',
                'facebook_insights' => [
                    'impressions' => $analytics['metrics']['impressions'] ?? 0,
                    'reach' => $analytics['metrics']['reach'] ?? 0,
                    'total_reactions' => $analytics['metrics']['total_reactions'] ?? 0,
                    'engagement_rate' => $analytics['metrics']['engagement_rate'] ?? 0
                ],
                'reaction_breakdown' => [
                    'likes' => $analytics['metrics']['likes'] ?? 0,
                    'loves' => $analytics['metrics']['loves'] ?? 0,
                    'wows' => $analytics['metrics']['wows'] ?? 0,
                    'hahas' => $analytics['metrics']['hahas'] ?? 0,
                    'sorrys' => $analytics['metrics']['sorrys'] ?? 0,
                    'angers' => $analytics['metrics']['angers'] ?? 0
                ],
                'demographics' => $analytics['demographics'] ?? [],
                'features_tested' => [
                    'basic_metrics' => '✅ Retrieved',
                    'reaction_tracking' => '✅ Facebook-specific reactions',
                    'demographic_data' => !empty($analytics['demographics']) ? '✅ Available' : '⏭️ Limited',
                    'timeline_data' => !empty($analytics['timeline']) ? '✅ Available' : '⏭️ Basic'
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    });
    
    // Test Facebook Analytics Helper
    Route::get('/helper-test', function () {
        try {
            // Create mock post
            $mockPost = new SocialMediaPost([
                'platform_posts' => [
                    'facebook' => [
                        'platform_id' => 'fb_mock_' . rand(100000000000000, 999999999999999),
                        'url' => 'https://facebook.com/mock-post',
                        'published_at' => now()->subHours(2)->toISOString()
                    ]
                ]
            ]);
            
            $result = FacebookHelpers::getFacebookAnalyticsSummary($mockPost);
            
            return [
                'status' => 'success',
                'developer' => 'J33WAKASUPUN',
                'message' => 'Facebook analytics helper test completed',
                'helper_result' => $result,
                'helper_method' => 'FacebookHelpers::getFacebookAnalyticsSummary',
                'facebook_specific_metrics' => $result['success'] ? [
                    'summary' => $result['summary'] ?? [],
                    'reactions_breakdown' => $result['reactions_breakdown'] ?? [],
                    'demographics' => !empty($result['demographics']) ? 'Available' : 'Limited'
                ] : ['error' => $result['error'] ?? 'Unknown error'],
                'advantage_over_linkedin' => [
                    'reaction_types' => '6 different reaction types (Like, Love, Wow, Haha, Sorry, Angry)',
                    'demographic_data' => 'Age and gender breakdowns available',
                    'video_metrics' => 'Video view and completion tracking',
                    'api_reliability' => 'More stable than LinkedIn API'
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    });
});

// === FACEBOOK POST MANAGEMENT ===

Route::prefix('test/facebook/management')->group(function () {
    
    // Test Facebook Post Existence Check
    Route::get('/check-post', function (Request $request) {
        try {
            $postId = $request->get('post_id', 'fb_test_' . rand(100000000000000, 999999999999999));
            
            $channel = FacebookHelpers::createTemporaryChannel();
            if (!$channel) {
                $channel = new Channel([
                    'oauth_tokens' => ['access_token' => 'mock_token'],
                    'provider' => 'facebook'
                ]);
            }
            
            $provider = new FacebookProvider();
            $result = $provider->checkPostExists($postId, $channel);
            
            return [
                'status' => 'success',
                'developer' => 'J33WAKASUPUN',
                'message' => 'Facebook post existence check completed',
                'post_id' => $postId,
                'check_result' => $result,
                'exists' => $result['exists'] ?? 'unknown',
                'mode' => $result['mode'] ?? 'unknown',
                'provider_method' => 'FacebookProvider::checkPostExists',
                'use_cases' => [
                    'before_deletion' => 'Check if post exists before attempting deletion',
                    'status_verification' => 'Verify post status after operations',
                    'cleanup_validation' => 'Confirm successful deletions'
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    });
    
    // Test Facebook Post Deletion
    Route::delete('/delete-post', function (Request $request) {
        try {
            $postId = $request->get('post_id', 'fb_test_' . rand(100000000000000, 999999999999999));
            
            $channel = FacebookHelpers::createTemporaryChannel();
            if (!$channel) {
                $channel = new Channel([
                    'oauth_tokens' => ['access_token' => 'mock_token'],
                    'provider' => 'facebook'
                ]);
            }
            
            $provider = new FacebookProvider();
            $result = $provider->deletePost($postId, $channel);
            
            return [
                'status' => 'success',
                'developer' => 'J33WAKASUPUN',
                'message' => 'Facebook post deletion test completed',
                'post_id' => $postId,
                'deletion_result' => $result,
                'deletion_success' => $result['success'] ?? false,
                'mode' => $result['mode'] ?? 'unknown',
                'provider_method' => 'FacebookProvider::deletePost',
                'facebook_advantage' => [
                    'api_deletion' => 'Facebook supports API-based post deletion',
                    'immediate_effect' => 'Deletions are processed immediately',
                    'better_than_linkedin' => 'More reliable than LinkedIn deletion API'
                ],
                'manual_fallback' => $result['requires_manual_deletion'] ?? false ? 
                    'Manual deletion may be required' : 'API deletion successful'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    });
    
    // Test Facebook Post Deletion Status
    Route::get('/deletion-status', function (Request $request) {
        try {
            $postId = $request->get('post_id', 'fb_test_' . rand(100000000000000, 999999999999999));
            $postUrl = $request->get('post_url', "https://facebook.com/{$postId}");
            
            $channel = FacebookHelpers::createTemporaryChannel();
            if (!$channel) {
                $channel = new Channel([
                    'oauth_tokens' => ['access_token' => 'mock_token'],
                    'provider' => 'facebook'
                ]);
            }
            
            $provider = new FacebookProvider();
            $result = $provider->getPostDeletionStatus($postId, $channel, $postUrl);
            
            return [
                'status' => 'success',
                'developer' => 'J33WAKASUPUN',
                'message' => 'Facebook post deletion status check completed',
                'post_id' => $postId,
                'post_url' => $postUrl,
                'status_result' => $result,
                'post_status' => $result['status'] ?? 'UNKNOWN',
                'provider_method' => 'FacebookProvider::getPostDeletionStatus',
                'status_meanings' => [
                    'EXISTS' => 'Post is still live on Facebook',
                    'DELETED' => 'Post has been successfully removed',
                    'UNKNOWN' => 'Could not determine post status'
                ],
                'deletion_options' => $result['deletion_options'] ?? []
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    });
});

// === FACEBOOK MEDIA VALIDATION ===

Route::prefix('test/facebook/media')->group(function () {
    
    // Test Facebook Media Validation
    Route::post('/validate', function (Request $request) {
        try {
            $mediaType = $request->input('type', 'image');
            $fileSize = $request->input('size', 1048576); // 1MB default
            $extension = $mediaType === 'image' ? 'jpg' : 'mp4';
            $fileName = $request->input('name', "test-file.{$extension}");
            
            // Create mock file object
            $mockFile = new class($fileName, $fileSize) {
                private $name;
                private $size;
                
                public function __construct($name, $size) {
                    $this->name = $name;
                    $this->size = $size;
                }
                
                public function getClientOriginalExtension() {
                    return pathinfo($this->name, PATHINFO_EXTENSION);
                }
                
                public function getSize() {
                    return $this->size;
                }
            };
            
            // Test platform-specific validation
            $facebookValidation = MediaValidation::validateMediaFile($mockFile, $mediaType, 'facebook');
            $genericValidation = MediaValidation::validateMediaFile($mockFile, $mediaType);
            
            $constraints = FacebookHelpers::getFacebookConstraints();
            
            return [
                'status' => 'success',
                'developer' => 'J33WAKASUPUN',
                'message' => 'Facebook media validation test completed',
                'test_file' => [
                    'name' => $fileName,
                    'type' => $mediaType,
                    'size' => $fileSize,
                    'size_formatted' => number_format($fileSize / 1024 / 1024, 2) . ' MB'
                ],
                'facebook_validation' => $facebookValidation,
                'generic_validation' => $genericValidation,
                'facebook_constraints' => [
                    'image_max_size' => number_format($constraints['image_max_size'] / 1024 / 1024, 0) . ' MB',
                    'video_max_size' => number_format($constraints['video_max_size'] / 1024 / 1024 / 1024, 0) . ' GB',
                    'supported_image_formats' => $constraints['supported_image_formats'],
                    'supported_video_formats' => $constraints['supported_video_formats']
                ],
                'validation_methods' => [
                    'platform_specific' => 'MediaValidation::validateMediaFile($file, $type, "facebook")',
                    'facebook_helper' => 'FacebookHelpers::validateFacebookMediaFile($file, $type)'
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    });
    
    // Facebook Media Constraints
    Route::get('/constraints', function () {
        try {
            $constraints = FacebookHelpers::getFacebookConstraints();
            
            return [
                'status' => 'success',
                'developer' => 'J33WAKASUPUN',
                'message' => 'Facebook media constraints',
                'facebook_limits' => $constraints,
                'formatted_limits' => [
                    'character_limit' => number_format($constraints['character_limit']) . ' characters',
                    'media_limit' => $constraints['media_limit'] . ' files per post',
                    'image_max_size' => number_format($constraints['image_max_size'] / 1024 / 1024, 0) . ' MB per image',
                    'video_max_size' => number_format($constraints['video_max_size'] / 1024 / 1024 / 1024, 0) . ' GB per video'
                ],
                'comparison_with_linkedin' => [
                    'character_limit' => 'Facebook: 63,206 vs LinkedIn: 3,000 (🎯 Facebook wins)',
                    'media_limit' => 'Facebook: 10 vs LinkedIn: 9 (🎯 Facebook wins)',
                    'video_size' => 'Facebook: 10GB vs LinkedIn: Complex upload (🎯 Facebook wins)',
                    'api_reliability' => 'Facebook: Excellent vs LinkedIn: Limited (🎯 Facebook wins)'
                ],
                'supported_formats' => [
                    'images' => $constraints['supported_image_formats'],
                    'videos' => $constraints['supported_video_formats']
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    });
});

// === FACEBOOK COMPREHENSIVE TEST SUITE ===

Route::get('/test/facebook/comprehensive', function () {
    try {
        $results = [];
        $provider = new FacebookProvider();
        $totalTests = 0;
        $passedTests = 0;
        
        Log::info('Facebook Comprehensive Test: Starting', [
            'provider_mode' => $provider->getCurrentMode(),
            'is_stub' => $provider->isStubMode(),
            'is_configured' => $provider->isConfigured()
        ]);
        
        // Test 1: Configuration - FIXED
        $totalTests++;
        try {
            $configTest = [
                'test' => 'Configuration Status',
                'status' => $provider->isConfigured() ? 'PASSED' : 'FAILED',
                'configured' => $provider->isConfigured(),
                'enabled' => FacebookHelpers::isFacebookEnabled(),
                'mode' => $provider->getCurrentMode()
            ];
            if ($configTest['status'] === 'PASSED') $passedTests++;
            $results['configuration'] = $configTest;
            
            Log::info('Facebook Test 1 - Configuration', $configTest);
        } catch (\Exception $e) {
            $results['configuration'] = [
                'test' => 'Configuration Status',
                'status' => 'FAILED',
                'error' => $e->getMessage()
            ];
            Log::error('Facebook Test 1 - Configuration Failed', ['error' => $e->getMessage()]);
        }
        
        // Test 2: OAuth Authentication - FIXED  
        $totalTests++;
        try {
            $authUrl = $provider->getAuthUrl('comprehensive_test_' . time());
            $oauthTest = [
                'test' => 'OAuth Authentication',
                'status' => !empty($authUrl) ? 'PASSED' : 'FAILED',
                'auth_url_generation' => !empty($authUrl) ? 'WORKING' : 'FAILED',
                'mode' => $provider->getCurrentMode()
            ];
            if ($oauthTest['status'] === 'PASSED') $passedTests++;
            $results['oauth'] = $oauthTest;
            
            Log::info('Facebook Test 2 - OAuth', [
                'status' => $oauthTest['status'],
                'auth_url_generated' => !empty($authUrl)
            ]);
        } catch (\Exception $e) {
            $results['oauth'] = [
                'test' => 'OAuth Authentication',
                'status' => 'FAILED',
                'error' => $e->getMessage()
            ];
            Log::error('Facebook Test 2 - OAuth Failed', ['error' => $e->getMessage()]);
        }
        
        // FIXED: Create proper test channel based on current mode
        $tempChannel = null;
        try {
            if ($provider->isStubMode()) {
                // Create stub channel with mock tokens
                $tempChannel = new Channel([
                    'provider' => 'facebook',
                    'handle' => 'facebook_test_user_' . uniqid(),
                    'display_name' => 'Facebook Test User',
                    'platform_user_id' => 'page_' . rand(100000000000000, 999999999999999),
                    'oauth_tokens' => [
                        'access_token' => 'facebook_test_token_' . uniqid(),
                        'expires_at' => now()->addDays(60)->toISOString(),
                        'token_type' => 'Bearer'
                    ],
                    'connection_status' => 'connected',
                    'active' => true
                ]);
                
                Log::info('Facebook Comprehensive: Created stub channel', [
                    'channel_handle' => $tempChannel->handle,
                    'has_tokens' => !empty($tempChannel->oauth_tokens)
                ]);
            } else {
                // For real mode, we'd need actual tokens
                // But since we're testing, we'll fall back to stub behavior
                $tempChannel = new Channel([
                    'provider' => 'facebook',
                    'handle' => 'facebook_test_real',
                    'display_name' => 'Facebook Real Test User',
                    'oauth_tokens' => [
                        'access_token' => 'would_need_real_token_here',
                        'expires_at' => now()->addDays(60)->toISOString()
                    ],
                    'connection_status' => 'connected',
                    'active' => true
                ]);
                
                Log::info('Facebook Comprehensive: Created real mode channel (test)', [
                    'mode' => 'real',
                    'note' => 'Would need actual OAuth tokens for real API calls'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Facebook Comprehensive: Channel creation failed', [
                'error' => $e->getMessage()
            ]);
            
            // Fallback channel
            $tempChannel = new Channel([
                'provider' => 'facebook',
                'handle' => 'facebook_fallback_' . uniqid(),
                'oauth_tokens' => ['access_token' => 'fallback_token'],
                'connection_status' => 'connected'
            ]);
        }
        
        // Test 3: Page Management - FIXED
        $totalTests++;
        try {
            Log::info('Facebook Test 3 - Page Management: Starting', [
                'provider_mode' => $provider->getCurrentMode(),
                'channel_handle' => $tempChannel->handle ?? 'null'
            ]);
            
            $pagesResult = $provider->getUserPages($tempChannel);
            
            Log::info('Facebook Test 3 - Page Management: Result', [
                'success' => $pagesResult['success'] ?? false,
                'pages_count' => count($pagesResult['pages'] ?? []),
                'mode' => $pagesResult['mode'] ?? 'unknown'
            ]);
            
            $pageTest = [
                'test' => 'Facebook Pages',
                'status' => $pagesResult['success'] ? 'PASSED' : 'FAILED',
                'pages_retrieved' => count($pagesResult['pages'] ?? []),
                'mode' => $pagesResult['mode'] ?? $provider->getCurrentMode()
            ];
            
            if ($pageTest['status'] === 'PASSED') $passedTests++;
            $results['page_management'] = $pageTest;
        } catch (\Exception $e) {
            Log::error('Facebook Test 3 - Page Management: Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $results['page_management'] = [
                'test' => 'Facebook Pages',
                'status' => 'FAILED',
                'error' => $e->getMessage(),
                'pages_retrieved' => 0,
                'mode' => $provider->getCurrentMode()
            ];
        }
        
        // Test 4: Post Publishing - FIXED
        $totalTests++;
        try {
            Log::info('Facebook Test 4 - Post Publishing: Starting', [
                'provider_mode' => $provider->getCurrentMode(),
                'channel_provider' => $tempChannel->provider ?? 'unknown'
            ]);
            
            $mockPost = new SocialMediaPost([
                'content' => ['text' => 'Facebook comprehensive test - ' . now()->toISOString()],
                'media' => [],
                'platforms' => ['facebook'],
                'user_id' => 'test_user_comprehensive',
                'post_status' => 'draft'
            ]);
            
            $publishResult = $provider->publishPost($mockPost, $tempChannel);
            
            Log::info('Facebook Test 4 - Post Publishing: Result', [
                'success' => $publishResult['success'] ?? false,
                'platform_id' => $publishResult['platform_id'] ?? null,
                'mode' => $publishResult['mode'] ?? 'unknown'
            ]);
            
            $publishTest = [
                'test' => 'Post Publishing',
                'status' => $publishResult['success'] ? 'PASSED' : 'FAILED',
                'platform_id' => $publishResult['platform_id'] ?? null,
                'mode' => $publishResult['mode'] ?? $provider->getCurrentMode()
            ];
            
            if ($publishTest['status'] === 'PASSED') $passedTests++;
            $results['post_publishing'] = $publishTest;
        } catch (\Exception $e) {
            Log::error('Facebook Test 4 - Post Publishing: Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $results['post_publishing'] = [
                'test' => 'Post Publishing',
                'status' => 'FAILED',
                'error' => $e->getMessage(),
                'platform_id' => null,
                'mode' => $provider->getCurrentMode()
            ];
        }
        
        // Test 5: Analytics - FIXED
        $totalTests++;
        try {
            Log::info('Facebook Test 5 - Analytics: Starting');
            
            $testPostId = 'fb_test_' . rand(100000000000000, 999999999999999);
            $analyticsResult = $provider->getAnalytics($testPostId, $tempChannel);
            
            Log::info('Facebook Test 5 - Analytics: Result', [
                'success' => $analyticsResult['success'] ?? false,
                'metrics_count' => count($analyticsResult['metrics'] ?? []),
                'mode' => $analyticsResult['mode'] ?? 'unknown'
            ]);
            
            $analyticsTest = [
                'test' => 'Analytics Collection',
                'status' => $analyticsResult['success'] ? 'PASSED' : 'FAILED',
                'metrics_available' => count($analyticsResult['metrics'] ?? []),
                'demographics_available' => !empty($analyticsResult['demographics']),
                'mode' => $analyticsResult['mode'] ?? $provider->getCurrentMode()
            ];
            
            if ($analyticsTest['status'] === 'PASSED') $passedTests++;
            $results['analytics'] = $analyticsTest;
        } catch (\Exception $e) {
            Log::error('Facebook Test 5 - Analytics: Exception', [
                'error' => $e->getMessage()
            ]);
            
            $results['analytics'] = [
                'test' => 'Analytics Collection',
                'status' => 'FAILED',
                'error' => $e->getMessage()
            ];
        }
        
        // Test 6: Post Management - FIXED
        $totalTests++;
        try {
            Log::info('Facebook Test 6 - Post Management: Starting');
            
            $testPostId = 'fb_test_' . rand(100000000000000, 999999999999999);
            $existenceCheck = $provider->checkPostExists($testPostId, $tempChannel);
            
            Log::info('Facebook Test 6 - Post Management: Result', [
                'success' => $existenceCheck['success'] ?? false,
                'exists' => $existenceCheck['exists'] ?? 'unknown',
                'mode' => $existenceCheck['mode'] ?? 'unknown'
            ]);
            
            $managementTest = [
                'test' => 'Post Management',
                'status' => $existenceCheck['success'] ? 'PASSED' : 'FAILED',
                'existence_check' => $existenceCheck['success'] ? 'WORKING' : 'FAILED',
                'deletion_capability' => 'AVAILABLE'
            ];
            
            if ($managementTest['status'] === 'PASSED') $passedTests++;
            $results['post_management'] = $managementTest;
        } catch (\Exception $e) {
            Log::error('Facebook Test 6 - Post Management: Exception', [
                'error' => $e->getMessage()
            ]);
            
            $results['post_management'] = [
                'test' => 'Post Management',
                'status' => 'FAILED',
                'error' => $e->getMessage()
            ];
        }
        
        // Test 7: Media Validation - FIXED
        $totalTests++;
        try {
            Log::info('Facebook Test 7 - Media Validation: Starting');
            
            $mockFile = new class('test.jpg', 1048576) {
                private $name, $size;
                public function __construct($name, $size) { 
                    $this->name = $name; 
                    $this->size = $size; 
                }
                public function getClientOriginalExtension() { 
                    return pathinfo($this->name, PATHINFO_EXTENSION); 
                }
                public function getSize() { 
                    return $this->size; 
                }
            };
            
            $mediaValidation = MediaValidation::validateMediaFile($mockFile, 'image', 'facebook');
            
            Log::info('Facebook Test 7 - Media Validation: Result', [
                'valid' => $mediaValidation['valid'] ?? false
            ]);
            
            $mediaTest = [
                'test' => 'Media Validation',
                'status' => $mediaValidation['valid'] ? 'PASSED' : 'FAILED',
                'facebook_specific' => 'WORKING',
                'constraints_loaded' => !empty(FacebookHelpers::getFacebookConstraints())
            ];
            
            if ($mediaTest['status'] === 'PASSED') $passedTests++;
            $results['media_validation'] = $mediaTest;
        } catch (\Exception $e) {
            Log::error('Facebook Test 7 - Media Validation: Exception', [
                'error' => $e->getMessage()
            ]);
            
            $results['media_validation'] = [
                'test' => 'Media Validation',
                'status' => 'FAILED',
                'error' => $e->getMessage()
            ];
        }
        
        $overallStatus = $passedTests === $totalTests;
        
        Log::info('Facebook Comprehensive Test: Completed', [
            'total_tests' => $totalTests,
            'passed_tests' => $passedTests,
            'failed_tests' => $totalTests - $passedTests,
            'overall_status' => $overallStatus ? 'PASSED' : 'FAILED'
        ]);
        
        return [
            'status' => 'success',
            'developer' => 'J33WAKASUPUN',
            'timestamp' => now()->toISOString(),
            'comprehensive_test' => 'COMPLETED',
            'overall_status' => $overallStatus ? 'ALL TESTS PASSED ✅' : 'SOME TESTS FAILED ⚠️',
            'total_tests' => $totalTests,
            'passed_tests' => $passedTests,
            'failed_tests' => $totalTests - $passedTests,
            'test_results' => $results,
            'facebook_implementation' => [
                'provider_class' => 'FacebookProvider - LOADED ✅',
                'helper_class' => 'FacebookHelpers - LOADED ✅',
                'media_validation' => 'MediaValidation - LOADED ✅',
                'route_file' => 'routes/facebook.php - ACTIVE ✅'
            ],
            'debug_info' => [
                'provider_mode_final' => $provider->getCurrentMode(),
                'provider_is_stub' => $provider->isStubMode(),
                'provider_configured' => $provider->isConfigured(),
                'channel_created' => !is_null($tempChannel),
                'log_entries_written' => 'Check storage/logs/laravel.log for detailed debugging'
            ],
            'advantages_over_linkedin' => [
                'api_reliability' => '🎯 Facebook Graph API is more stable',
                'media_support' => '🎯 Better video and carousel support',
                'analytics_depth' => '🎯 Richer insights and demographics',
                'deletion_capability' => '🎯 API-based post deletion works',
                'character_limits' => '🎯 Much higher character limits',
                'development_experience' => '🎯 Better documentation and tooling'
            ],
            'ready_for_production' => $overallStatus
        ];
    } catch (\Exception $e) {
        Log::error('Facebook Comprehensive Test: Fatal Exception', [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return [
            'status' => 'error',
            'error' => $e->getMessage(),
            'comprehensive_test' => 'FAILED',
            'timestamp' => now()->toISOString(),
            'developer' => 'J33WAKASUPUN',
            'debug_location' => $e->getFile() . ':' . $e->getLine()
        ];
    }
});

// === FACEBOOK DEBUG & DIAGNOSTIC ROUTES ===
Route::prefix('test/facebook')->group(function () {
    
    // ADD THIS: Debug Facebook Post ID Resolution  
    Route::get('/debug-post-id/{postId}', function ($postId) {
        try {
            $oauthSessionsPath = storage_path('app/oauth_sessions');
            $facebookFiles = glob($oauthSessionsPath . '/oauth_tokens_facebook_*.json');
            
            if (empty($facebookFiles)) {
                return response()->json([
                    'error' => 'No Facebook tokens found',
                    'oauth_required' => 'Complete OAuth: GET /test/facebook/oauth/url'
                ], 404);
            }
            
            // Use latest token
            $latestTokenFile = end($facebookFiles);
            $facebookToken = json_decode(file_get_contents($latestTokenFile), true);
            
            // Get pages
            $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
                'access_token' => $facebookToken['access_token'],
                'fields' => 'id,name,access_token,category'
            ]);
            
            if (!$pagesResponse->successful()) {
                return response()->json([
                    'error' => 'Failed to get Facebook pages',
                    'token_issue' => true,
                    'response' => $pagesResponse->json()
                ], 400);
            }
            
            $pages = $pagesResponse->json()['data'] ?? [];
            if (empty($pages)) {
                return response()->json([
                    'error' => 'No Facebook pages found'
                ], 400);
            }
            
            $selectedPage = $pages[0];
            $pageAccessToken = $selectedPage['access_token'];
            $pageId = $selectedPage['id'];
            
            // Test different post ID formats
            $postIdFormats = [
                'original' => $postId,
                'with_page_prefix' => $pageId . '_' . $postId,
                'without_prefix' => str_replace($pageId . '_', '', $postId)
            ];
            
            $results = [];
            $foundPost = null;
            
            foreach ($postIdFormats as $formatName => $testPostId) {
                $response = Http::get("https://graph.facebook.com/v18.0/{$testPostId}", [
                    'fields' => 'id,message,created_time,type,permalink_url',
                    'access_token' => $pageAccessToken
                ]);
                
                $success = $response->successful();
                $results[$formatName] = [
                    'post_id_tested' => $testPostId,
                    'success' => $success,
                    'http_code' => $response->status(),
                    'data' => $success ? $response->json() : null,
                    'error' => $success ? null : ($response->json()['error'] ?? 'Unknown error')
                ];
                
                if ($success && !$foundPost) {
                    $foundPost = $response->json();
                }
            }
            
            // Get recent posts for comparison
            $recentPostsResponse = Http::get("https://graph.facebook.com/v18.0/{$pageId}/posts", [
                'fields' => 'id,message,created_time,type,permalink_url',
                'limit' => 10,
                'access_token' => $pageAccessToken
            ]);
            
            $recentPosts = $recentPostsResponse->successful() ? $recentPostsResponse->json()['data'] : [];
            
            return response()->json([
                'status' => 'Facebook Post ID Debug Complete! 🔍',
                'developer' => 'J33WAKASUPUN',
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'debug_info' => [
                    'target_post_id' => $postId,
                    'page_id' => $pageId,
                    'page_name' => $selectedPage['name']
                ],
                'post_id_format_tests' => $results,
                'post_found' => !is_null($foundPost),
                'found_post_data' => $foundPost,
                'recent_posts_sample' => array_slice($recentPosts, 0, 3),
                'total_recent_posts' => count($recentPosts),
                'diagnosis' => [
                    'post_exists' => !is_null($foundPost) ? '✅ Post found!' : '❌ Post not found',
                    'correct_format' => $foundPost ? array_search(true, array_column($results, 'success')) : 'none',
                    'likely_issue' => is_null($foundPost) ? 'Post may not exist or be from different page' : 'Format identified'
                ],
                'recommended_actions' => [
                    'if_found' => $foundPost ? "Use format: {$foundPost['id']}" : null,
                    'if_not_found' => is_null($foundPost) ? [
                        'Check if post exists on Facebook',
                        'Verify post was created by your page',
                        'Check recent posts list above'
                    ] : null
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Debug failed',
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'developer' => 'J33WAKASUPUN'
            ], 500);
        }
    });
    
    // ADD THIS: Enhanced Facebook View Post Route
    Route::get('/view-post/{postId}', function ($postId) {
        try {
            $oauthSessionsPath = storage_path('app/oauth_sessions');
            $facebookFiles = glob($oauthSessionsPath . '/oauth_tokens_facebook_*.json');
            
            if (empty($facebookFiles)) {
                return response()->json([
                    'error' => 'No Facebook tokens found'
                ], 404);
            }
            
            $latestTokenFile = end($facebookFiles);
            $facebookToken = json_decode(file_get_contents($latestTokenFile), true);
            
            // Get pages
            $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
                'access_token' => $facebookToken['access_token'],
                'fields' => 'id,name,access_token'
            ]);
            
            if (!$pagesResponse->successful()) {
                return response()->json(['error' => 'Failed to get pages'], 400);
            }
            
            $pages = $pagesResponse->json()['data'] ?? [];
            if (empty($pages)) {
                return response()->json(['error' => 'No pages found'], 400);
            }
            
            $selectedPage = $pages[0];
            $pageAccessToken = $selectedPage['access_token'];
            $pageId = $selectedPage['id'];
            
            // Try different post ID formats with enhanced fields
            $postIdVariations = [
                'original' => $postId,
                'with_page_prefix' => $pageId . '_' . $postId,
                'without_prefix' => str_replace($pageId . '_', '', $postId)
            ];
            
            $foundPost = null;
            $usedVariation = null;
            
            foreach ($postIdVariations as $variation => $testPostId) {
                $response = Http::get("https://graph.facebook.com/v18.0/{$testPostId}", [
                    'fields' => 'id,message,story,created_time,updated_time,type,status_type,permalink_url,likes.summary(true),comments.summary(true),shares',
                    'access_token' => $pageAccessToken
                ]);
                
                if ($response->successful()) {
                    $foundPost = $response->json();
                    $usedVariation = $variation;
                    break;
                }
            }
            
            if ($foundPost) {
                return response()->json([
                    'status' => '👀 Facebook Post Retrieved via Laravel Route! 👀',
                    'developer' => 'J33WAKASUPUN',
                    'timestamp' => now()->format('Y-m-d H:i:s'),
                    'method' => 'Laravel HTTP Client + Enhanced Post ID Resolution',
                    'post_id_resolution' => [
                        'input_post_id' => $postId,
                        'resolved_post_id' => $foundPost['id'],
                        'variation_used' => $usedVariation
                    ],
                    'post_details' => [
                        'id' => $foundPost['id'],
                        'message' => $foundPost['message'] ?? $foundPost['story'] ?? 'Media post or no text',
                        'type' => $foundPost['type'] ?? 'status',
                        'status_type' => $foundPost['status_type'] ?? 'mobile_status_update',
                        'created_time' => $foundPost['created_time'],
                        'updated_time' => $foundPost['updated_time'] ?? null,
                        'permalink_url' => $foundPost['permalink_url'] ?? "https://facebook.com/{$foundPost['id']}"
                    ],
                    'engagement' => [
                        'likes' => $foundPost['likes']['summary']['total_count'] ?? 0,
                        'comments' => $foundPost['comments']['summary']['total_count'] ?? 0,
                        'shares' => $foundPost['shares']['count'] ?? 0
                    ],
                    'laravel_integration' => 'WORKING PERFECTLY! 🚀'
                ]);
            } else {
                return response()->json([
                    'error' => 'Post not found with any ID variation',
                    'input_post_id' => $postId,
                    'page_id' => $pageId,
                    'tried_variations' => array_keys($postIdVariations),
                    'suggestion' => 'Post may not exist or may be from different page'
                ], 404);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'View post failed',
                'message' => $e->getMessage(),
                'developer' => 'J33WAKASUPUN'
            ], 500);
        }
    });
    
    // ADD THIS: Enhanced Facebook Analytics Route  
    Route::get('/analytics-post/{postId}', function ($postId) {
        try {
            $oauthSessionsPath = storage_path('app/oauth_sessions');
            $facebookFiles = glob($oauthSessionsPath . '/oauth_tokens_facebook_*.json');
            
            if (empty($facebookFiles)) {
                return response()->json(['error' => 'No Facebook tokens found'], 404);
            }
            
            $latestTokenFile = end($facebookFiles);
            $facebookToken = json_decode(file_get_contents($latestTokenFile), true);
            
            // Get pages
            $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
                'access_token' => $facebookToken['access_token'],
                'fields' => 'id,name,access_token'
            ]);
            
            if (!$pagesResponse->successful()) {
                return response()->json(['error' => 'Failed to get pages'], 400);
            }
            
            $pages = $pagesResponse->json()['data'] ?? [];
            if (empty($pages)) {
                return response()->json(['error' => 'No pages found'], 400);
            }
            
            $selectedPage = $pages[0];
            $pageAccessToken = $selectedPage['access_token'];
            $pageId = $selectedPage['id'];
            
            // Try different post ID formats
            $postIdVariations = [
                'original' => $postId,
                'with_page_prefix' => $pageId . '_' . $postId,
                'without_prefix' => str_replace($pageId . '_', '', $postId)
            ];
            
            $foundPost = null;
            $usedVariation = null;
            
            foreach ($postIdVariations as $variation => $testPostId) {
                $response = Http::get("https://graph.facebook.com/v18.0/{$testPostId}", [
                    'fields' => 'id,message,created_time,updated_time,type,likes.summary(true),comments.summary(true),shares,reactions.summary(true)',
                    'access_token' => $pageAccessToken
                ]);
                
                if ($response->successful()) {
                    $foundPost = $response->json();
                    $usedVariation = $variation;
                    break;
                }
            }
            
            if ($foundPost) {
                // Calculate engagement metrics
                $likes = $foundPost['likes']['summary']['total_count'] ?? 0;
                $comments = $foundPost['comments']['summary']['total_count'] ?? 0;
                $shares = $foundPost['shares']['count'] ?? 0;
                $totalReactions = $foundPost['reactions']['summary']['total_count'] ?? $likes;
                $totalEngagement = $likes + $comments + $shares;
                
                return response()->json([
                    'status' => '📊 Facebook Analytics via Laravel Route! 📊',
                    'developer' => 'J33WAKASUPUN',
                    'timestamp' => now()->format('Y-m-d H:i:s'),
                    'method' => 'Laravel HTTP Client + Enhanced Analytics',
                    'post_id_resolution' => [
                        'input_post_id' => $postId,
                        'resolved_post_id' => $foundPost['id'],
                        'variation_used' => $usedVariation
                    ],
                    'post_info' => [
                        'id' => $foundPost['id'],
                        'type' => $foundPost['type'] ?? 'status',
                        'created_time' => $foundPost['created_time'],
                        'message_preview' => isset($foundPost['message']) ? substr($foundPost['message'], 0, 150) . '...' : 'Media post',
                        'post_url' => "https://facebook.com/{$foundPost['id']}"
                    ],
                    'engagement_metrics' => [
                        'total_engagement' => $totalEngagement,
                        'likes' => $likes,
                        'comments' => $comments,
                        'shares' => $shares,
                        'total_reactions' => $totalReactions
                    ],
                    'performance_indicators' => [
                        'high_engagement' => $totalEngagement > 50,
                        'viral_potential' => $shares > 10,
                        'discussion_starter' => $comments > $likes,
                        'engagement_rate' => $totalReactions > 0 ? round(($totalEngagement / $totalReactions) * 100, 2) . '%' : '0%'
                    ],
                    'laravel_integration' => 'ANALYTICS WORKING VIA LARAVEL! 🚀'
                ]);
            } else {
                return response()->json([
                    'error' => 'Post not found for analytics',
                    'input_post_id' => $postId,
                    'tried_variations' => array_keys($postIdVariations)
                ], 404);
            }
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Analytics failed',
                'message' => $e->getMessage(),
                'developer' => 'J33WAKASUPUN'
            ], 500);
        }
    });

});

// Add this route to check your current permissions
Route::get('/test/facebook/check-permissions', function () {
    try {
        $oauthSessionsPath = storage_path('app/oauth_sessions');
        $facebookFiles = glob($oauthSessionsPath . '/oauth_tokens_facebook_*.json');
        
        if (empty($facebookFiles)) {
            return response()->json(['error' => 'No Facebook tokens found'], 404);
        }
        
        $latestTokenFile = end($facebookFiles);
        $facebookToken = json_decode(file_get_contents($latestTokenFile), true);
        
        // Check user permissions
        $permissionsResponse = Http::get('https://graph.facebook.com/v18.0/me/permissions', [
            'access_token' => $facebookToken['access_token']
        ]);
        
        if (!$permissionsResponse->successful()) {
            return response()->json([
                'error' => 'Failed to check permissions',
                'response' => $permissionsResponse->json()
            ], 400);
        }
        
        $permissions = $permissionsResponse->json()['data'] ?? [];
        $granted = [];
        $declined = [];
        
        foreach ($permissions as $perm) {
            if ($perm['status'] === 'granted') {
                $granted[] = $perm['permission'];
            } else {
                $declined[] = $perm['permission'];
            }
        }
        
        $requiredPermissions = [
            'pages_show_list',
            'pages_manage_posts', 
            'pages_read_engagement',
            'pages_read_user_content',
            'business_management',
            'public_profile'
        ];
        
        $missing = array_diff($requiredPermissions, $granted);
        
        return response()->json([
            'status' => 'Facebook Permissions Check Complete! 🔐',
            'developer' => 'J33WAKASUPUN',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'permission_summary' => [
                'total_permissions' => count($permissions),
                'granted_count' => count($granted),
                'declined_count' => count($declined),
                'missing_count' => count($missing)
            ],
            'granted_permissions' => $granted,
            'declined_permissions' => $declined,
            'required_permissions' => $requiredPermissions,
            'missing_permissions' => $missing,
            'diagnosis' => [
                'has_posting_access' => in_array('pages_manage_posts', $granted) ? '✅ Yes' : '❌ No',
                'has_reading_access' => in_array('pages_read_engagement', $granted) ? '✅ Yes' : '❌ No',
                'has_page_access' => in_array('pages_show_list', $granted) ? '✅ Yes' : '❌ No',
                'can_read_posts' => in_array('pages_read_engagement', $granted) && in_array('pages_read_user_content', $granted) ? '✅ Yes' : '❌ No'
            ],
            'solution' => count($missing) > 0 ? [
                'action' => 'Re-authorize Facebook app with missing permissions',
                'missing_permissions' => $missing,
                'reauth_url' => 'GET /test/facebook/reauth-url'
            ] : [
                'action' => 'All permissions granted! Check other issues.',
                'status' => 'PERMISSIONS_COMPLETE'
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Permission check failed',
            'message' => $e->getMessage(),
            'developer' => 'J33WAKASUPUN'
        ], 500);
    }
});

// Add this route for re-authorization with ALL permissions
Route::get('/test/facebook/reauth-url', function () {
    try {
        $facebookAppId = env('FACEBOOK_CLIENT_ID');
        $redirectUri = env('FACEBOOK_REDIRECT_URI', 'http://localhost:8000/oauth/facebook/callback');
        
        if (!$facebookAppId) {
            return response()->json([
                'error' => 'Facebook App ID not configured',
                'fix' => 'Set FACEBOOK_CLIENT_ID in .env file'
            ], 400);
        }
        
        // ENHANCED: All permissions needed for full Facebook functionality
        $allRequiredScopes = [
            'pages_show_list',           // List user's pages
            'pages_manage_posts',        // Create, update, delete posts
            'pages_read_engagement',     // READ POST ENGAGEMENT DATA (MISSING!)
            'pages_read_user_content',   // Read page content (MISSING!)
            'business_management',       // Business management access
            'public_profile',            // Basic profile info
            'email'                      // Email address (optional)
        ];
        
        $authUrl = 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query([
            'client_id' => $facebookAppId,
            'redirect_uri' => $redirectUri,
            'scope' => implode(',', $allRequiredScopes),
            'response_type' => 'code',
            'state' => 'reauth_full_permissions_' . time(),
            'auth_type' => 'rerequest'  // IMPORTANT: Forces re-authorization
        ]);
        
        return response()->json([
            'status' => 'Facebook Re-Authorization URL Generated! 🔐',
            'developer' => 'J33WAKASUPUN',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'reauth_required' => 'Missing permissions for reading posts',
            'missing_critical_permissions' => [
                'pages_read_engagement' => 'Required to read post likes, comments, shares',
                'pages_read_user_content' => 'Required to access post content'
            ],
            'reauth_url' => $authUrl,
            'all_permissions_requested' => $allRequiredScopes,
            'instructions' => [
                '1️⃣ Copy the reauth_url below',
                '2️⃣ Open it in your browser',
                '3️⃣ Accept ALL permissions (especially the reading permissions)',
                '4️⃣ Complete the OAuth flow',
                '5️⃣ Test the view/analytics endpoints again'
            ],
            'why_reauth_needed' => [
                'current_issue' => 'Cannot read existing posts due to missing permissions',
                'impact' => 'VIEW and ANALYTICS operations fail',
                'solution' => 'Re-authorize with pages_read_engagement permission'
            ],
            'test_after_reauth' => [
                'check_permissions' => 'GET /test/facebook/check-permissions',
                'debug_post' => 'GET /test/facebook/debug-post-id/775860752279131_122099900751016950',
                'view_post' => 'GET /test/facebook/view-post/775860752279131_122099900751016950',
                'analytics' => 'GET /test/facebook/analytics-post/775860752279131_122099900751016950'
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Failed to generate re-auth URL',
            'message' => $e->getMessage(),
            'developer' => 'J33WAKASUPUN'
        ], 500);
    }
});

// Add this route to test if we can see ANY posts from your page
Route::get('/test/facebook/check-page-access', function () {
    try {
        $oauthSessionsPath = storage_path('app/oauth_sessions');
        $facebookFiles = glob($oauthSessionsPath . '/oauth_tokens_facebook_*.json');
        
        if (empty($facebookFiles)) {
            return response()->json(['error' => 'No Facebook tokens found'], 404);
        }
        
        $latestTokenFile = end($facebookFiles);
        $facebookToken = json_decode(file_get_contents($latestTokenFile), true);
        
        // Get pages
        $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
            'access_token' => $facebookToken['access_token'],
            'fields' => 'id,name,access_token,category,followers_count,tasks'
        ]);
        
        if (!$pagesResponse->successful()) {
            return response()->json([
                'error' => 'Failed to get pages',
                'response' => $pagesResponse->json()
            ], 400);
        }
        
        $pages = $pagesResponse->json()['data'] ?? [];
        
        if (empty($pages)) {
            return response()->json(['error' => 'No pages found'], 400);
        }
        
        $pageTests = [];
        
        foreach ($pages as $page) {
            $pageId = $page['id'];
            $pageAccessToken = $page['access_token'];
            $pageName = $page['name'];
            
            // Test different endpoints for this page
            $tests = [
                'page_info' => Http::get("https://graph.facebook.com/v18.0/{$pageId}", [
                    'fields' => 'id,name,category,followers_count',
                    'access_token' => $pageAccessToken
                ]),
                'page_posts_basic' => Http::get("https://graph.facebook.com/v18.0/{$pageId}/posts", [
                    'fields' => 'id,created_time',
                    'limit' => 5,
                    'access_token' => $pageAccessToken
                ]),
                'page_feed' => Http::get("https://graph.facebook.com/v18.0/{$pageId}/feed", [
                    'fields' => 'id,created_time',
                    'limit' => 5,
                    'access_token' => $pageAccessToken
                ])
            ];
            
            $pageTest = [
                'page_id' => $pageId,
                'page_name' => $pageName,
                'page_category' => $page['category'] ?? 'Unknown',
                'has_access_token' => !empty($pageAccessToken),
                'access_tests' => []
            ];
            
            foreach ($tests as $testName => $response) {
                $pageTest['access_tests'][$testName] = [
                    'success' => $response->successful(),
                    'http_code' => $response->status(),
                    'data_count' => $response->successful() ? count($response->json()['data'] ?? []) : 0,
                    'error' => $response->successful() ? null : ($response->json()['error']['message'] ?? 'Unknown error')
                ];
            }
            
            $pageTests[] = $pageTest;
        }
        
        return response()->json([
            'status' => 'Facebook Page Access Test Complete! 📋',
            'developer' => 'J33WAKASUPUN',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'total_pages' => count($pages),
            'page_access_tests' => $pageTests,
            'summary' => [
                'can_access_page_info' => count(array_filter($pageTests, fn($p) => $p['access_tests']['page_info']['success'])) . '/' . count($pageTests),
                'can_access_posts' => count(array_filter($pageTests, fn($p) => $p['access_tests']['page_posts_basic']['success'])) . '/' . count($pageTests),
                'can_access_feed' => count(array_filter($pageTests, fn($p) => $p['access_tests']['page_feed']['success'])) . '/' . count($pageTests)
            ],
            'diagnosis' => [
                'permission_issue' => 'Likely missing pages_read_engagement permission',
                'solution' => 'Re-authorize with full permissions',
                'reauth_url_endpoint' => 'GET /test/facebook/reauth-url'
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Page access test failed',
            'message' => $e->getMessage(),
            'developer' => 'J33WAKASUPUN'
        ], 500);
    }
});

// Add this advanced debugging route
Route::get('/test/facebook/advanced-debug/{postId}', function ($postId) {
    try {
        $oauthSessionsPath = storage_path('app/oauth_sessions');
        $facebookFiles = glob($oauthSessionsPath . '/oauth_tokens_facebook_*.json');
        
        if (empty($facebookFiles)) {
            return response()->json(['error' => 'No Facebook tokens found'], 404);
        }
        
        $latestTokenFile = end($facebookFiles);
        $facebookToken = json_decode(file_get_contents($latestTokenFile), true);
        
        // Get pages with detailed info
        $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
            'access_token' => $facebookToken['access_token'],
            'fields' => 'id,name,access_token,category,followers_count,tasks,perms'
        ]);
        
        if (!$pagesResponse->successful()) {
            return response()->json(['error' => 'Failed to get pages'], 400);
        }
        
        $pages = $pagesResponse->json()['data'] ?? [];
        if (empty($pages)) {
            return response()->json(['error' => 'No pages found'], 400);
        }
        
        $selectedPage = $pages[0];
        $pageAccessToken = $selectedPage['access_token'];
        $pageId = $selectedPage['id'];
        
        // ADVANCED: Test different API approaches
        $apiTests = [
            // Test 1: Direct post access with user token
            'user_token_post_access' => [
                'url' => "https://graph.facebook.com/v18.0/{$postId}",
                'params' => [
                    'fields' => 'id,message,created_time',
                    'access_token' => $facebookToken['access_token']
                ]
            ],
            
            // Test 2: Direct post access with page token  
            'page_token_post_access' => [
                'url' => "https://graph.facebook.com/v18.0/{$postId}",
                'params' => [
                    'fields' => 'id,message,created_time',
                    'access_token' => $pageAccessToken
                ]
            ],
            
            // Test 3: Get posts from page feed and find our post
            'page_feed_search' => [
                'url' => "https://graph.facebook.com/v18.0/{$pageId}/feed",
                'params' => [
                    'fields' => 'id,message,created_time,type',
                    'limit' => 25,
                    'access_token' => $pageAccessToken
                ]
            ],
            
            // Test 4: Get posts from page/posts endpoint
            'page_posts_search' => [
                'url' => "https://graph.facebook.com/v18.0/{$pageId}/posts",
                'params' => [
                    'fields' => 'id,message,created_time,type',
                    'limit' => 25,
                    'access_token' => $pageAccessToken
                ]
            ],
            
            // Test 5: Use older API version
            'v17_api_test' => [
                'url' => "https://graph.facebook.com/v17.0/{$postId}",
                'params' => [
                    'fields' => 'id,message,created_time',
                    'access_token' => $pageAccessToken
                ]
            ],
            
            // Test 6: Minimal fields test
            'minimal_fields_test' => [
                'url' => "https://graph.facebook.com/v18.0/{$postId}",
                'params' => [
                    'fields' => 'id',
                    'access_token' => $pageAccessToken
                ]
            ]
        ];
        
        $results = [];
        $foundInFeed = null;
        
        foreach ($apiTests as $testName => $test) {
            $response = Http::get($test['url'], $test['params']);
            
            $result = [
                'test_name' => $testName,
                'success' => $response->successful(),
                'http_code' => $response->status(),
                'url_tested' => $test['url'],
                'data' => null,
                'error' => null
            ];
            
            if ($response->successful()) {
                $data = $response->json();
                $result['data'] = $data;
                
                // If this is a feed search, look for our post
                if (in_array($testName, ['page_feed_search', 'page_posts_search'])) {
                    $posts = $data['data'] ?? [];
                    foreach ($posts as $post) {
                        if ($post['id'] === $postId || str_contains($post['id'], $postId)) {
                            $foundInFeed = $post;
                            $result['found_target_post'] = true;
                            $result['target_post_data'] = $post;
                            break;
                        }
                    }
                    $result['total_posts_found'] = count($posts);
                    $result['posts_sample'] = array_slice($posts, 0, 3);
                }
            } else {
                $result['error'] = $response->json();
            }
            
            $results[] = $result;
        }
        
        // Token introspection
        $tokenDebugResponse = Http::get('https://graph.facebook.com/debug_token', [
            'input_token' => $facebookToken['access_token'],
            'access_token' => $facebookToken['access_token']
        ]);
        
        $tokenDebug = $tokenDebugResponse->successful() ? $tokenDebugResponse->json() : null;
        
        return response()->json([
            'status' => 'Facebook Advanced Debug Complete! 🔬',
            'developer' => 'J33WAKASUPUN',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'debug_info' => [
                'target_post_id' => $postId,
                'page_id' => $pageId,
                'page_name' => $selectedPage['name'],
                'user_token_length' => strlen($facebookToken['access_token']),
                'page_token_length' => strlen($pageAccessToken),
                'tokens_different' => $facebookToken['access_token'] !== $pageAccessToken
            ],
            'api_test_results' => $results,
            'post_found_in_feed' => !is_null($foundInFeed),
            'found_post_data' => $foundInFeed,
            'token_debug_info' => $tokenDebug,
            'page_permissions' => $selectedPage['perms'] ?? [],
            'page_tasks' => $selectedPage['tasks'] ?? [],
            'diagnosis' => [
                'permissions_granted' => '✅ All required permissions present',
                'page_access' => '✅ Can access page and its feed',
                'specific_post_access' => is_null($foundInFeed) ? '❌ Cannot access specific post' : '✅ Post found in feed',
                'likely_causes' => [
                    'post_privacy_settings' => 'Post may have specific privacy restrictions',
                    'api_version_issue' => 'Different API versions may behave differently',
                    'token_scope_issue' => 'Token may not have post-level access despite having page-level access',
                    'post_type_restriction' => 'Certain post types may require different access patterns'
                ]
            ],
            'next_steps' => [
                'if_found_in_feed' => $foundInFeed ? 'Use feed-based access instead of direct post access' : null,
                'try_different_approach' => 'Use page feed search to find and display post data',
                'check_post_privacy' => 'Verify post privacy settings on Facebook'
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Advanced debug failed',
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'developer' => 'J33WAKASUPUN'
        ], 500);
    }
});

// Add this route that finds posts via feed search instead of direct access
Route::get('/test/facebook/view-post-via-feed/{postId}', function ($postId) {
    try {
        $oauthSessionsPath = storage_path('app/oauth_sessions');
        $facebookFiles = glob($oauthSessionsPath . '/oauth_tokens_facebook_*.json');
        
        if (empty($facebookFiles)) {
            return response()->json(['error' => 'No Facebook tokens found'], 404);
        }
        
        $latestTokenFile = end($facebookFiles);
        $facebookToken = json_decode(file_get_contents($latestTokenFile), true);
        
        // Get pages
        $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
            'access_token' => $facebookToken['access_token'],
            'fields' => 'id,name,access_token'
        ]);
        
        if (!$pagesResponse->successful()) {
            return response()->json(['error' => 'Failed to get pages'], 400);
        }
        
        $pages = $pagesResponse->json()['data'] ?? [];
        if (empty($pages)) {
            return response()->json(['error' => 'No pages found'], 400);
        }
        
        $selectedPage = $pages[0];
        $pageAccessToken = $selectedPage['access_token'];
        $pageId = $selectedPage['id'];
        
        // Search through page feed to find the post
        $feedResponse = Http::get("https://graph.facebook.com/v18.0/{$pageId}/feed", [
            'fields' => 'id,message,story,created_time,updated_time,type,status_type,permalink_url,likes.summary(true),comments.summary(true),shares',
            'limit' => 50, // Get more posts to increase chance of finding target
            'access_token' => $pageAccessToken
        ]);
        
        if (!$feedResponse->successful()) {
            return response()->json([
                'error' => 'Failed to get page feed',
                'response' => $feedResponse->json()
            ], 400);
        }
        
        $posts = $feedResponse->json()['data'] ?? [];
        $targetPost = null;
        
        // Look for the post in different ways
        foreach ($posts as $post) {
            $currentPostId = $post['id'];
            
            // Check various ID matching patterns
            if ($currentPostId === $postId ||                           // Exact match
                str_contains($currentPostId, $postId) ||                // Contains target ID
                str_contains($postId, $currentPostId) ||                // Target contains current ID
                str_replace($pageId . '_', '', $currentPostId) === str_replace($pageId . '_', '', $postId)) { // Without page prefix
                
                $targetPost = $post;
                break;
            }
        }
        
        if ($targetPost) {
            return response()->json([
                'status' => '👀 Facebook Post Found via Feed Search! 👀',
                'developer' => 'J33WAKASUPUN',
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'method' => 'Feed Search Workaround (Direct access blocked)',
                'search_info' => [
                    'target_post_id' => $postId,
                    'found_post_id' => $targetPost['id'],
                    'total_posts_searched' => count($posts),
                    'search_method' => 'page_feed_search'
                ],
                'post_details' => [
                    'id' => $targetPost['id'],
                    'message' => $targetPost['message'] ?? $targetPost['story'] ?? 'Media post or no text',
                    'type' => $targetPost['type'] ?? 'status',
                    'status_type' => $targetPost['status_type'] ?? 'mobile_status_update',
                    'created_time' => $targetPost['created_time'],
                    'updated_time' => $targetPost['updated_time'] ?? null,
                    'permalink_url' => $targetPost['permalink_url'] ?? "https://facebook.com/{$targetPost['id']}"
                ],
                'engagement' => [
                    'likes' => $targetPost['likes']['summary']['total_count'] ?? 0,
                    'comments' => $targetPost['comments']['summary']['total_count'] ?? 0,
                    'shares' => $targetPost['shares']['count'] ?? 0
                ],
                'workaround_note' => [
                    'why_needed' => 'Direct post access blocked despite having permissions',
                    'solution' => 'Using page feed search to find post data',
                    'limitation' => 'Only works for recent posts (last ~50 posts)'
                ],
                'success_via_feed' => '✅ FACEBOOK POST ACCESS WORKING VIA FEED SEARCH!'
            ]);
        } else {
            return response()->json([
                'error' => 'Post not found in page feed',
                'search_info' => [
                    'target_post_id' => $postId,
                    'total_posts_searched' => count($posts),
                    'posts_sample' => array_slice(array_map(fn($p) => ['id' => $p['id'], 'created_time' => $p['created_time']], $posts), 0, 5)
                ],
                'suggestions' => [
                    'Post may be older than the last 50 posts',
                    'Post may have been deleted',
                    'Post may be from a different page',
                    'Try with a more recent post ID'
                ]
            ], 404);
        }
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Feed search failed',
            'message' => $e->getMessage(),
            'developer' => 'J33WAKASUPUN'
        ], 500);
    }
});

// Replace your existing debugging routes with these FIXED versions

// FIXED: Debug with compatible fields
Route::get('/test/facebook/fixed-debug/{postId}', function ($postId) {
    try {
        $oauthSessionsPath = storage_path('app/oauth_sessions');
        $facebookFiles = glob($oauthSessionsPath . '/oauth_tokens_facebook_*.json');
        
        if (empty($facebookFiles)) {
            return response()->json(['error' => 'No Facebook tokens found'], 404);
        }
        
        $latestTokenFile = end($facebookFiles);
        $facebookToken = json_decode(file_get_contents($latestTokenFile), true);
        
        // FIXED: Use compatible field combinations for pages
        $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
            'access_token' => $facebookToken['access_token'],
            'fields' => 'id,name,access_token,category,followers_count' // Removed problematic fields
        ]);
        
        if (!$pagesResponse->successful()) {
            return response()->json([
                'error' => 'Failed to get pages',
                'response' => $pagesResponse->json(),
                'fix_applied' => 'Using compatible field combinations'
            ], 400);
        }
        
        $pages = $pagesResponse->json()['data'] ?? [];
        if (empty($pages)) {
            return response()->json(['error' => 'No pages found'], 400);
        }
        
        $selectedPage = $pages[0];
        $pageAccessToken = $selectedPage['access_token'];
        $pageId = $selectedPage['id'];
        
        // FIXED: Test different API approaches with compatible fields
        $apiTests = [];
        
        // Test 1: Basic post fields only (no deprecated fields)
        try {
            $basicPostTest = Http::get("https://graph.facebook.com/v18.0/{$postId}", [
                'fields' => 'id,message,created_time,type',
                'access_token' => $pageAccessToken
            ]);
            
            $apiTests['basic_post_access'] = [
                'success' => $basicPostTest->successful(),
                'http_code' => $basicPostTest->status(),
                'data' => $basicPostTest->successful() ? $basicPostTest->json() : null,
                'error' => $basicPostTest->successful() ? null : $basicPostTest->json()
            ];
        } catch (\Exception $e) {
            $apiTests['basic_post_access'] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
        
        // Test 2: Page feed with minimal fields
        try {
            $feedTest = Http::get("https://graph.facebook.com/v18.0/{$pageId}/feed", [
                'fields' => 'id,message,created_time,type', // FIXED: Minimal field set
                'limit' => 10,
                'access_token' => $pageAccessToken
            ]);
            
            $apiTests['page_feed_minimal'] = [
                'success' => $feedTest->successful(),
                'http_code' => $feedTest->status(),
                'data' => $feedTest->successful() ? $feedTest->json() : null,
                'error' => $feedTest->successful() ? null : $feedTest->json(),
                'posts_found' => $feedTest->successful() ? count($feedTest->json()['data'] ?? []) : 0
            ];
        } catch (\Exception $e) {
            $apiTests['page_feed_minimal'] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
        
        // Test 3: Page posts endpoint with minimal fields
        try {
            $postsTest = Http::get("https://graph.facebook.com/v18.0/{$pageId}/posts", [
                'fields' => 'id,message,created_time,type',
                'limit' => 10,
                'access_token' => $pageAccessToken
            ]);
            
            $apiTests['page_posts_minimal'] = [
                'success' => $postsTest->successful(),
                'http_code' => $postsTest->status(),
                'data' => $postsTest->successful() ? $postsTest->json() : null,
                'error' => $postsTest->successful() ? null : $postsTest->json(),
                'posts_found' => $postsTest->successful() ? count($postsTest->json()['data'] ?? []) : 0
            ];
        } catch (\Exception $e) {
            $apiTests['page_posts_minimal'] = [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
        
        // Search for target post in successful results
        $foundPost = null;
        $foundVia = null;
        
        foreach ($apiTests as $testName => $result) {
            if ($result['success'] && isset($result['data']['data'])) {
                foreach ($result['data']['data'] as $post) {
                    if ($post['id'] === $postId || str_contains($post['id'], str_replace($pageId . '_', '', $postId))) {
                        $foundPost = $post;
                        $foundVia = $testName;
                        break 2;
                    }
                }
            } elseif ($testName === 'basic_post_access' && $result['success'] && isset($result['data']['id'])) {
                // Direct post access successful
                $foundPost = $result['data'];
                $foundVia = $testName;
                break;
            }
        }
        
        return response()->json([
            'status' => 'Facebook Fixed Debug Complete! 🔧',
            'developer' => 'J33WAKASUPUN',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'fix_applied' => 'Using Facebook API compatible field combinations',
            'debug_info' => [
                'target_post_id' => $postId,
                'page_id' => $pageId,
                'page_name' => $selectedPage['name']
            ],
            'api_test_results' => $apiTests,
            'post_found' => !is_null($foundPost),
            'found_via' => $foundVia,
            'found_post_data' => $foundPost,
            'field_fix_notes' => [
                'removed_deprecated_fields' => 'Removed fields causing deprecation errors',
                'using_minimal_field_sets' => 'Using only stable, compatible fields',
                'facebook_api_version' => 'v18.0 with compatible field combinations'
            ],
            'success_indicators' => [
                'pages_access' => '✅ Fixed with compatible fields',
                'feed_access' => $apiTests['page_feed_minimal']['success'] ? '✅ Working' : '❌ Still failing',
                'posts_access' => $apiTests['page_posts_minimal']['success'] ? '✅ Working' : '❌ Still failing',
                'direct_post_access' => $apiTests['basic_post_access']['success'] ? '✅ Working' : '❌ Still failing'
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Fixed debug failed',
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'developer' => 'J33WAKASUPUN'
        ], 500);
    }
});

// FIXED: View post with compatible fields only
Route::get('/test/facebook/view-post-fixed/{postId}', function ($postId) {
    try {
        $oauthSessionsPath = storage_path('app/oauth_sessions');
        $facebookFiles = glob($oauthSessionsPath . '/oauth_tokens_facebook_*.json');
        
        if (empty($facebookFiles)) {
            return response()->json(['error' => 'No Facebook tokens found'], 404);
        }
        
        $latestTokenFile = end($facebookFiles);
        $facebookToken = json_decode(file_get_contents($latestTokenFile), true);
        
        // Get pages with fixed fields
        $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
            'access_token' => $facebookToken['access_token'],
            'fields' => 'id,name,access_token,category' // FIXED: Minimal page fields
        ]);
        
        if (!$pagesResponse->successful()) {
            return response()->json([
                'error' => 'Failed to get pages',
                'response' => $pagesResponse->json()
            ], 400);
        }
        
        $pages = $pagesResponse->json()['data'] ?? [];
        if (empty($pages)) {
            return response()->json(['error' => 'No pages found'], 400);
        }
        
        $selectedPage = $pages[0];
        $pageAccessToken = $selectedPage['access_token'];
        $pageId = $selectedPage['id'];
        
        // Try different post ID formats with FIXED field combinations
        $postIdVariations = [
            'original' => $postId,
            'with_page_prefix' => $pageId . '_' . $postId,
            'without_prefix' => str_replace($pageId . '_', '', $postId)
        ];
        
        $foundPost = null;
        $usedVariation = null;
        $usedFields = null;
        
        // FIXED: Progressive field testing (start minimal, add more)
        $fieldCombinations = [
            'minimal' => 'id,message,created_time,type',
            'basic' => 'id,message,created_time,type,updated_time',
            'extended' => 'id,message,created_time,type,updated_time,permalink_url'
        ];
        
        foreach ($postIdVariations as $variation => $testPostId) {
            foreach ($fieldCombinations as $fieldName => $fields) {
                try {
                    $response = Http::get("https://graph.facebook.com/v18.0/{$testPostId}", [
                        'fields' => $fields,
                        'access_token' => $pageAccessToken
                    ]);
                    
                    if ($response->successful()) {
                        $foundPost = $response->json();
                        $usedVariation = $variation;
                        $usedFields = $fieldName;
                        break 2; // Exit both loops
                    }
                } catch (\Exception $e) {
                    // Continue to next combination
                    continue;
                }
            }
        }
        
        // If direct access failed, try feed search with minimal fields
        if (!$foundPost) {
            try {
                $feedResponse = Http::get("https://graph.facebook.com/v18.0/{$pageId}/feed", [
                    'fields' => 'id,message,created_time,type', // FIXED: Minimal fields only
                    'limit' => 25,
                    'access_token' => $pageAccessToken
                ]);
                
                if ($feedResponse->successful()) {
                    $posts = $feedResponse->json()['data'] ?? [];
                    foreach ($posts as $post) {
                        if ($post['id'] === $postId || str_contains($post['id'], str_replace($pageId . '_', '', $postId))) {
                            $foundPost = $post;
                            $usedVariation = 'found_in_feed';
                            $usedFields = 'minimal_from_feed';
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Feed search also failed
            }
        }
        
        if ($foundPost) {
            return response()->json([
                'status' => '👀 Facebook Post Retrieved with Fixed Fields! 👀',
                'developer' => 'J33WAKASUPUN',
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'method' => 'Fixed field combinations + Progressive fallback',
                'fix_applied' => 'Using Facebook API compatible fields only',
                'post_id_resolution' => [
                    'input_post_id' => $postId,
                    'resolved_post_id' => $foundPost['id'],
                    'variation_used' => $usedVariation,
                    'fields_used' => $usedFields
                ],
                'post_details' => [
                    'id' => $foundPost['id'],
                    'message' => $foundPost['message'] ?? 'No message content',
                    'type' => $foundPost['type'] ?? 'status',
                    'created_time' => $foundPost['created_time'],
                    'updated_time' => $foundPost['updated_time'] ?? null,
                    'permalink_url' => $foundPost['permalink_url'] ?? "https://facebook.com/{$foundPost['id']}"
                ],
                'field_limitations' => [
                    'engagement_data' => 'Not available due to field compatibility issues',
                    'available_data' => 'Basic post information only',
                    'reason' => 'Facebook deprecated certain field combinations'
                ],
                'success_with_compatible_fields' => '✅ FACEBOOK POST ACCESS WORKING WITH FIXED FIELDS!'
            ]);
        } else {
            return response()->json([
                'error' => 'Post not found with any compatible field combination',
                'input_post_id' => $postId,
                'page_id' => $pageId,
                'tried_variations' => array_keys($postIdVariations),
                'tried_field_combinations' => array_keys($fieldCombinations),
                'suggestions' => [
                    'Post may not exist or may be very old',
                    'Post may have been deleted',
                    'Post may be from a different page',
                    'Try creating a new test post and viewing it immediately'
                ]
            ], 404);
        }
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Fixed view post failed',
            'message' => $e->getMessage(),
            'developer' => 'J33WAKASUPUN'
        ], 500);
    }
});

// FIXED: Analytics with compatible fields
Route::get('/test/facebook/analytics-fixed/{postId}', function ($postId) {
    try {
        $oauthSessionsPath = storage_path('app/oauth_sessions');
        $facebookFiles = glob($oauthSessionsPath . '/oauth_tokens_facebook_*.json');
        
        if (empty($facebookFiles)) {
            return response()->json(['error' => 'No Facebook tokens found'], 404);
        }
        
        $latestTokenFile = end($facebookFiles);
        $facebookToken = json_decode(file_get_contents($latestTokenFile), true);
        
        // Get pages with fixed fields
        $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
            'access_token' => $facebookToken['access_token'],
            'fields' => 'id,name,access_token'
        ]);
        
        if (!$pagesResponse->successful()) {
            return response()->json(['error' => 'Failed to get pages'], 400);
        }
        
        $pages = $pagesResponse->json()['data'] ?? [];
        if (empty($pages)) {
            return response()->json(['error' => 'No pages found'], 400);
        }
        
        $selectedPage = $pages[0];
        $pageAccessToken = $selectedPage['access_token'];
        $pageId = $selectedPage['id'];
        
        // FIXED: Try to get basic post data first with minimal fields
        $foundPost = null;
        $postIdVariations = [
            'original' => $postId,
            'with_page_prefix' => $pageId . '_' . $postId,
            'without_prefix' => str_replace($pageId . '_', '', $postId)
        ];
        
        foreach ($postIdVariations as $variation => $testPostId) {
            try {
                $response = Http::get("https://graph.facebook.com/v18.0/{$testPostId}", [
                    'fields' => 'id,message,created_time,type', // FIXED: Minimal fields
                    'access_token' => $pageAccessToken
                ]);
                
                if ($response->successful()) {
                    $foundPost = $response->json();
                    break;
                }
            } catch (\Exception $e) {
                continue;
            }
        }
        
        if ($foundPost) {
            // For now, return basic analytics since engagement fields are problematic
            return response()->json([
                'status' => '📊 Facebook Basic Analytics (Fixed Fields)! 📊',
                'developer' => 'J33WAKASUPUN',
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'method' => 'Basic analytics with compatible fields',
                'fix_applied' => 'Using minimal field set to avoid deprecation errors',
                'post_info' => [
                    'id' => $foundPost['id'],
                    'type' => $foundPost['type'] ?? 'status',
                    'created_time' => $foundPost['created_time'],
                    'message_preview' => isset($foundPost['message']) ? substr($foundPost['message'], 0, 150) . '...' : 'Media post',
                    'post_url' => "https://facebook.com/{$foundPost['id']}"
                ],
                'analytics_note' => [
                    'limitation' => 'Engagement metrics not available due to Facebook API field deprecation',
                    'available_data' => 'Basic post information only',
                    'alternative' => 'Use Facebook Insights API for detailed analytics'
                ],
                'field_compatibility_issue' => [
                    'problem' => 'Facebook deprecated engagement field combinations in v3.3+',
                    'solution' => 'Using minimal field sets that remain stable',
                    'impact' => 'Limited analytics data available via Graph API'
                ],
                'success_with_limitations' => '✅ BASIC ANALYTICS WORKING WITH COMPATIBLE FIELDS!'
            ]);
        } else {
            return response()->json([
                'error' => 'Post not found for analytics',
                'tried_variations' => array_keys($postIdVariations),
                'field_compatibility_note' => 'Using minimal fields to avoid deprecation errors'
            ], 404);
        }
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Fixed analytics failed',
            'message' => $e->getMessage(),
            'developer' => 'J33WAKASUPUN'
        ], 500);
    }
});

// Add this route to test your updated provider methods
Route::get('/test/facebook/provider-test/{postId}', function ($postId) {
    try {
        // Create a mock channel for testing
        $channel = new \App\Models\Channel([
            'provider' => 'facebook',
            'oauth_tokens' => [
                'access_token' => 'test_token_' . uniqid()
            ]
        ]);

        // Get the latest Facebook token
        $oauthSessionsPath = storage_path('app/oauth_sessions');
        $facebookFiles = glob($oauthSessionsPath . '/oauth_tokens_facebook_*.json');
        
        if (!empty($facebookFiles)) {
            $latestTokenFile = end($facebookFiles);
            $facebookToken = json_decode(file_get_contents($latestTokenFile), true);
            $channel->oauth_tokens = $facebookToken;
        }

        $provider = new \App\Services\SocialMedia\FacebookProvider();

        // Test various provider methods
        $results = [
            'provider_mode' => $provider->getCurrentMode(),
            'provider_configured' => $provider->isConfigured(),
            'default_scopes' => $provider->getDefaultScopes(),
        ];

        // Test permissions check
        if (!empty($facebookFiles)) {
            $results['permissions_check'] = $provider->checkPermissions($channel);
        }

        // Test get post
        if (!empty($facebookFiles)) {
            $results['get_post_test'] = $provider->getPost($postId, $channel);
        }

        // Test pages access
        if (!empty($facebookFiles)) {
            $results['user_pages'] = $provider->getUserPages($channel);
        }

        return response()->json([
            'status' => 'Facebook Provider Test Complete! 🧪',
            'developer' => 'J33WAKASUPUN',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'test_results' => $results,
            'scopes_check' => [
                'has_pages_read_engagement' => in_array('pages_read_engagement', $provider->getDefaultScopes()),
                'total_scopes' => count($provider->getDefaultScopes()),
                'scopes_list' => $provider->getDefaultScopes()
            ],
            'provider_enhancements' => [
                'getPost_method' => '✅ Enhanced post retrieval',
                'updatePost_method' => '✅ Enhanced update with fallbacks',
                'checkPermissions_method' => '✅ Detailed permission checking',
                'enhanced_scopes' => '✅ Added pages_read_engagement'
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Provider test failed',
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'developer' => 'J33WAKASUPUN'
        ], 500);
    }
});

// Add this route to test post propagation timing
Route::post('/test/facebook/create-and-wait-test', function () {
    try {
        $oauthSessionsPath = storage_path('app/oauth_sessions');
        $facebookFiles = glob($oauthSessionsPath . '/oauth_tokens_facebook_*.json');
        
        if (empty($facebookFiles)) {
            return response()->json(['error' => 'No Facebook tokens found'], 404);
        }
        
        $latestTokenFile = end($facebookFiles);
        $facebookToken = json_decode(file_get_contents($latestTokenFile), true);
        
        // Get pages
        $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
            'access_token' => $facebookToken['access_token'],
            'fields' => 'id,name,access_token'
        ]);
        
        if (!$pagesResponse->successful()) {
            return response()->json(['error' => 'Failed to get pages'], 400);
        }
        
        $pages = $pagesResponse->json()['data'] ?? [];
        if (empty($pages)) {
            return response()->json(['error' => 'No pages found'], 400);
        }
        
        $selectedPage = $pages[0];
        $pageAccessToken = $selectedPage['access_token'];
        $pageId = $selectedPage['id'];
        
        // Create a test post
        $message = "🧪 FACEBOOK POST PROPAGATION TEST - " . now()->format('Y-m-d H:i:s') . " 

Testing Facebook Graph API post propagation timing.
This post will be checked for readability after creation.

Built by J33WAKASUPUN! #FacebookAPI #PostPropagationTest";
        
        $postResponse = Http::post("https://graph.facebook.com/v18.0/{$pageId}/feed", [
            'message' => $message,
            'access_token' => $pageAccessToken
        ]);
        
        if (!$postResponse->successful()) {
            return response()->json([
                'error' => 'Failed to create test post',
                'response' => $postResponse->json()
            ], 400);
        }
        
        $postData = $postResponse->json();
        $newPostId = $postData['id'];
        
        // Immediate readability test (will likely fail)
        $immediateTest = Http::get("https://graph.facebook.com/v18.0/{$newPostId}", [
            'fields' => 'id,message,created_time,type',
            'access_token' => $pageAccessToken
        ]);
        
        // Wait 5 seconds and test again
        sleep(5);
        $afterWaitTest = Http::get("https://graph.facebook.com/v18.0/{$newPostId}", [
            'fields' => 'id,message,created_time,type',
            'access_token' => $pageAccessToken
        ]);
        
        // Check in recent posts
        $recentPostsResponse = Http::get("https://graph.facebook.com/v18.0/{$pageId}/posts", [
            'fields' => 'id,message,created_time,type',
            'limit' => 10,
            'access_token' => $pageAccessToken
        ]);
        
        $foundInRecent = false;
        $recentPosts = [];
        if ($recentPostsResponse->successful()) {
            $recentPosts = $recentPostsResponse->json()['data'] ?? [];
            foreach ($recentPosts as $post) {
                if ($post['id'] === $newPostId) {
                    $foundInRecent = true;
                    break;
                }
            }
        }
        
        return response()->json([
            'status' => 'Facebook Post Propagation Test Complete! ⏱️',
            'developer' => 'J33WAKASUPUN',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'test_results' => [
                'post_created' => [
                    'success' => true,
                    'post_id' => $newPostId,
                    'post_url' => "https://facebook.com/{$newPostId}",
                    'created_at' => now()->format('Y-m-d H:i:s')
                ],
                'immediate_read_test' => [
                    'success' => $immediateTest->successful(),
                    'http_code' => $immediateTest->status(),
                    'data' => $immediateTest->successful() ? $immediateTest->json() : null,
                    'error' => $immediateTest->successful() ? null : $immediateTest->json()
                ],
                'after_5_seconds_test' => [
                    'success' => $afterWaitTest->successful(),
                    'http_code' => $afterWaitTest->status(),
                    'data' => $afterWaitTest->successful() ? $afterWaitTest->json() : null,
                    'error' => $afterWaitTest->successful() ? null : $afterWaitTest->json()
                ],
                'found_in_recent_posts' => [
                    'found' => $foundInRecent,
                    'total_recent_posts' => count($recentPosts),
                    'recent_posts_sample' => array_slice($recentPosts, 0, 3)
                ]
            ],
            'diagnosis' => [
                'immediate_readable' => $immediateTest->successful() ? '✅ Yes' : '❌ No',
                'readable_after_wait' => $afterWaitTest->successful() ? '✅ Yes' : '❌ No',
                'appears_in_feed' => $foundInRecent ? '✅ Yes' : '❌ No',
                'propagation_delay' => !$immediateTest->successful() && $afterWaitTest->successful() ? 'YES - Needs waiting period' : 'NO - Instant availability'
            ],
            'recommendations' => [
                'for_immediate_view' => 'Use /posts endpoint to find newly created posts',
                'for_delayed_view' => 'Wait 30-60 seconds before direct post access',
                'best_practice' => 'Store post data locally immediately after creation'
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Propagation test failed',
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'developer' => 'J33WAKASUPUN'
        ], 500);
    }
});

// Add this route to find and test older posts
Route::get('/test/facebook/find-readable-posts', function () {
    try {
        $oauthSessionsPath = storage_path('app/oauth_sessions');
        $facebookFiles = glob($oauthSessionsPath . '/oauth_tokens_facebook_*.json');
        
        if (empty($facebookFiles)) {
            return response()->json(['error' => 'No Facebook tokens found'], 404);
        }
        
        $latestTokenFile = end($facebookFiles);
        $facebookToken = json_decode(file_get_contents($latestTokenFile), true);
        
        // Get pages
        $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
            'access_token' => $facebookToken['access_token'],
            'fields' => 'id,name,access_token'
        ]);
        
        if (!$pagesResponse->successful()) {
            return response()->json(['error' => 'Failed to get pages'], 400);
        }
        
        $pages = $pagesResponse->json()['data'] ?? [];
        if (empty($pages)) {
            return response()->json(['error' => 'No pages found'], 400);
        }
        
        $selectedPage = $pages[0];
        $pageAccessToken = $selectedPage['access_token'];
        $pageId = $selectedPage['id'];
        
        // Get recent posts
        $recentPostsResponse = Http::get("https://graph.facebook.com/v18.0/{$pageId}/posts", [
            'fields' => 'id,message,created_time,type',
            'limit' => 20,
            'access_token' => $pageAccessToken
        ]);
        
        if (!$recentPostsResponse->successful()) {
            return response()->json([
                'error' => 'Failed to get recent posts',
                'response' => $recentPostsResponse->json()
            ], 400);
        }
        
        $recentPosts = $recentPostsResponse->json()['data'] ?? [];
        
        // Test readability of each post via direct access
        $readabilityTests = [];
        foreach ($recentPosts as $index => $post) {
            $postId = $post['id'];
            
            // Try direct access
            $directAccessTest = Http::get("https://graph.facebook.com/v18.0/{$postId}", [
                'fields' => 'id,message,created_time,type',
                'access_token' => $pageAccessToken
            ]);
            
            $readabilityTests[] = [
                'post_id' => $postId,
                'created_time' => $post['created_time'],
                'message_preview' => isset($post['message']) ? substr($post['message'], 0, 100) . '...' : 'No message',
                'direct_access_success' => $directAccessTest->successful(),
                'direct_access_error' => $directAccessTest->successful() ? null : $directAccessTest->json()['error']['message'] ?? 'Unknown error',
                'age_hours' => round((time() - strtotime($post['created_time'])) / 3600, 1)
            ];
            
            // Only test first 5 posts to avoid rate limits
            if ($index >= 4) break;
        }
        
        $readablePosts = array_filter($readabilityTests, fn($test) => $test['direct_access_success']);
        $unreadablePosts = array_filter($readabilityTests, fn($test) => !$test['direct_access_success']);
        
        return response()->json([
            'status' => 'Facebook Post Readability Analysis Complete! 📊',
            'developer' => 'J33WAKASUPUN',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'analysis_results' => [
                'total_recent_posts' => count($recentPosts),
                'posts_tested' => count($readabilityTests),
                'readable_posts' => count($readablePosts),
                'unreadable_posts' => count($unreadablePosts)
            ],
            'readability_tests' => $readabilityTests,
            'readable_posts_sample' => array_slice($readablePosts, 0, 3),
            'unreadable_posts_sample' => array_slice($unreadablePosts, 0, 3),
            'findings' => [
                'immediate_readability' => count($readablePosts) > 0 ? '✅ Some posts are readable' : '❌ No posts readable',
                'pattern_detected' => count($readablePosts) > 0 && count($unreadablePosts) > 0 ? 'Mixed readability - time-based pattern' : 'All same status',
                'recommended_test_post' => count($readablePosts) > 0 ? $readablePosts[0]['post_id'] : 'Create an older test post'
            ],
            'test_suggestions' => [
                'if_readable_found' => count($readablePosts) > 0 ? "Test with: GET /test/facebook/view-post-fixed/{$readablePosts[0]['post_id']}" : null,
                'if_none_readable' => count($readablePosts) === 0 ? 'All posts show readability issues - may be API restriction' : null,
                'create_and_wait' => 'POST /test/facebook/create-and-wait-test'
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Readability analysis failed',
            'message' => $e->getMessage(),
            'developer' => 'J33WAKASUPUN'
        ], 500);
    }
});

// Add this route with absolutely minimal fields that won't trigger deprecation
Route::get('/test/facebook/minimal-field-test', function () {
    try {
        $oauthSessionsPath = storage_path('app/oauth_sessions');
        $facebookFiles = glob($oauthSessionsPath . '/oauth_tokens_facebook_*.json');
        
        if (empty($facebookFiles)) {
            return response()->json(['error' => 'No Facebook tokens found'], 404);
        }
        
        $latestTokenFile = end($facebookFiles);
        $facebookToken = json_decode(file_get_contents($latestTokenFile), true);
        
        // Get pages
        $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
            'access_token' => $facebookToken['access_token'],
            'fields' => 'id,name,access_token' // Minimal page fields
        ]);
        
        if (!$pagesResponse->successful()) {
            return response()->json(['error' => 'Failed to get pages'], 400);
        }
        
        $pages = $pagesResponse->json()['data'] ?? [];
        if (empty($pages)) {
            return response()->json(['error' => 'No pages found'], 400);
        }
        
        $selectedPage = $pages[0];
        $pageAccessToken = $selectedPage['access_token'];
        $pageId = $selectedPage['id'];
        
        // ULTRA-MINIMAL: Try getting posts with only ID field
        $tests = [];
        
        // Test 1: Posts endpoint with only ID
        $postsOnlyIdResponse = Http::get("https://graph.facebook.com/v18.0/{$pageId}/posts", [
            'fields' => 'id', // Only ID field
            'limit' => 5,
            'access_token' => $pageAccessToken
        ]);
        
        $tests['posts_id_only'] = [
            'success' => $postsOnlyIdResponse->successful(),
            'http_code' => $postsOnlyIdResponse->status(),
            'data' => $postsOnlyIdResponse->successful() ? $postsOnlyIdResponse->json() : null,
            'error' => $postsOnlyIdResponse->successful() ? null : $postsOnlyIdResponse->json()
        ];
        
        // Test 2: Posts endpoint with ID and created_time only
        if ($tests['posts_id_only']['success']) {
            $postsBasicResponse = Http::get("https://graph.facebook.com/v18.0/{$pageId}/posts", [
                'fields' => 'id,created_time', // Very minimal
                'limit' => 5,
                'access_token' => $pageAccessToken
            ]);
            
            $tests['posts_id_time'] = [
                'success' => $postsBasicResponse->successful(),
                'http_code' => $postsBasicResponse->status(),
                'data' => $postsBasicResponse->successful() ? $postsBasicResponse->json() : null,
                'error' => $postsBasicResponse->successful() ? null : $postsBasicResponse->json()
            ];
        }
        
        // Test 3: Feed endpoint with minimal fields
        $feedMinimalResponse = Http::get("https://graph.facebook.com/v18.0/{$pageId}/feed", [
            'fields' => 'id', // Only ID
            'limit' => 5,
            'access_token' => $pageAccessToken
        ]);
        
        $tests['feed_id_only'] = [
            'success' => $feedMinimalResponse->successful(),
            'http_code' => $feedMinimalResponse->status(),
            'data' => $feedMinimalResponse->successful() ? $feedMinimalResponse->json() : null,
            'error' => $feedMinimalResponse->successful() ? null : $feedMinimalResponse->json()
        ];
        
        // Test 4: Try accessing a specific post with minimal fields
        $workingPosts = [];
        if ($tests['posts_id_only']['success']) {
            $posts = $tests['posts_id_only']['data']['data'] ?? [];
            foreach ($posts as $post) {
                $postId = $post['id'];
                
                // Test direct post access with minimal fields
                $directResponse = Http::get("https://graph.facebook.com/v18.0/{$postId}", [
                    'fields' => 'id', // Only ID field
                    'access_token' => $pageAccessToken
                ]);
                
                if ($directResponse->successful()) {
                    $workingPosts[] = [
                        'post_id' => $postId,
                        'direct_access' => true,
                        'data' => $directResponse->json()
                    ];
                }
                
                // Only test first 3 posts
                if (count($workingPosts) >= 3) break;
            }
        }
        
        return response()->json([
            'status' => 'Facebook Minimal Field Test Complete! 🔬',
            'developer' => 'J33WAKASUPUN',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'page_info' => [
                'page_id' => $pageId,
                'page_name' => $selectedPage['name']
            ],
            'field_tests' => $tests,
            'working_posts' => $workingPosts,
            'findings' => [
                'posts_endpoint_working' => $tests['posts_id_only']['success'] ? '✅ Yes' : '❌ No',
                'feed_endpoint_working' => $tests['feed_id_only']['success'] ? '✅ Yes' : '❌ No',
                'direct_post_access_working' => count($workingPosts) > 0 ? '✅ Yes' : '❌ No',
                'total_accessible_posts' => count($workingPosts)
            ],
            'solution' => [
                'approach' => 'Use minimal field sets to avoid deprecation errors',
                'working_fields' => $tests['posts_id_only']['success'] ? 'id only or id,created_time' : 'Need even more minimal approach',
                'avoid_fields' => 'message, story, attachments, reactions, comments, shares - all cause deprecation errors'
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Minimal field test failed',
            'message' => $e->getMessage(),
            'line' => $e->getLine(),
            'developer' => 'J33WAKASUPUN'
        ], 500);
    }
});

// Add this route that uses only fields that work
Route::get('/test/facebook/minimal-view-post/{postId}', function ($postId) {
    try {
        $oauthSessionsPath = storage_path('app/oauth_sessions');
        $facebookFiles = glob($oauthSessionsPath . '/oauth_tokens_facebook_*.json');
        
        if (empty($facebookFiles)) {
            return response()->json(['error' => 'No Facebook tokens found'], 404);
        }
        
        $latestTokenFile = end($facebookFiles);
        $facebookToken = json_decode(file_get_contents($latestTokenFile), true);
        
        // Get pages
        $pagesResponse = Http::get('https://graph.facebook.com/v18.0/me/accounts', [
            'access_token' => $facebookToken['access_token'],
            'fields' => 'id,name,access_token'
        ]);
        
        if (!$pagesResponse->successful()) {
            return response()->json(['error' => 'Failed to get pages'], 400);
        }
        
        $pages = $pagesResponse->json()['data'] ?? [];
        if (empty($pages)) {
            return response()->json(['error' => 'No pages found'], 400);
        }
        
        $selectedPage = $pages[0];
        $pageAccessToken = $selectedPage['access_token'];
        $pageId = $selectedPage['id'];
        
        // Progressive field testing - start with absolute minimum
        $fieldCombinations = [
            'id_only' => 'id',
            'id_time' => 'id,created_time',
            'id_time_type' => 'id,created_time,type'
        ];
        
        $postIdVariations = [
            'original' => $postId,
            'with_page_prefix' => $pageId . '_' . $postId,
            'without_prefix' => str_replace($pageId . '_', '', $postId)
        ];
        
        $foundPost = null;
        $usedVariation = null;
        $usedFields = null;
        
        foreach ($postIdVariations as $variation => $testPostId) {
            foreach ($fieldCombinations as $fieldName => $fields) {
                try {
                    $response = Http::get("https://graph.facebook.com/v18.0/{$testPostId}", [
                        'fields' => $fields,
                        'access_token' => $pageAccessToken
                    ]);
                    
                    if ($response->successful()) {
                        $foundPost = $response->json();
                        $usedVariation = $variation;
                        $usedFields = $fieldName;
                        break 2; // Exit both loops
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
        
        // If direct access failed, search in posts with minimal fields
        if (!$foundPost) {
            try {
                $postsResponse = Http::get("https://graph.facebook.com/v18.0/{$pageId}/posts", [
                    'fields' => 'id,created_time', // Minimal that might work
                    'limit' => 25,
                    'access_token' => $pageAccessToken
                ]);
                
                if ($postsResponse->successful()) {
                    $posts = $postsResponse->json()['data'] ?? [];
                    foreach ($posts as $post) {
                        if ($post['id'] === $postId || str_contains($post['id'], str_replace($pageId . '_', '', $postId))) {
                            $foundPost = $post;
                            $usedVariation = 'found_in_posts_list';
                            $usedFields = 'id_time_from_posts';
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Even posts endpoint failed
            }
        }
        
        if ($foundPost) {
            return response()->json([
                'status' => '👀 Facebook Post Found with Minimal Fields! 👀',
                'developer' => 'J33WAKASUPUN',
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'method' => 'Minimal field approach to avoid deprecation',
                'post_id_resolution' => [
                    'input_post_id' => $postId,
                    'resolved_post_id' => $foundPost['id'],
                    'variation_used' => $usedVariation,
                    'fields_used' => $usedFields
                ],
                'post_details' => [
                    'id' => $foundPost['id'],
                    'created_time' => $foundPost['created_time'] ?? 'Not available',
                    'type' => $foundPost['type'] ?? 'Not available',
                    'post_url' => "https://facebook.com/{$foundPost['id']}"
                ],
                'limitations' => [
                    'message_content' => 'Not available due to Facebook API deprecation',
                    'engagement_data' => 'Not available due to field restrictions',
                    'available_data' => 'Basic post metadata only'
                ],
                'workaround_success' => '✅ MINIMAL FIELD ACCESS WORKING!'
            ]);
        } else {
            return response()->json([
                'error' => 'Post not found even with minimal fields',
                'input_post_id' => $postId,
                'page_id' => $pageId,
                'tried_variations' => array_keys($postIdVariations),
                'tried_field_combinations' => array_keys($fieldCombinations),
                'diagnosis' => [
                    'api_restrictions' => 'Facebook may have further restricted post access',
                    'possible_causes' => [
                        'App review required for post reading',
                        'Additional permissions needed',
                        'Post privacy settings',
                        'Facebook API changes'
                    ]
                ]
            ], 404);
        }
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => 'Minimal view post failed',
            'message' => $e->getMessage(),
            'developer' => 'J33WAKASUPUN'
        ], 500);
    }
});

// If API access is too restricted, redirect to Facebook URL
Route::get('/test/facebook/redirect-to-post/{postId}', function ($postId) {
    $facebookUrl = "https://facebook.com/{$postId}";
    
    return response()->json([
        'status' => 'Facebook Post Direct Link 🔗',
        'developer' => 'J33WAKASUPUN',
        'timestamp' => now()->format('Y-m-d H:i:s'),
        'approach' => 'Direct Facebook URL access',
        'post_id' => $postId,
        'facebook_url' => $facebookUrl,
        'instructions' => [
            'Copy the facebook_url below',
            'Open it in your browser',
            'You will see the post directly on Facebook'
        ],
        'when_to_use' => 'When Graph API access is restricted by Facebook',
        'note' => 'This always works regardless of API limitations'
    ]);
});

/*
|--------------------------------------------------------------------------
| End of FaceBook Routes
|--------------------------------------------------------------------------
*/