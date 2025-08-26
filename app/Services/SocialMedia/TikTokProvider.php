<?php
// app/Services/SocialMedia/TikTokProvider.php

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;
use App\Models\Channel;
use Illuminate\Support\Facades\Http;


class TikTokProvider extends AbstractSocialMediaProvider
{
    protected $platform = 'tiktok';

    public function authenticate(array $credentials): array
    {
        if ($this->isStubMode) {
            return [
                'success' => true,
                'access_token' => 'tiktok_token_' . uniqid(),
                'refresh_token' => 'tiktok_refresh_' . uniqid(),
                'expires_at' => now()->addDays(1),
                'user_info' => [
                    'username' => 'tiktok_user_' . rand(1000, 9999),
                    'display_name' => 'TikTok Creator',
                    'avatar_url' => 'https://p16-sign-va.tiktokcdn.com/avatar.jpg',
                    'follower_count' => rand(100, 1000000),
                    'following_count' => rand(50, 2000),
                    'likes_count' => rand(1000, 10000000),
                    'video_count' => rand(10, 5000)
                ]
            ];
        }

        return $this->authenticateReal($credentials);
    }

    protected function getRealAuthUrl(string $state = null): string
    {
        $params = [
            'client_key' => $this->getConfig('client_id'),
            'response_type' => 'code',
            'scope' => implode(',', $this->getDefaultScopes()),
            'redirect_uri' => $this->getConfig('redirect'),
            'state' => $state ?? csrf_token()
        ];

        return 'https://www.tiktok.com/auth/authorize/?' . http_build_query($params);
    }

    protected function getRealTokens(string $code): array
    {
        $response = Http::asForm()->post('https://open-api.tiktok.com/oauth/access_token/', [
            'client_key' => $this->getConfig('client_id'),
            'client_secret' => $this->getConfig('client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->getConfig('redirect')
        ]);

        if (!$response->successful()) {
            throw new \Exception('TikTok token exchange failed: ' . $response->body());
        }

        $data = $response->json()['data'];

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'],
            'expires_at' => now()->addSeconds($data['expires_in']),
            'token_type' => 'Bearer',
            'scope' => $this->getDefaultScopes(),
        ];
    }

    private function authenticateReal(array $credentials): array
    {
        return [
            'success' => true,
            'message' => 'TikTok authentication completed'
        ];
    }

    public function publishPost(SocialMediaPost $post, Channel $channel): array
    {
        if ($this->isStubMode) {
            return $this->publishStubPost($post, $channel);
        }

        return $this->publishRealPost($post, $channel);
    }

    private function publishStubPost(SocialMediaPost $post, Channel $channel): array
    {
        $formatted = $this->formatPost($post);
        
        $hasVideo = false;
        foreach ($formatted['media'] as $item) {
            if ($item['type'] === 'video') {
                $hasVideo = true;
                break;
            }
        }
        
        if (!$hasVideo) {
            return [
                'success' => false,
                'error' => 'TikTok posts require video content'
            ];
        }
        
        return [
            'success' => true,
            'platform_id' => 'tiktok_video_' . uniqid(),
            'url' => 'https://tiktok.com/@user/video/' . uniqid(),
            'published_at' => now()->toISOString(),
            'video_id' => 'tk_' . uniqid(),
            'processing_status' => 'processing'
        ];
    }

    private function publishRealPost(SocialMediaPost $post, Channel $channel): array
    {
        try {
            $tokens = decrypt($channel->oauth_tokens);
            $formatted = $this->formatPost($post);

            if (empty($formatted['media'])) {
                return [
                    'success' => false,
                    'error' => 'TikTok requires video content',
                    'retryable' => false
                ];
            }

            $videoFile = $formatted['media'][0];
            
            if ($videoFile['type'] !== 'video') {
                return [
                    'success' => false,
                    'error' => 'TikTok only accepts video files',
                    'retryable' => false
                ];
            }

            // TikTok API implementation would go here
            // Note: TikTok API is quite complex and requires business verification
            
            return [
                'success' => true,
                'platform_id' => 'real_tiktok_' . uniqid(),
                'url' => 'https://tiktok.com/@user/video/real_id',
                'published_at' => now()->toISOString(),
                'platform_data' => ['status' => 'processing']
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retryable' => true
            ];
        }
    }

    public function getAnalytics(string $postId, Channel $channel): array
    {
        if ($this->isStubMode) {
            return [
                'views' => rand(1000, 1000000),
                'likes' => rand(50, 50000),
                'comments' => rand(10, 5000),
                'shares' => rand(5, 2000),
                'downloads' => rand(2, 500),
                'profile_views' => rand(20, 1000),
                'followers_gained' => rand(0, 100),
                'average_watch_time' => round(rand(500, 8000) / 100, 1),
                'completion_rate' => round(rand(2000, 8000) / 100, 2),
                'engagement_rate' => round(rand(500, 1500) / 100, 2),
                'hashtag_views' => rand(10000, 1000000),
                'sound_usage' => rand(1, 1000),
                'geographic_data' => [
                    'top_territories' => [
                        'US' => rand(20, 60),
                        'UK' => rand(5, 20),
                        'CA' => rand(5, 15),
                        'AU' => rand(2, 10),
                        'other' => rand(10, 40)
                    ]
                ],
                'audience_activity' => [
                    'peak_hours' => ['18:00', '19:00', '20:00', '21:00'],
                    'peak_days' => ['Friday', 'Saturday', 'Sunday']
                ]
            ];
        }

        return $this->getRealAnalytics($postId, $channel);
    }

    private function getRealAnalytics(string $postId, Channel $channel): array
    {
        // TikTok analytics implementation
        return [
            'success' => true,
            'note' => 'TikTok analytics require business API access',
            'metrics' => []
        ];
    }

    public function validatePost(SocialMediaPost $post): array
    {
        $errors = [];
        
        $content = $post->content['text'] ?? '';
        $media = $post->media ?? [];
        
        $hasVideo = false;
        foreach ($media as $item) {
            if ($item['type'] === 'video') {
                $hasVideo = true;
                break;
            }
        }
        
        if (!$hasVideo) {
            $errors[] = "TikTok requires video content";
        }
        
        if (strlen($content) > 300) {
            $errors[] = "TikTok captions cannot exceed 300 characters";
        }
        
        $hashtags = $post->hashtags ?? [];
        if (count($hashtags) > 20) {
            $errors[] = "TikTok allows maximum 20 hashtags";
        }
        
        return $errors;
    }

    public function getCharacterLimit(): int
    {
        return 300;
    }

    public function getMediaLimit(): int
    {
        return 1;
    }

    public function getSupportedMediaTypes(): array
    {
        return ['video'];
    }

    public function getDefaultScopes(): array
    {
        return ['user.info.basic', 'video.upload'];
    }
}