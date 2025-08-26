<?php
// app/Services/SocialMedia/FacebookProvider.php

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;
use App\Models\Channel;
use Illuminate\Support\Facades\Http;

class FacebookProvider extends AbstractSocialMediaProvider
{
    protected $platform = 'facebook';

    public function authenticate(array $credentials): array
    {
        if ($this->isStubMode) {
            return [
                'success' => true,
                'access_token' => 'facebook_token_' . uniqid(),
                'refresh_token' => 'facebook_refresh_' . uniqid(),
                'expires_at' => now()->addHours(24),
                'user_info' => [
                    'username' => 'facebook_user_' . rand(1000, 9999),
                    'display_name' => 'Facebook User',
                    'avatar_url' => 'https://graph.facebook.com/me/picture',
                    'page_id' => 'page_' . uniqid(),
                    'page_name' => 'My Facebook Page'
                ]
            ];
        }

        return $this->authenticateReal($credentials);
    }

    protected function getRealAuthUrl(string $state = null): string
    {
        $params = [
            'client_id' => $this->getConfig('client_id'),
            'redirect_uri' => $this->getConfig('redirect'),
            'scope' => implode(',', $this->getDefaultScopes()),
            'response_type' => 'code',
            'state' => $state ?? csrf_token()
        ];

        return 'https://www.facebook.com/v18.0/dialog/oauth?' . http_build_query($params);
    }

    protected function getRealTokens(string $code): array
    {
        $response = Http::get('https://graph.facebook.com/v18.0/oauth/access_token', [
            'client_id' => $this->getConfig('client_id'),
            'client_secret' => $this->getConfig('client_secret'),
            'redirect_uri' => $this->getConfig('redirect'),
            'code' => $code
        ]);

        if (!$response->successful()) {
            throw new \Exception('Facebook token exchange failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'token_type' => 'bearer',
            'expires_at' => now()->addSeconds($data['expires_in'] ?? 3600),
            'scope' => $this->getDefaultScopes(),
        ];
    }

    private function authenticateReal(array $credentials): array
    {
        // Get user's pages after OAuth
        return [
            'success' => true,
            'message' => 'Facebook authentication completed'
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
        
        return [
            'success' => true,
            'platform_id' => 'fb_post_' . uniqid(),
            'url' => 'https://facebook.com/permalink.php?id=' . uniqid(),
            'published_at' => now()->toISOString(),
            'engagement' => [
                'initial_reach' => rand(50, 2000)
            ]
        ];
    }

    private function publishRealPost(SocialMediaPost $post, Channel $channel): array
    {
        try {
            $tokens = decrypt($channel->oauth_tokens);
            $formatted = $this->formatPost($post);

            $postData = [
                'message' => $formatted['content'],
                'access_token' => $tokens['access_token']
            ];

            // Add media if present
            if (!empty($formatted['media'])) {
                $media = $formatted['media'][0];
                $postData['link'] = url('storage/' . $media['path']);
            }

            $response = $this->makeApiRequest(
                'post',
                "https://graph.facebook.com/v18.0/{$channel->platform_user_id}/feed",
                $postData
            );

            if ($response['success']) {
                $fbPost = $response['data'];
                return [
                    'success' => true,
                    'platform_id' => $fbPost['id'],
                    'url' => "https://facebook.com/{$fbPost['id']}",
                    'published_at' => now()->toISOString(),
                    'platform_data' => $fbPost
                ];
            }

            return [
                'success' => false,
                'error' => $response['error'],
                'retryable' => $response['retryable'] ?? false
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
                'impressions' => rand(200, 20000),
                'reach' => rand(150, 15000),
                'likes' => rand(10, 800),
                'shares' => rand(2, 150),
                'comments' => rand(1, 100),
                'clicks' => rand(5, 400),
                'reactions' => [
                    'like' => rand(5, 300),
                    'love' => rand(1, 100),
                    'haha' => rand(0, 50),
                    'wow' => rand(0, 30),
                    'sad' => rand(0, 20),
                    'angry' => rand(0, 10)
                ],
                'video_views' => rand(100, 5000),
                'page_engagement' => rand(20, 500)
            ];
        }

        return $this->getRealAnalytics($postId, $channel);
    }

    private function getRealAnalytics(string $postId, Channel $channel): array
    {
        try {
            $tokens = decrypt($channel->oauth_tokens);
            
            $response = $this->makeApiRequest(
                'get',
                "https://graph.facebook.com/v18.0/{$postId}/insights?metric=post_impressions,post_engaged_users",
                [],
                ['Authorization' => 'Bearer ' . $tokens['access_token']]
            );

            if ($response['success']) {
                $insights = $response['data']['data'];
                $metrics = [];
                
                foreach ($insights as $insight) {
                    $metricName = $this->mapFacebookMetric($insight['name']);
                    $metrics[$metricName] = $insight['values'][0]['value'] ?? 0;
                }

                return $metrics;
            }

            return ['error' => $response['error']];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function mapFacebookMetric(string $facebookMetric): string
    {
        return match($facebookMetric) {
            'post_impressions' => 'impressions',
            'post_engaged_users' => 'engagement',
            'post_clicks' => 'clicks',
            default => $facebookMetric
        };
    }

    public function validatePost(SocialMediaPost $post): array
    {
        $errors = [];
        
        $content = $post->content['text'] ?? '';
        $errors = array_merge($errors, $this->validateContent($content));
        
        $media = $post->media ?? [];
        $errors = array_merge($errors, $this->validateMedia($media));
        
        // Facebook-specific validations
        if (count($media) > 0) {
            foreach ($media as $item) {
                if ($item['type'] === 'video' && !empty($content) && strlen($content) > 2200) {
                    $errors[] = "Video posts should have shorter text (max 2200 characters)";
                }
            }
        }
        
        return $errors;
    }

    public function getCharacterLimit(): int
    {
        return 63206;
    }

    public function getMediaLimit(): int
    {
        return 10;
    }

    public function getSupportedMediaTypes(): array
    {
        return ['image', 'video', 'link', 'poll'];
    }

    public function getDefaultScopes(): array
    {
        return ['pages_manage_posts', 'pages_read_engagement', 'pages_show_list'];
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
}