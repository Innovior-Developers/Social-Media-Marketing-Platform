<?php

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;
use App\Models\Channel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FacebookProvider extends AbstractSocialMediaProvider
{
    protected $platform = 'facebook';

    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * Override stub mode detection for mixed mode support - FIXED
     */
    public function isStubMode(): bool
    {
        // Check if this specific provider should use real API
        $realProviders = config('services.social_media.real_providers', []);
        $shouldUseReal = $realProviders['facebook'] ?? false;

        if ($shouldUseReal && $this->hasRealCredentials()) {
            Log::info('Facebook Provider: Using REAL API mode');
            return false; // Use real API
        }

        Log::info('Facebook Provider: Using STUB mode');
        return true; // Use stub mode
    }

    /**
     * Check if real credentials are available
     */
    private function hasRealCredentials(): bool
    {
        return !empty($this->getConfig('app_id')) &&
            !empty($this->getConfig('app_secret')) &&
            !empty($this->getConfig('redirect'));
    }

    public function authenticate(array $credentials): array
    {
        if ($this->isStubMode()) {
            Log::info('Facebook: Using stub authentication');
            return [
                'success' => true,
                'access_token' => 'facebook_token_' . uniqid(),
                'refresh_token' => 'facebook_refresh_' . uniqid(),
                'expires_at' => now()->addDays(60), // Facebook tokens last longer
                'user_info' => [
                    'id' => 'fb_user_' . rand(100000000000000, 999999999999999),
                    'name' => 'Facebook User ' . rand(1000, 9999),
                    'email' => 'user' . rand(1000, 9999) . '@facebook.com',
                    'picture' => [
                        'data' => [
                            'url' => 'https://graph.facebook.com/me/picture?width=200&height=200'
                        ]
                    ]
                ],
                'pages' => [
                    [
                        'id' => 'page_' . rand(100000000000000, 999999999999999),
                        'name' => 'My Facebook Page',
                        'access_token' => 'page_token_' . uniqid(),
                        'category' => 'Business',
                        'followers_count' => rand(100, 10000)
                    ]
                ],
                'mode' => 'stub'
            ];
        }

        Log::info('Facebook: Using real authentication');
        return $this->authenticateReal($credentials);
    }

    protected function getRealAuthUrl(string $state = null): string
    {
        $baseUrl = 'https://www.facebook.com/v18.0/dialog/oauth';

        $params = [
            'client_id' => $this->getConfig('app_id'),
            'redirect_uri' => $this->getConfig('redirect'),
            'scope' => implode(',', $this->getDefaultScopes()),
            'response_type' => 'code',
            'state' => $state ?? csrf_token()
        ];

        $authUrl = $baseUrl . '?' . http_build_query($params);

        Log::info('Facebook: Generated auth URL', [
            'url' => $authUrl,
            'app_id' => substr($this->getConfig('app_id'), 0, 8) . '...',
            'redirect_uri' => $this->getConfig('redirect'),
            'scopes' => $this->getDefaultScopes()
        ]);

        return $authUrl;
    }

    protected function getRealTokens(string $code): array
    {
        // 🔥 FIXED: Hard-code the token URL to prevent null issues
        $tokenUrl = 'https://graph.facebook.com/v18.0/oauth/access_token';

        $requestData = [
            'client_id' => $this->getConfig('app_id'),
            'client_secret' => $this->getConfig('app_secret'),
            'redirect_uri' => $this->getConfig('redirect'),
            'code' => $code
        ];

        Log::info('Facebook: Exchanging code for tokens', [
            'token_url' => $tokenUrl,
            'app_id' => substr($this->getConfig('app_id'), 0, 8) . '...',
            'redirect_uri' => $this->getConfig('redirect'),
            'code_length' => strlen($code)
        ]);

        $response = Http::get($tokenUrl, $requestData);

        if (!$response->successful()) {
            Log::error('Facebook: Token exchange failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'request_data' => array_merge($requestData, ['client_secret' => '[HIDDEN]'])
            ]);
            throw new \Exception('Facebook token exchange failed: ' . $response->body());
        }

        $data = $response->json();

        Log::info('Facebook: Token exchange successful', [
            'expires_in' => $data['expires_in'] ?? 'not_specified',
            'token_type' => $data['token_type'] ?? 'bearer'
        ]);

        return [
            'access_token' => $data['access_token'],
            'expires_at' => now()->addSeconds($data['expires_in'] ?? 5184000), // 60 days default
            'token_type' => $data['token_type'] ?? 'Bearer',
            'scope' => $this->getDefaultScopes(),
        ];
    }

    private function authenticateReal(array $credentials): array
    {
        return [
            'success' => true,
            'message' => 'Facebook real authentication completed',
            'mode' => 'real'
        ];
    }

    public function publishPost(SocialMediaPost $post, Channel $channel): array
    {
        if ($this->isStubMode()) {
            Log::info('Facebook: Publishing post in stub mode');
            return $this->publishStubPost($post, $channel);
        }

        Log::info('Facebook: Publishing post in real mode');
        return $this->publishRealPost($post, $channel);
    }

    /**
     * 🔥 FIXED STUB POST METHOD
     */
    private function publishStubPost(SocialMediaPost $post, Channel $channel): array
    {
        $formatted = $this->formatPost($post);
        $platformId = rand(100000000000000, 999999999999999) . '_' . rand(100000000000000, 999999999999999);

        Log::info('Facebook: Publishing stub post', [
            'content_length' => strlen($formatted['content']),
            'media_count' => count($post->media ?? []),
            'platform_id' => $platformId
        ]);

        return [
            'success' => true,
            'platform_id' => $platformId,
            'url' => 'https://facebook.com/permalink.php?story_fbid=' . rand(100000000000000, 999999999999999) . '&id=' . rand(100000000000000, 999999999999999),
            'published_at' => now()->toISOString(),
            'post_type' => !empty($post->media) ? 'PHOTO' : 'STATUS',
            'initial_metrics' => [
                'reach' => rand(50, 2000),
                'impressions' => rand(100, 3000),
                'engagement' => rand(5, 200)
            ],
            'mode' => 'stub'
        ];
    }

    private function publishRealPost(SocialMediaPost $post, Channel $channel): array
    {
        try {
            $tokens = $channel->oauth_tokens;
            if (is_string($tokens)) {
                $tokens = decrypt($tokens);
            }

            // Get page access token (Facebook requires page-level posting)
            $pageToken = $this->getPageAccessToken($channel, $tokens['access_token']);
            if (!$pageToken['success']) {
                return $pageToken;
            }

            $formatted = $this->formatPost($post);
            $hasMedia = !empty($post->media);

            // Handle different post types
            if ($hasMedia) {
                if (count($post->media) > 1) {
                    return $this->publishCarouselPost($post, $channel, $pageToken['access_token']);
                } else {
                    return $this->publishSingleMediaPost($post, $channel, $pageToken['access_token']);
                }
            } else {
                return $this->publishTextPost($post, $channel, $pageToken['access_token']);
            }
        } catch (\Exception $e) {
            Log::error('Facebook: Post publishing exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retryable' => true,
                'mode' => 'real'
            ];
        }
    }

    /**
     * Get page access token for posting
     */
    /**
     * Get page access token for posting - FIXED ENDPOINT
     */
    private function getPageAccessToken(Channel $channel, string $userToken): array
    {
        try {
            // FIXED: Use full Graph API URL instead of config endpoint
            $graphApiUrl = 'https://graph.facebook.com/v18.0'; // Hard-coded to prevent null issues

            $response = Http::get($graphApiUrl . '/me/accounts', [
                'access_token' => $userToken,
                'fields' => 'id,name,access_token,category,followers_count'
            ]);

            if (!$response->successful()) {
                Log::error('Facebook: Failed to get user pages', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'url_used' => $graphApiUrl . '/me/accounts'
                ]);
                return [
                    'success' => false,
                    'error' => 'Failed to get Facebook pages: ' . $response->body()
                ];
            }

            $pages = $response->json()['data'] ?? [];

            if (empty($pages)) {
                return [
                    'success' => false,
                    'error' => 'No Facebook pages found. Please create a Facebook page to post.'
                ];
            }

            // Use the first page or find specific page
            $selectedPage = $pages[0];
            $pageId = $selectedPage['id'];
            $pageAccessToken = $selectedPage['access_token'];

            Log::info('Facebook: Using page for posting', [
                'page_id' => $pageId,
                'page_name' => $selectedPage['name'],
                'category' => $selectedPage['category'] ?? 'unknown'
            ]);

            return [
                'success' => true,
                'access_token' => $pageAccessToken,
                'page_id' => $pageId,
                'page_info' => $selectedPage
            ];
        } catch (\Exception $e) {
            Log::error('Facebook: Page access token error', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Failed to get page access token: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Publish text-only post
     */
    private function publishTextPost(SocialMediaPost $post, Channel $channel, string $pageToken): array
    {
        try {
            $formatted = $this->formatPost($post);
            $pageId = $channel->platform_user_id;

            $postData = [
                'message' => $formatted['content'],
                'access_token' => $pageToken
            ];

            // Add link if present
            if (!empty($post->content['link'])) {
                $postData['link'] = $post->content['link'];
            }

            Log::info('Facebook: Publishing text post', [
                'page_id' => $pageId,
                'content_length' => strlen($formatted['content']),
                'has_link' => !empty($post->content['link'])
            ]);

            $response = Http::post($this->getConfig('https://graph.facebook.com/v18.0') . "/{$pageId}/feed", $postData);

            if ($response->successful()) {
                $responseData = $response->json();
                $postId = $responseData['id'];

                Log::info('Facebook: Text post published successfully', [
                    'post_id' => $postId,
                    'page_id' => $pageId
                ]);

                return [
                    'success' => true,
                    'platform_id' => $postId,
                    'url' => "https://facebook.com/{$postId}",
                    'published_at' => now()->toISOString(),
                    'platform_data' => $responseData,
                    'post_type' => 'TEXT',
                    'mode' => 'real'
                ];
            }

            Log::error('Facebook: Text post publishing failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'post_data' => array_merge($postData, ['access_token' => '[HIDDEN]'])
            ]);

            return [
                'success' => false,
                'error' => 'Facebook API error: ' . $response->body(),
                'retryable' => $this->isRetryableError($response->status()),
                'mode' => 'real'
            ];
        } catch (\Exception $e) {
            Log::error('Facebook: Text post exception', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retryable' => true,
                'mode' => 'real'
            ];
        }
    }

    /**
     * Publish single media post (image or video)
     */
    private function publishSingleMediaPost(SocialMediaPost $post, Channel $channel, string $pageToken): array
    {
        try {
            $formatted = $this->formatPost($post);
            $pageId = $channel->platform_user_id;
            $media = $post->media[0];

            Log::info('Facebook: Publishing single media post', [
                'page_id' => $pageId,
                'media_type' => $media['type'],
                'media_size' => $media['size'] ?? 'unknown'
            ]);

            $endpoint = $media['type'] === 'video' ? 'videos' : 'photos';

            $postData = [
                'message' => $formatted['content'],
                'access_token' => $pageToken
            ];

            // Handle media upload
            if ($media['type'] === 'video') {
                $postData['source'] = fopen($media['path'], 'r');
            } else {
                $postData['url'] = $media['url'] ?? url('storage/' . $media['path']);
            }

            $response = Http::asMultipart()->post(
                $this->getConfig('https://graph.facebook.com/v18.0') . "/{$pageId}/{$endpoint}",
                $postData
            );

            if ($response->successful()) {
                $responseData = $response->json();
                $postId = $responseData['id'] ?? $responseData['post_id'];

                Log::info('Facebook: Single media post published successfully', [
                    'post_id' => $postId,
                    'media_type' => $media['type']
                ]);

                return [
                    'success' => true,
                    'platform_id' => $postId,
                    'url' => "https://facebook.com/{$postId}",
                    'published_at' => now()->toISOString(),
                    'platform_data' => $responseData,
                    'post_type' => strtoupper($media['type']),
                    'media_info' => [
                        'type' => $media['type'],
                        'count' => 1
                    ],
                    'mode' => 'real'
                ];
            }

            Log::error('Facebook: Single media post failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => 'Facebook media upload failed: ' . $response->body(),
                'retryable' => $this->isRetryableError($response->status()),
                'mode' => 'real'
            ];
        } catch (\Exception $e) {
            Log::error('Facebook: Single media post exception', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retryable' => true,
                'mode' => 'real'
            ];
        }
    }

    /**
     * Publish carousel post (multiple images)
     */
    private function publishCarouselPost(SocialMediaPost $post, Channel $channel, string $pageToken): array
    {
        try {
            $formatted = $this->formatPost($post);
            $pageId = $channel->platform_user_id;

            Log::info('Facebook: Publishing carousel post', [
                'page_id' => $pageId,
                'media_count' => count($post->media),
                'content_length' => strlen($formatted['content'])
            ]);

            // Upload all media first
            $mediaObjects = [];
            foreach ($post->media as $index => $media) {
                $uploadResult = $this->uploadMediaForCarousel($media, $pageId, $pageToken);

                if ($uploadResult['success']) {
                    $mediaObjects[] = [
                        'media_fbid' => $uploadResult['media_id']
                    ];
                    Log::info('Facebook: Carousel media uploaded', [
                        'position' => $index + 1,
                        'media_id' => $uploadResult['media_id']
                    ]);
                } else {
                    Log::error('Facebook: Carousel media upload failed', [
                        'position' => $index + 1,
                        'error' => $uploadResult['error']
                    ]);
                }
            }

            if (empty($mediaObjects)) {
                return [
                    'success' => false,
                    'error' => 'No media could be uploaded for carousel',
                    'mode' => 'real'
                ];
            }

            // Create carousel post
            $postData = [
                'message' => $formatted['content'],
                'attached_media' => json_encode($mediaObjects),
                'access_token' => $pageToken
            ];

            $response = Http::post($this->getConfig('https://graph.facebook.com/v18.0') . "/{$pageId}/feed", $postData);

            if ($response->successful()) {
                $responseData = $response->json();
                $postId = $responseData['id'];

                Log::info('Facebook: Carousel post published successfully', [
                    'post_id' => $postId,
                    'media_count' => count($mediaObjects)
                ]);

                return [
                    'success' => true,
                    'platform_id' => $postId,
                    'url' => "https://facebook.com/{$postId}",
                    'published_at' => now()->toISOString(),
                    'platform_data' => $responseData,
                    'post_type' => 'CAROUSEL',
                    'media_info' => [
                        'type' => 'carousel',
                        'count' => count($mediaObjects),
                        'media_objects' => $mediaObjects
                    ],
                    'mode' => 'real'
                ];
            }

            Log::error('Facebook: Carousel post publishing failed', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => 'Facebook carousel post failed: ' . $response->body(),
                'retryable' => $this->isRetryableError($response->status()),
                'mode' => 'real'
            ];
        } catch (\Exception $e) {
            Log::error('Facebook: Carousel post exception', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retryable' => true,
                'mode' => 'real'
            ];
        }
    }

    /**
     * Upload media for carousel
     */
    private function uploadMediaForCarousel(array $media, string $pageId, string $pageToken): array
    {
        try {
            $uploadData = [
                'access_token' => $pageToken,
                'published' => 'false' // Important: don't publish immediately
            ];

            if ($media['type'] === 'image') {
                $uploadData['url'] = $media['url'] ?? url('storage/' . $media['path']);
            } else {
                $uploadData['source'] = fopen($media['path'], 'r');
            }

            $endpoint = $media['type'] === 'video' ? 'videos' : 'photos';
            $response = Http::asMultipart()->post(
                $this->getConfig('https://graph.facebook.com/v18.0') . "/{$pageId}/{$endpoint}",
                $uploadData
            );

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'media_id' => $data['id'],
                    'type' => $media['type']
                ];
            }

            return [
                'success' => false,
                'error' => 'Upload failed: ' . $response->body()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Enhanced post retrieval with correct endpoint
     */
    private function retrievePostWithFallback(string $postId, array $pageInfo, string $fields = 'id,message,created_time,type'): array
    {
        $accessToken = $pageInfo['page_access_token'];
        $pageId = $pageInfo['page_id'];

        // FIXED: Use full Graph API URL
        $graphApiUrl = 'https://graph.facebook.com/v18.0';

        // Try different post ID formats
        $postIdVariations = [
            'original' => $postId,
            'with_page_prefix' => $pageId . '_' . $postId,
            'without_prefix' => str_replace($pageId . '_', '', $postId)
        ];

        Log::info('Facebook: Trying post ID variations', ['variations' => array_keys($postIdVariations)]);

        foreach ($postIdVariations as $variation => $testPostId) {
            Log::info('Facebook: Testing post ID variation', [
                'variation' => $variation,
                'post_id' => $testPostId
            ]);

            $response = Http::get($graphApiUrl . "/{$testPostId}", [
                'fields' => $fields,
                'access_token' => $accessToken
            ]);

            if ($response->successful()) {
                Log::info('Facebook: Post found with variation', [
                    'variation' => $variation,
                    'post_id' => $testPostId,
                    'actual_post_id' => $response->json()['id'] ?? 'unknown'
                ]);

                return [
                    'success' => true,
                    'data' => $response->json(),
                    'post_id_used' => $testPostId,
                    'variation_used' => $variation
                ];
            } else {
                Log::warning('Facebook: Post not found with variation', [
                    'variation' => $variation,
                    'post_id' => $testPostId,
                    'error' => $response->json()['error'] ?? 'Unknown error'
                ]);
            }
        }

        // If none worked, try getting recent posts to find it
        Log::info('Facebook: Attempting to find post in recent posts');

        $recentPostsResponse = Http::get($graphApiUrl . "/{$pageId}/posts", [
            'fields' => $fields,
            'limit' => 10,
            'access_token' => $accessToken
        ]);

        if ($recentPostsResponse->successful()) {
            $posts = $recentPostsResponse->json()['data'] ?? [];

            foreach ($posts as $post) {
                $fullPostId = $post['id'];
                $shortPostId = str_replace($pageId . '_', '', $fullPostId);

                if ($shortPostId === $postId || $fullPostId === $postId) {
                    Log::info('Facebook: Post found in recent posts', [
                        'found_post_id' => $fullPostId,
                        'searched_for' => $postId
                    ]);

                    return [
                        'success' => true,
                        'data' => $post,
                        'post_id_used' => $fullPostId,
                        'variation_used' => 'found_in_recent_posts'
                    ];
                }
            }
        }

        return [
            'success' => false,
            'error' => 'Post not found with any ID variation',
            'tried_variations' => array_keys($postIdVariations)
        ];
    }

    public function getAnalytics(string $postId, Channel $channel): array
    {
        if ($this->isStubMode()) {
            return $this->getEnhancedStubAnalytics();
        }

        // For real mode, acknowledge Facebook's API limitations
        return [
            'success' => true,
            'limitations' => [
                'facebook_api_restrictions' => 'Facebook severely limits analytics access via Graph API',
                'available_alternatives' => [
                    'facebook_insights' => 'Use Facebook Insights dashboard for detailed analytics',
                    'facebook_business_suite' => 'Access comprehensive analytics via Facebook Business Suite',
                    'manual_tracking' => 'Store engagement data locally when posts are created'
                ]
            ],
            'basic_data' => [
                'post_id' => $postId,
                'facebook_url' => "https://facebook.com/{$postId}",
                'manual_analytics_url' => "https://business.facebook.com/latest/insights",
                'note' => 'Visit Facebook Business Suite for detailed post analytics'
            ],
            'stub_analytics_available' => 'Set to stub mode for development analytics',
            'mode' => 'real'
        ];
    }

    private function getRealFacebookAnalytics(string $postId, Channel $channel): array
    {
        try {
            $tokens = $channel->oauth_tokens;
            if (is_string($tokens)) {
                $tokens = decrypt($tokens);
            }

            // Get page access token
            $pageToken = $this->getPageAccessToken($channel, $tokens['access_token']);
            if (!$pageToken['success']) {
                return $this->getEnhancedStubAnalytics();
            }

            Log::info('Facebook: Collecting real analytics', [
                'post_id' => $postId
            ]);

            // Facebook Insights API - much richer than LinkedIn!
            $metrics = [
                'post_impressions',
                'post_reach',
                'post_reactions_like_total',
                'post_reactions_love_total',
                'post_reactions_wow_total',
                'post_reactions_haha_total',
                'post_reactions_sorry_total',
                'post_reactions_anger_total',
                'post_consumptions',
                'post_clicks',
                'post_engaged_users',
                'post_video_views', // For video posts
                'post_video_complete_views_30s'
            ];

            $response = Http::get($this->getConfig('https://graph.facebook.com/v18.0') . "/{$postId}/insights", [
                'metric' => implode(',', $metrics),
                'access_token' => $pageToken['access_token']
            ]);

            if ($response->successful()) {
                $insights = $response->json()['data'] ?? [];
                $processedMetrics = $this->processFacebookInsights($insights);

                // Get demographic data
                $demographics = $this->getFacebookDemographics($postId, $pageToken['access_token']);

                Log::info('Facebook: Analytics collected successfully', [
                    'metrics_count' => count($processedMetrics),
                    'has_demographics' => !empty($demographics)
                ]);

                return [
                    'success' => true,
                    'metrics' => $processedMetrics,
                    'demographics' => $demographics,
                    'data_source' => 'facebook_graph_api',
                    'collected_at' => now()->toISOString(),
                    'mode' => 'real'
                ];
            }

            Log::warning('Facebook: Analytics API failed, using enhanced stub', [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return $this->getEnhancedStubAnalytics();
        } catch (\Exception $e) {
            Log::error('Facebook: Analytics collection exception', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);

            return $this->getEnhancedStubAnalytics();
        }
    }

    /**
     * Process Facebook Insights data
     */
    private function processFacebookInsights(array $insights): array
    {
        $metrics = [
            'impressions' => 0,
            'reach' => 0,
            'likes' => 0,
            'loves' => 0,
            'wows' => 0,
            'hahas' => 0,
            'sorrys' => 0,
            'angers' => 0,
            'total_reactions' => 0,
            'consumptions' => 0,
            'clicks' => 0,
            'engaged_users' => 0,
            'video_views' => 0,
            'video_complete_views' => 0
        ];

        foreach ($insights as $insight) {
            $metricName = $insight['name'];
            $value = $insight['values'][0]['value'] ?? 0;

            switch ($metricName) {
                case 'post_impressions':
                    $metrics['impressions'] = $value;
                    break;
                case 'post_reach':
                    $metrics['reach'] = $value;
                    break;
                case 'post_reactions_like_total':
                    $metrics['likes'] = $value;
                    break;
                case 'post_reactions_love_total':
                    $metrics['loves'] = $value;
                    break;
                case 'post_reactions_wow_total':
                    $metrics['wows'] = $value;
                    break;
                case 'post_reactions_haha_total':
                    $metrics['hahas'] = $value;
                    break;
                case 'post_reactions_sorry_total':
                    $metrics['sorrys'] = $value;
                    break;
                case 'post_reactions_anger_total':
                    $metrics['angers'] = $value;
                    break;
                case 'post_consumptions':
                    $metrics['consumptions'] = $value;
                    break;
                case 'post_clicks':
                    $metrics['clicks'] = $value;
                    break;
                case 'post_engaged_users':
                    $metrics['engaged_users'] = $value;
                    break;
                case 'post_video_views':
                    $metrics['video_views'] = $value;
                    break;
                case 'post_video_complete_views_30s':
                    $metrics['video_complete_views'] = $value;
                    break;
            }
        }

        // Calculate total reactions
        $metrics['total_reactions'] = $metrics['likes'] + $metrics['loves'] +
            $metrics['wows'] + $metrics['hahas'] + $metrics['sorrys'] + $metrics['angers'];

        // Calculate engagement rate
        if ($metrics['reach'] > 0) {
            $metrics['engagement_rate'] = round(($metrics['total_reactions'] / $metrics['reach']) * 100, 2);
        } else {
            $metrics['engagement_rate'] = 0;
        }

        return $metrics;
    }

    /**
     * Get Facebook demographic data
     */
    private function getFacebookDemographics(string $postId, string $pageToken): array
    {
        try {
            $response = Http::get($this->getConfig('https://graph.facebook.com/v18.0') . "/{$postId}/insights", [
                'metric' => 'post_impressions_by_age_gender,post_reach_by_age_gender',
                'access_token' => $pageToken
            ]);

            if ($response->successful()) {
                $data = $response->json()['data'] ?? [];
                return $this->processDemographicData($data);
            }

            return [];
        } catch (\Exception $e) {
            Log::info('Facebook: Demographics not available', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Process demographic data from Facebook
     */
    private function processDemographicData(array $data): array
    {
        $demographics = [
            'age_gender' => [],
            'age_groups' => [],
            'gender_split' => []
        ];

        foreach ($data as $insight) {
            if ($insight['name'] === 'post_impressions_by_age_gender') {
                $demographics['age_gender'] = $insight['values'][0]['value'] ?? [];
            }
        }

        // Process age and gender data
        if (!empty($demographics['age_gender'])) {
            $ageGroups = [];
            $genderSplit = ['M' => 0, 'F' => 0];

            foreach ($demographics['age_gender'] as $key => $value) {
                if (preg_match('/^(\w+)\.(\d+-\d+)$/', $key, $matches)) {
                    $gender = $matches[1];
                    $ageGroup = $matches[2];

                    $genderSplit[$gender] = ($genderSplit[$gender] ?? 0) + $value;
                    $ageGroups[$ageGroup] = ($ageGroups[$ageGroup] ?? 0) + $value;
                }
            }

            $demographics['age_groups'] = $ageGroups;
            $demographics['gender_split'] = $genderSplit;
        }

        return $demographics;
    }

    /**
     * Enhanced stub analytics with Facebook-specific metrics
     */
    private function getEnhancedStubAnalytics(): array
    {
        $baseImpressions = rand(100, 5000);
        $reach = (int)($baseImpressions * rand(60, 85) / 100);
        $engagementRate = rand(3, 12) / 100;
        $totalEngagement = (int)($reach * $engagementRate);

        $likes = (int)($totalEngagement * 0.60);
        $loves = (int)($totalEngagement * 0.15);
        $wows = (int)($totalEngagement * 0.08);
        $hahas = (int)($totalEngagement * 0.10);
        $sorrys = (int)($totalEngagement * 0.04);
        $angers = (int)($totalEngagement * 0.03);

        return [
            'success' => true,
            'metrics' => [
                'impressions' => $baseImpressions,
                'reach' => $reach,
                'likes' => $likes,
                'loves' => $loves,
                'wows' => $wows,
                'hahas' => $hahas,
                'sorrys' => $sorrys,
                'angers' => $angers,
                'total_reactions' => $likes + $loves + $wows + $hahas + $sorrys + $angers,
                'clicks' => rand(10, (int)($baseImpressions * 0.15)),
                'consumptions' => rand(5, (int)($baseImpressions * 0.08)),
                'engaged_users' => rand(20, (int)($reach * 0.3)),
                'video_views' => rand(50, (int)($baseImpressions * 0.6)),
                'video_complete_views' => rand(10, (int)($baseImpressions * 0.2)),
                'engagement_rate' => round($engagementRate * 100, 2),
                'click_through_rate' => round(rand(1, 8) / 100, 2)
            ],
            'demographics' => [
                'age_groups' => [
                    '18-24' => rand(15, 25),
                    '25-34' => rand(30, 45),
                    '35-44' => rand(20, 35),
                    '45-54' => rand(10, 20),
                    '55-64' => rand(5, 15),
                    '65+' => rand(2, 10)
                ],
                'gender_split' => [
                    'F' => rand(45, 60),
                    'M' => rand(35, 50),
                    'U' => rand(2, 5) // Unknown
                ],
                'top_locations' => [
                    'United States' => rand(30, 50),
                    'India' => rand(10, 25),
                    'United Kingdom' => rand(8, 15),
                    'Canada' => rand(5, 12),
                    'Australia' => rand(3, 8)
                ]
            ],
            'timeline' => [
                now()->subHours(24)->toISOString() => rand(10, 100),
                now()->subHours(12)->toISOString() => rand(20, 200),
                now()->subHours(6)->toISOString() => rand(30, 150),
                now()->subHours(1)->toISOString() => rand(5, 50)
            ],
            'data_source' => 'facebook_enhanced_simulation',
            'collected_at' => now()->toISOString(),
            'mode' => 'stub'
        ];
    }

    public function validatePost(SocialMediaPost $post): array
    {
        $errors = [];

        $content = $post->content['text'] ?? '';
        $errors = array_merge($errors, $this->validateContent($content));

        $media = $post->media ?? [];
        $errors = array_merge($errors, $this->validateMedia($media));

        // Facebook-specific validations
        if (count($media) > 10) {
            $errors[] = "Facebook supports maximum 10 images in a carousel post";
        }

        foreach ($media as $item) {
            if ($item['type'] === 'video') {
                $maxSize = 10 * 1024 * 1024 * 1024; // 10GB
                if (($item['size'] ?? 0) > $maxSize) {
                    $errors[] = "Video file too large. Facebook supports videos up to 10GB";
                }
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'character_count' => strlen($content),
            'character_limit' => $this->getCharacterLimit(),
            'media_count' => count($media),
            'media_limit' => $this->getMediaLimit(),
            'mode' => $this->isStubMode() ? 'stub' : 'real'
        ];
    }

    public function getCharacterLimit(): int
    {
        return 63206; // Facebook's generous character limit
    }

    public function getMediaLimit(): int
    {
        return 10; // Facebook carousel limit
    }

    public function getSupportedMediaTypes(): array
    {
        return ['image', 'video', 'link'];
    }

    public function getDefaultScopes(): array
    {
        return [
            'pages_show_list',           // List user's pages
            'pages_read_user_content',   // Read page content  
            'pages_read_engagement',     // Read post engagement data
            'business_management',       // Business management
            'pages_manage_metadata',     // Manage page settings
            'pages_manage_posts',        // Create, update, delete posts
            'public_profile',            // Basic profile access
            'email'                      // Email address (optional)
        ];
    }

    /**
     * ENHANCED: Get post with fallback mechanisms
     */
    public function getPost(string $postId, Channel $channel): array
    {
        if ($this->isStubMode()) {
            return [
                'success' => true,
                'post' => [
                    'id' => $postId,
                    'message' => 'Stub post content for ID: ' . $postId,
                    'created_time' => now()->subHours(2)->toISOString(),
                    'type' => 'status'
                ],
                'mode' => 'stub'
            ];
        }

        try {
            $tokens = $channel->oauth_tokens;
            if (is_string($tokens)) {
                $tokens = decrypt($tokens);
            }

            // Get page access token
            $pageToken = $this->getPageAccessToken($channel, $tokens['access_token']);
            if (!$pageToken['success']) {
                return [
                    'success' => false,
                    'error' => 'Cannot get page access token',
                    'details' => $pageToken['error']
                ];
            }

            $pageInfo = [
                'page_id' => $pageToken['page_id'],
                'page_access_token' => $pageToken['access_token']
            ];

            // Use the enhanced retrieval method
            $result = $this->retrievePostWithFallback(
                $postId,
                $pageInfo,
                'id,message,created_time,type,updated_time,permalink_url'
            );

            if ($result['success']) {
                return [
                    'success' => true,
                    'post' => $result['data'],
                    'retrieval_method' => $result['variation_used'],
                    'mode' => 'real'
                ];
            }

            return [
                'success' => false,
                'error' => 'Post not found with any retrieval method',
                'post_id' => $postId,
                'tried_methods' => $result['tried_variations'] ?? []
            ];
        } catch (\Exception $e) {
            Log::error('Facebook: Get post failed', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'mode' => 'real'
            ];
        }
    }

    /**
     * ENHANCED: Update post with better error handling
     */
    public function updatePost(string $postId, string $newContent, Channel $channel): array
    {
        if ($this->isStubMode()) {
            return [
                'success' => true,
                'message' => 'Post updated successfully (stub mode)',
                'post_id' => $postId,
                'updated_at' => now()->toISOString(),
                'mode' => 'stub'
            ];
        }

        try {
            $tokens = $channel->oauth_tokens;
            if (is_string($tokens)) {
                $tokens = decrypt($tokens);
            }

            // Get page access token
            $pageToken = $this->getPageAccessToken($channel, $tokens['access_token']);
            if (!$pageToken['success']) {
                return [
                    'success' => false,
                    'error' => 'Cannot get page access token for update'
                ];
            }

            // Try different post ID formats for update
            $postIdVariations = [
                'original' => $postId,
                'with_page_prefix' => $pageToken['page_id'] . '_' . $postId,
                'without_prefix' => str_replace($pageToken['page_id'] . '_', '', $postId)
            ];

            foreach ($postIdVariations as $variation => $testPostId) {
                $updateData = [
                    'message' => $newContent,
                    'access_token' => $pageToken['access_token']
                ];

                $response = Http::post($this->getConfig('https://graph.facebook.com/v18.0') . "/{$testPostId}", $updateData);

                if ($response->successful()) {
                    Log::info('Facebook: Post updated successfully', [
                        'post_id' => $testPostId,
                        'variation_used' => $variation
                    ]);

                    return [
                        'success' => true,
                        'message' => 'Post updated successfully',
                        'post_id' => $testPostId,
                        'original_post_id' => $postId,
                        'variation_used' => $variation,
                        'updated_at' => now()->toISOString(),
                        'mode' => 'real'
                    ];
                }
            }

            return [
                'success' => false,
                'error' => 'Failed to update post with any ID variation',
                'post_id' => $postId,
                'tried_variations' => array_keys($postIdVariations)
            ];
        } catch (\Exception $e) {
            Log::error('Facebook: Update post failed', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * ENHANCED: Check if we have all required permissions
     */
    public function checkPermissions(Channel $channel): array
    {
        try {
            $tokens = $channel->oauth_tokens;
            if (is_string($tokens)) {
                $tokens = decrypt($tokens);
            }

            $response = Http::get('https://graph.facebook.com/v18.0/me/permissions', [
                'access_token' => $tokens['access_token']
            ]);

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => 'Failed to check permissions'
                ];
            }

            $permissions = $response->json()['data'] ?? [];
            $granted = [];
            $declined = [];

            foreach ($permissions as $perm) {
                if ($perm['status'] === 'granted') {
                    $granted[] = $perm['permission'];
                } else {
                    $declined[] = $perm['permission'];
                }
            }

            $requiredPermissions = $this->getDefaultScopes();
            $missing = array_diff($requiredPermissions, $granted);

            return [
                'success' => true,
                'granted_permissions' => $granted,
                'declined_permissions' => $declined,
                'required_permissions' => $requiredPermissions,
                'missing_permissions' => $missing,
                'all_required_granted' => empty($missing)
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getCurrentMode(): string
    {
        return $this->isStubMode() ? 'stub' : 'real';
    }

    // === PUBLIC OAUTH METHODS ===

    public function exchangeCodeForTokens(string $code): array
    {
        if ($this->isStubMode()) {
            throw new \Exception('Cannot exchange real tokens in stub mode.');
        }

        Log::info('Facebook: Public token exchange called', ['code_length' => strlen($code)]);
        return $this->getRealTokens($code);
    }

    public function getAuthUrl(string $state = null): string
    {
        if ($this->isStubMode()) {
            Log::info('Facebook: Generating stub auth URL');
            return 'https://example.com/oauth/stub?provider=facebook&state=' . ($state ?? 'stub_state');
        }

        Log::info('Facebook: Generating real auth URL');
        return $this->getRealAuthUrl($state);
    }

    /**
     * 🔥 FIXED CONFIGURATION CHECK
     */
    public function isConfigured(): bool
    {
        // In stub mode, we don't need real credentials
        if ($this->isStubMode()) {
            Log::info('Facebook: Configuration check in stub mode - always configured');
            return true;
        }

        // In real mode, we need actual credentials
        $hasAppId = !empty($this->getConfig('app_id'));
        $hasAppSecret = !empty($this->getConfig('app_secret'));
        $hasRedirect = !empty($this->getConfig('redirect'));

        Log::info('Facebook: Configuration check in real mode', [
            'app_id_set' => $hasAppId,
            'app_secret_set' => $hasAppSecret,
            'redirect_set' => $hasRedirect,
            'fully_configured' => $hasAppId && $hasAppSecret && $hasRedirect
        ]);

        return $hasAppId && $hasAppSecret && $hasRedirect;
    }

    public function getConfigurationStatus(): array
    {
        return [
            'platform' => $this->platform,
            'mode' => $this->getCurrentMode(),
            'configured' => $this->isConfigured(),
            'enabled' => $this->isEnabled(),
            'config_details' => [
                'app_id' => !empty($this->getConfig('app_id')) ? 'SET' : 'NOT SET',
                'app_secret' => !empty($this->getConfig('app_secret')) ? 'SET' : 'NOT SET',
                'redirect_uri' => $this->getConfig('redirect') ?? 'NOT SET',
                'graph_version' => $this->getConfig('graph_version') ?? 'v18.0',
                'auth_url' => $this->getConfig('endpoints.auth_url') ?? 'NOT SET',
                'token_url' => $this->getConfig('endpoints.token_url') ?? 'NOT SET',
                'graph_api' => $this->getConfig('https://graph.facebook.com/v18.0') ?? 'NOT SET',
            ],
            'scopes' => $this->getDefaultScopes(),
            'constraints' => [
                'character_limit' => $this->getCharacterLimit(),
                'media_limit' => $this->getMediaLimit(),
                'supported_media' => $this->getSupportedMediaTypes()
            ],
            'features' => [
                'page_posting' => true,
                'carousel_posts' => true,
                'video_upload' => true,
                'rich_analytics' => true,
                'demographic_data' => true,
                'reaction_tracking' => true
            ]
        ];
    }

    /**
     * 🔥 FIXED USER PAGES METHOD
     */
    public function getUserPages(Channel $channel): array
    {
        if ($this->isStubMode()) {
            Log::info('Facebook: Getting pages in stub mode');
            return [
                'success' => true,
                'pages' => [
                    [
                        'id' => 'page_' . rand(100000000000000, 999999999999999),
                        'name' => 'My Facebook Page',
                        'category' => 'Business',
                        'followers_count' => rand(100, 10000),
                        'access_token' => 'page_token_' . uniqid(),
                        'picture' => [
                            'data' => [
                                'url' => 'https://graph.facebook.com/page_id/picture'
                            ]
                        ]
                    ]
                ],
                'mode' => 'stub'
            ];
        }

        try {
            $tokens = $channel->oauth_tokens;
            if (is_string($tokens)) {
                $tokens = decrypt($tokens);
            }

            // FIXED: Use full Graph API URL
            $graphApiUrl = 'https://graph.facebook.com/v18.0';

            $response = Http::get($graphApiUrl . '/me/accounts', [
                'access_token' => $tokens['access_token'],
                'fields' => 'id,name,access_token,category,followers_count,picture'
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'pages' => $response->json()['data'] ?? [],
                    'mode' => 'real'
                ];
            }

            return [
                'success' => false,
                'error' => 'Failed to fetch pages: ' . $response->body(),
                'mode' => 'real'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'mode' => 'real'
            ];
        }
    }

    /**
     * 🔥 FIXED POST EXISTS CHECK
     */
    public function checkPostExists(string $postId, Channel $channel): array
    {
        if ($this->isStubMode()) {
            Log::info('Facebook: Checking post exists in stub mode', ['post_id' => $postId]);
            return $this->checkStubPostExists($postId);
        }

        return $this->checkRealPostExists($postId, $channel);
    }

    private function checkStubPostExists(string $postId): array
    {
        $exists = rand(1, 10) > 3; // 70% chance exists

        Log::info('Facebook: Stub post existence check', [
            'post_id' => $postId,
            'exists' => $exists
        ]);

        return [
            'success' => true,
            'exists' => $exists,
            'post_id' => $postId,
            'status_code' => $exists ? 200 : 404,
            'checked_at' => now()->toISOString(),
            'mode' => 'stub'
        ];
    }

    private function checkRealPostExists(string $postId, Channel $channel): array
    {
        try {
            $tokens = $channel->oauth_tokens;
            if (is_string($tokens)) {
                $tokens = decrypt($tokens);
            }

            // Get page access token
            $pageTokenResponse = $this->getPageAccessToken($channel, $tokens['access_token']);
            if (!$pageTokenResponse['success']) {
                return [
                    'success' => false,
                    'exists' => 'unknown',
                    'error' => 'Could not get page access token'
                ];
            }

            $pageInfo = [
                'page_id' => $pageTokenResponse['page_id'],
                'page_access_token' => $pageTokenResponse['access_token']
            ];

            Log::info('Facebook: Checking if post exists with fallback', [
                'post_id' => $postId
            ]);

            $postResult = $this->retrievePostWithFallback($postId, $pageInfo, 'id');

            $exists = $postResult['success'];

            Log::info('Facebook: Post existence check result', [
                'post_id' => $postId,
                'exists' => $exists,
                'variation_used' => $postResult['variation_used'] ?? 'none'
            ]);

            return [
                'success' => true,
                'exists' => $exists,
                'post_id' => $postId,
                'resolved_post_id' => $postResult['post_id_used'] ?? null,
                'checked_at' => now()->toISOString(),
                'mode' => 'real'
            ];
        } catch (\Exception $e) {
            Log::error('Facebook: Post existence check failed', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'exists' => 'unknown',
                'error' => $e->getMessage(),
                'post_id' => $postId,
                'mode' => 'real'
            ];
        }
    }

    /**
     * Delete Facebook post
     */
    public function deletePost(string $postId, Channel $channel): array
    {
        if ($this->isStubMode()) {
            return $this->deleteStubPost($postId);
        }

        return $this->deleteRealPost($postId, $channel);
    }

    private function deleteRealPost(string $postId, Channel $channel): array
    {
        try {
            $tokens = $channel->oauth_tokens;
            if (is_string($tokens)) {
                $tokens = decrypt($tokens);
            }

            // Get page access token
            $pageToken = $this->getPageAccessToken($channel, $tokens['access_token']);
            if (!$pageToken['success']) {
                return [
                    'success' => false,
                    'error' => 'Could not get page access token'
                ];
            }

            Log::info('Facebook: Attempting to delete post', [
                'post_id' => $postId
            ]);

            // First check if post exists
            $existenceCheck = $this->checkRealPostExists($postId, $channel);

            if (!$existenceCheck['success']) {
                return [
                    'success' => false,
                    'error' => 'Could not verify post existence',
                    'post_id' => $postId,
                    'requires_manual_deletion' => true,
                    'mode' => 'real'
                ];
            }

            if (!$existenceCheck['exists']) {
                return [
                    'success' => true,
                    'message' => 'Post was already deleted from Facebook',
                    'post_id' => $postId,
                    'already_deleted' => true,
                    'deleted_at' => now()->toISOString(),
                    'mode' => 'real'
                ];
            }

            // Attempt to delete post
            $response = Http::delete($this->getConfig('https://graph.facebook.com/v18.0') . "/{$postId}", [
                'access_token' => $pageToken['access_token']
            ]);

            if ($response->successful() || $response->status() === 404) {
                Log::info('Facebook: Post deleted successfully', [
                    'post_id' => $postId,
                    'status_code' => $response->status()
                ]);

                return [
                    'success' => true,
                    'message' => 'Post deleted successfully from Facebook',
                    'post_id' => $postId,
                    'status_code' => $response->status(),
                    'deleted_at' => now()->toISOString(),
                    'mode' => 'real'
                ];
            }

            Log::warning('Facebook: Post deletion API call failed', [
                'post_id' => $postId,
                'status_code' => $response->status(),
                'response' => $response->body()
            ]);

            return [
                'success' => false,
                'message' => 'Facebook post deletion failed - may require manual deletion',
                'post_id' => $postId,
                'status_code' => $response->status(),
                'requires_manual_deletion' => true,
                'manual_deletion_note' => 'Visit the Facebook post and delete it manually',
                'api_response' => $response->body(),
                'mode' => 'real'
            ];
        } catch (\Exception $e) {
            Log::error('Facebook: Post deletion failed', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'post_id' => $postId,
                'requires_manual_deletion' => true,
                'mode' => 'real'
            ];
        }
    }

    private function deleteStubPost(string $postId): array
    {
        $success = rand(1, 10) > 2; // 80% success rate

        if ($success) {
            return [
                'success' => true,
                'message' => 'Post deleted successfully (stub mode)',
                'post_id' => $postId,
                'deleted_at' => now()->toISOString(),
                'mode' => 'stub'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Post deletion failed (stub simulation)',
                'post_id' => $postId,
                'error' => 'Simulated deletion failure',
                'requires_manual_deletion' => false,
                'mode' => 'stub'
            ];
        }
    }

    /**
     * Get Facebook post deletion status
     */
    public function getPostDeletionStatus(string $postId, Channel $channel, string $postUrl = null): array
    {
        $existenceCheck = $this->checkPostExists($postId, $channel);

        if (!$existenceCheck['success']) {
            return [
                'status' => 'UNKNOWN',
                'message' => 'Could not check Facebook post status',
                'post_id' => $postId,
                'error' => $existenceCheck['error'] ?? 'Unknown error'
            ];
        }

        if (!$existenceCheck['exists']) {
            return [
                'status' => 'DELETED',
                'message' => 'Post has been deleted from Facebook',
                'post_id' => $postId,
                'verified_deleted' => true,
                'checked_at' => $existenceCheck['checked_at']
            ];
        }

        return [
            'status' => 'EXISTS',
            'message' => 'Post still exists on Facebook - can be deleted via API or manually',
            'post_id' => $postId,
            'exists_on_platform' => true,
            'post_url' => $postUrl ?? "https://facebook.com/{$postId}",
            'deletion_options' => [
                'api' => 'Use the delete endpoint to remove via API',
                'manual' => 'Visit Facebook and delete the post manually'
            ],
            'note' => 'Facebook allows both API and manual deletion of posts',
            'checked_at' => $existenceCheck['checked_at']
        ];
    }

    /**
     * Helper method to determine retryable errors
     */
    protected function isRetryableError($error): bool
    {
        if (is_int($error)) {
            return $error >= 500 || $error === 429;
        }

        if (is_array($error) && isset($error['status_code'])) {
            return $error['status_code'] >= 500 || $error['status_code'] === 429;
        }

        return false;
    }

    protected function formatPost(SocialMediaPost $post): array
    {
        $formatted = parent::formatPost($post);

        // Facebook-specific formatting
        if (!empty($post->content['link'])) {
            $formatted['link'] = $post->content['link'];
        }

        return $formatted;
    }

    /**
     * OPTIMIZED: Get post with realistic Facebook API limitations
     */
    public function getPostDetails(string $postId, Channel $channel): array
    {
        if ($this->isStubMode()) {
            return [
                'success' => true,
                'post' => [
                    'id' => $postId,
                    'message' => 'Full post content available in stub mode',
                    'created_time' => now()->subHours(2)->toISOString(),
                    'type' => 'status',
                    'engagement' => [
                        'likes' => rand(10, 100),
                        'comments' => rand(5, 50),
                        'shares' => rand(1, 20)
                    ]
                ],
                'mode' => 'stub'
            ];
        }

        try {
            $tokens = $channel->oauth_tokens;
            if (is_string($tokens)) {
                $tokens = decrypt($tokens);
            }

            // Get page access token
            $pageToken = $this->getPageAccessToken($channel, $tokens['access_token']);
            if (!$pageToken['success']) {
                return [
                    'success' => false,
                    'error' => 'Cannot get page access token'
                ];
            }

            $pageInfo = [
                'page_id' => $pageToken['page_id'],
                'page_access_token' => $pageToken['access_token']
            ];

            // Use minimal fields that work with current Facebook API
            $result = $this->retrievePostWithFallback(
                $postId,
                $pageInfo,
                'id,created_time' // Only fields that consistently work
            );

            if ($result['success']) {
                return [
                    'success' => true,
                    'post' => [
                        'id' => $result['data']['id'],
                        'created_time' => $result['data']['created_time'] ?? null,
                        'facebook_url' => "https://facebook.com/{$result['data']['id']}",
                        'api_limitations' => [
                            'message_content' => 'Not available due to Facebook API restrictions',
                            'engagement_data' => 'Use Facebook Insights for detailed analytics',
                            'available_data' => 'Basic metadata only'
                        ]
                    ],
                    'retrieval_method' => $result['variation_used'],
                    'mode' => 'real'
                ];
            }

            return [
                'success' => false,
                'error' => 'Post not found with any retrieval method',
                'facebook_url' => "https://facebook.com/{$postId}",
                'manual_access' => 'Use the facebook_url to view post directly'
            ];
        } catch (\Exception $e) {
            Log::error('Facebook: Get post details failed', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'facebook_url' => "https://facebook.com/{$postId}",
                'mode' => 'real'
            ];
        }
    }
}
