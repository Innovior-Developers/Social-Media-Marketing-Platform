<?php
// Complete LinkedInProvider.php with all methods

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;
use App\Models\Channel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinkedInProvider extends AbstractSocialMediaProvider
{
    protected $platform = 'linkedin';

    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * Override stub mode detection for mixed mode support
     */
    public function isStubMode(): bool
    {
        // Check if this specific provider should use real API
        $realProviders = config('services.social_media.real_providers', []);
        $shouldUseReal = $realProviders['linkedin'] ?? false;

        if ($shouldUseReal) {
            Log::info('LinkedIn Provider: Using REAL API mode');
            return false; // Use real API
        }

        Log::info('LinkedIn Provider: Using STUB mode');
        return true; // Use stub mode
    }

    public function authenticate(array $credentials): array
    {
        if ($this->isStubMode()) {
            Log::info('LinkedIn: Using stub authentication');
            return [
                'success' => true,
                'access_token' => 'linkedin_token_' . uniqid(),
                'refresh_token' => 'linkedin_refresh_' . uniqid(),
                'expires_at' => now()->addDays(365),
                'user_info' => [
                    'username' => 'linkedin_user_' . rand(1000, 9999),
                    'display_name' => 'LinkedIn Professional',
                    'avatar_url' => 'https://linkedin.com/avatar.jpg',
                    'headline' => 'Professional at Company',
                    'industry' => 'Technology',
                    'connections_count' => rand(500, 5000)
                ]
            ];
        }

        Log::info('LinkedIn: Using real authentication');
        return $this->authenticateReal($credentials);
    }

    protected function getRealAuthUrl(string $state = null): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => $this->getConfig('client_id'),
            'redirect_uri' => $this->getConfig('redirect'),
            'scope' => implode(' ', $this->getDefaultScopes()),
            'state' => $state ?? csrf_token()
        ];

        $authUrl = $this->getConfig('auth_url') . '?' . http_build_query($params);

        Log::info('LinkedIn: Generated auth URL', [
            'url' => $authUrl,
            'client_id' => $this->getConfig('client_id'),
            'redirect_uri' => $this->getConfig('redirect'),
            'scopes' => $this->getDefaultScopes()
        ]);

        return $authUrl;
    }

    protected function getRealTokens(string $code): array
    {
        $tokenUrl = $this->getConfig('token_url');
        $requestData = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->getConfig('redirect'),
            'client_id' => $this->getConfig('client_id'),
            'client_secret' => $this->getConfig('client_secret'),
        ];

        Log::info('LinkedIn: Exchanging code for tokens', [
            'token_url' => $tokenUrl,
            'client_id' => $this->getConfig('client_id'),
            'redirect_uri' => $this->getConfig('redirect')
        ]);

        $response = Http::asForm()->post($tokenUrl, $requestData);

        if (!$response->successful()) {
            Log::error('LinkedIn: Token exchange failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'request_data' => array_merge($requestData, ['client_secret' => '[HIDDEN]'])
            ]);
            throw new \Exception('LinkedIn token exchange failed: ' . $response->body());
        }

        $data = $response->json();

        Log::info('LinkedIn: Token exchange successful', [
            'expires_in' => $data['expires_in'] ?? 'not_specified',
            'token_type' => $data['token_type'] ?? 'not_specified'
        ]);

        return [
            'access_token' => $data['access_token'],
            'expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
            'token_type' => $data['token_type'] ?? 'Bearer',
            'scope' => explode(' ', $data['scope'] ?? implode(' ', $this->getDefaultScopes())),
        ];
    }

    private function authenticateReal(array $credentials): array
    {
        return [
            'success' => true,
            'message' => 'LinkedIn real authentication completed',
            'mode' => 'real'
        ];
    }

    public function publishPost(SocialMediaPost $post, Channel $channel): array
    {
        if ($this->isStubMode()) {
            Log::info('LinkedIn: Publishing post in stub mode');
            return $this->publishStubPost($post, $channel);
        }

        Log::info('LinkedIn: Publishing post in real mode');
        return $this->publishRealPost($post, $channel);
    }

    private function publishStubPost(SocialMediaPost $post, Channel $channel): array
    {
        $formatted = $this->formatPost($post);

        return [
            'success' => true,
            'platform_id' => 'linkedin_post_' . uniqid(),
            'url' => 'https://linkedin.com/feed/update/urn:li:activity:' . uniqid(),
            'published_at' => now()->toISOString(),
            'post_type' => !empty($formatted['media']) ? 'RICH_MEDIA' : 'TEXT_ONLY',
            'mode' => 'stub'
        ];
    }

    private function publishRealPost(SocialMediaPost $post, Channel $channel): array
    {
        try {
            $tokens = $channel->oauth_tokens; // Don't decrypt if already array
            if (is_string($tokens)) {
                $tokens = decrypt($tokens);
            }

            // Get user profile ID first - using your working endpoint
            $profileResponse = Http::withToken($tokens['access_token'])
                ->withHeaders([
                    'Accept' => 'application/json',
                    'X-Restli-Protocol-Version' => '2.0.0'
                ])
                ->get('https://api.linkedin.com/v2/userinfo');

            if (!$profileResponse->successful()) {
                Log::error('LinkedIn: Failed to get profile', [
                    'status' => $profileResponse->status(),
                    'response' => $profileResponse->body()
                ]);
                throw new \Exception('Failed to get LinkedIn profile: ' . $profileResponse->body());
            }

            $profile = $profileResponse->json();
            $profileId = $profile['sub']; // Using 'sub' from userinfo endpoint
            $formatted = $this->formatPost($post);

            // 🔥 CORRECTED LINKEDIN API PAYLOAD FORMAT
            $postData = [
                'author' => "urn:li:person:{$profileId}",
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $formatted['content']
                        ],
                        'shareMediaCategory' => 'NONE'
                    ]
                ],
                'visibility' => [
                    'com.linkedin.ugc.MemberNetworkVisibility' => 'PUBLIC'
                ]
            ];

            Log::info('LinkedIn: Attempting to publish post', [
                'profile_id' => $profileId,
                'content_length' => strlen($formatted['content']),
                'payload' => $postData
            ]);

            // 🔥 UPDATED API CALL WITH CORRECT HEADERS
            $response = Http::withToken($tokens['access_token'])
                ->withHeaders([
                    'X-Restli-Protocol-Version' => '2.0.0',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->post('https://api.linkedin.com/v2/ugcPosts', $postData);

            if ($response->successful()) {
                $responseData = $response->json();
                $postId = $responseData['id'] ?? 'unknown';

                Log::info('LinkedIn: Post published successfully', [
                    'post_id' => $postId,
                    'linkedin_id' => $responseData['id']
                ]);

                return [
                    'success' => true,
                    'platform_id' => $postId,
                    'url' => "https://www.linkedin.com/feed/update/{$postId}/",
                    'published_at' => now()->toISOString(),
                    'platform_data' => $responseData,
                    'mode' => 'real'
                ];
            }

            Log::error('LinkedIn: Post publishing failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'request_payload' => $postData
            ]);

            return [
                'success' => false,
                'error' => 'LinkedIn API error: ' . $response->body(),
                'retryable' => $this->isRetryableError($response->status()),
                'mode' => 'real',
                'debug_info' => [
                    'status_code' => $response->status(),
                    'payload_sent' => $postData,
                    'api_response' => $response->body()
                ]
            ];
        } catch (\Exception $e) {
            Log::error('LinkedIn: Post publishing exception', [
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

    public function getAnalytics(string $postId, Channel $channel): array
    {
        if ($this->isStubMode()) {
            return [
                'impressions' => rand(100, 8000),
                'clicks' => rand(10, 400),
                'likes' => rand(5, 200),
                'comments' => rand(1, 50),
                'shares' => rand(2, 100),
                'follows' => rand(0, 20),
                'engagement_rate' => round(rand(150, 600) / 100, 2),
                'demographic_data' => [
                    'seniority' => [
                        'entry' => rand(10, 30),
                        'mid' => rand(30, 50),
                        'senior' => rand(20, 40),
                        'executive' => rand(5, 20)
                    ],
                    'industry' => [
                        'technology' => rand(20, 60),
                        'finance' => rand(10, 30),
                        'healthcare' => rand(5, 25),
                        'education' => rand(5, 20),
                        'other' => rand(10, 30)
                    ]
                ],
                'mode' => 'stub'
            ];
        }

        return $this->getRealAnalytics($postId, $channel);
    }

    private function getRealAnalytics(string $postId, Channel $channel): array
    {
        // LinkedIn analytics require additional permissions and enterprise access
        Log::info('LinkedIn: Analytics requested for real mode', ['post_id' => $postId]);

        return [
            'success' => true,
            'note' => 'LinkedIn analytics require enterprise API access',
            'metrics' => [
                'impressions' => 0,
                'clicks' => 0,
                'likes' => 0,
                'comments' => 0,
                'shares' => 0
            ],
            'mode' => 'real'
        ];
    }

    public function validatePost(SocialMediaPost $post): array
    {
        $errors = [];

        $content = $post->content['text'] ?? '';
        $errors = array_merge($errors, $this->validateContent($content));

        $media = $post->media ?? [];
        $errors = array_merge($errors, $this->validateMedia($media));

        if (strlen($content) < 10) {
            $errors[] = "LinkedIn posts should be at least 10 characters for better engagement";
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'character_count' => strlen($content),
            'character_limit' => $this->getCharacterLimit(),
            'mode' => $this->isStubMode() ? 'stub' : 'real'
        ];
    }

    public function getCharacterLimit(): int
    {
        return 3000;
    }

    public function getMediaLimit(): int
    {
        return 9;
    }

    public function getSupportedMediaTypes(): array
    {
        return ['image', 'video', 'document', 'article'];
    }

    public function getDefaultScopes(): array
    {
        // Use OpenID Connect scopes (these work with your enabled products)
        return [
            'openid',
            'profile',
            'w_member_social',
            'email'              // Posting scope
        ];
    }

    /**
     * Get current mode for debugging
     */
    public function getCurrentMode(): string
    {
        return $this->isStubMode() ? 'stub' : 'real';
    }

    // === NEW PUBLIC METHODS FOR OAUTH CALLBACK ===

    /**
     * Public wrapper for token exchange (for OAuth callback)
     */
    public function exchangeCodeForTokens(string $code): array
    {
        if ($this->isStubMode()) {
            throw new \Exception('Cannot exchange real tokens in stub mode. LinkedIn is configured for real API but provider is in stub mode.');
        }

        Log::info('LinkedIn: Public token exchange called', ['code_length' => strlen($code)]);
        return $this->getRealTokens($code);
    }

    /**
     * Public wrapper to get auth URL
     */
    public function getAuthUrl(string $state = null): string
    {
        if ($this->isStubMode()) {
            Log::info('LinkedIn: Generating stub auth URL');
            return 'https://example.com/oauth/stub?provider=linkedin&state=' . ($state ?? 'stub_state');
        }

        Log::info('LinkedIn: Generating real auth URL');
        return $this->getRealAuthUrl($state);
    }

    /**
     * Check if provider is properly configured for real API
     */
    public function isConfigured(): bool
    {
        $hasClientId = !empty($this->getConfig('client_id'));
        $hasClientSecret = !empty($this->getConfig('client_secret'));
        $hasRedirect = !empty($this->getConfig('redirect'));

        Log::info('LinkedIn: Configuration check', [
            'client_id_set' => $hasClientId,
            'client_secret_set' => $hasClientSecret,
            'redirect_set' => $hasRedirect,
            'fully_configured' => $hasClientId && $hasClientSecret && $hasRedirect
        ]);

        return $hasClientId && $hasClientSecret && $hasRedirect;
    }

    /**
     * Get configuration status for debugging
     */
    public function getConfigurationStatus(): array
    {
        return [
            'platform' => $this->platform,
            'mode' => $this->getCurrentMode(),
            'configured' => $this->isConfigured(),
            'enabled' => $this->isEnabled(),
            'config_details' => [
                'client_id' => !empty($this->getConfig('client_id')) ? 'SET' : 'NOT SET',
                'client_secret' => !empty($this->getConfig('client_secret')) ? 'SET' : 'NOT SET',
                'redirect_uri' => $this->getConfig('redirect') ?? 'NOT SET',
                'base_url' => $this->getConfig('base_url') ?? 'NOT SET',
                'auth_url' => $this->getConfig('auth_url') ?? 'NOT SET',
                'token_url' => $this->getConfig('token_url') ?? 'NOT SET',
            ],
            'scopes' => $this->getDefaultScopes(),
            'constraints' => [
                'character_limit' => $this->getCharacterLimit(),
                'media_limit' => $this->getMediaLimit(),
                'supported_media' => $this->getSupportedMediaTypes()
            ]
        ];
    }
}
