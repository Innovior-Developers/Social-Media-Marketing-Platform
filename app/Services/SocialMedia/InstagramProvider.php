<?php
// app/Services/SocialMedia/InstagramProvider.php

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;
use App\Models\Channel;
use Illuminate\Support\Facades\Http;

class InstagramProvider extends AbstractSocialMediaProvider
{
    protected $platform = 'instagram';

    public function authenticate(array $credentials): array
    {
        if ($this->isStubMode) {
            return [
                'success' => true,
                'access_token' => 'instagram_token_' . uniqid(),
                'refresh_token' => 'instagram_refresh_' . uniqid(),
                'expires_at' => now()->addDays(60),
                'user_info' => [
                    'username' => 'insta_user_' . rand(1000, 9999),
                    'display_name' => 'Instagram User',
                    'avatar_url' => 'https://instagram.com/avatar.jpg',
                    'account_type' => 'BUSINESS',
                    'followers_count' => rand(100, 50000)
                ]
            ];
        }

        return $this->authenticateReal($credentials);
    }

    protected function getRealAuthUrl(string $state = null): string
    {
        // Instagram uses Facebook OAuth
        $params = [
            'client_id' => $this->getConfig('client_id'),
            'redirect_uri' => $this->getConfig('redirect'),
            'scope' => implode(',', $this->getDefaultScopes()),
            'response_type' => 'code',
            'state' => $state ?? csrf_token()
        ];

        return 'https://api.instagram.com/oauth/authorize?' . http_build_query($params);
    }

    protected function getRealTokens(string $code): array
    {
        $response = Http::asForm()->post('https://api.instagram.com/oauth/access_token', [
            'client_id' => $this->getConfig('client_id'),
            'client_secret' => $this->getConfig('client_secret'),
            'grant_type' => 'authorization_code',
            'redirect_uri' => $this->getConfig('redirect'),
            'code' => $code
        ]);

        if (!$response->successful()) {
            throw new \Exception('Instagram token exchange failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'user_id' => $data['user_id'],
            'expires_at' => now()->addDays(60), // Instagram tokens last 60 days
            'scope' => $this->getDefaultScopes(),
        ];
    }

    private function authenticateReal(array $credentials): array
    {
        return [
            'success' => true,
            'message' => 'Instagram authentication completed'
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
        
        if (empty($formatted['media'])) {
            return [
                'success' => false,
                'error' => 'Instagram posts require at least one image or video'
            ];
        }
        
        return [
            'success' => true,
            'platform_id' => 'ig_post_' . uniqid(),
            'url' => 'https://instagram.com/p/' . uniqid(),
            'published_at' => now()->toISOString(),
            'media_type' => count($formatted['media']) > 1 ? 'CAROUSEL_ALBUM' : 'IMAGE'
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
                    'error' => 'Instagram posts require media',
                    'retryable' => false
                ];
            }

            // Step 1: Create media container
            $mediaContainerData = [
                'image_url' => url('storage/' . $formatted['media'][0]['path']),
                'caption' => $formatted['content'],
                'access_token' => $tokens['access_token']
            ];

            $containerResponse = $this->makeApiRequest(
                'post',
                "https://graph.instagram.com/v18.0/{$tokens['user_id']}/media",
                $mediaContainerData
            );

            if (!$containerResponse['success']) {
                return [
                    'success' => false,
                    'error' => $containerResponse['error'],
                    'retryable' => $containerResponse['retryable'] ?? false
                ];
            }

            $containerId = $containerResponse['data']['id'];

            // Step 2: Publish the container
            $publishResponse = $this->makeApiRequest(
                'post',
                "https://graph.instagram.com/v18.0/{$tokens['user_id']}/media_publish",
                [
                    'creation_id' => $containerId,
                    'access_token' => $tokens['access_token']
                ]
            );

            if ($publishResponse['success']) {
                $mediaId = $publishResponse['data']['id'];
                return [
                    'success' => true,
                    'platform_id' => $mediaId,
                    'url' => "https://instagram.com/p/{$mediaId}/",
                    'published_at' => now()->toISOString(),
                    'platform_data' => $publishResponse['data']
                ];
            }

            return [
                'success' => false,
                'error' => $publishResponse['error'],
                'retryable' => $publishResponse['retryable'] ?? false
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
                'impressions' => rand(150, 15000),
                'reach' => rand(120, 12000),
                'likes' => rand(20, 1200),
                'comments' => rand(2, 150),
                'shares' => rand(1, 80),
                'saves' => rand(5, 300),
                'profile_visits' => rand(10, 200),
                'website_clicks' => rand(2, 100),
                'story_shares' => rand(1, 50),
                'hashtag_impressions' => rand(50, 5000),
                'location_impressions' => rand(20, 2000),
                'engagement_rate' => round(rand(200, 800) / 100, 2)
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
                "https://graph.instagram.com/v18.0/{$postId}/insights?metric=impressions,reach,likes,comments,shares,saves",
                [],
                ['Authorization' => 'Bearer ' . $tokens['access_token']]
            );

            if ($response['success']) {
                $insights = $response['data']['data'];
                $metrics = [];
                
                foreach ($insights as $insight) {
                    $metrics[$insight['name']] = $insight['values'][0]['value'] ?? 0;
                }

                return $metrics;
            }

            return ['error' => $response['error']];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    public function validatePost(SocialMediaPost $post): array
    {
        $errors = [];
        
        $content = $post->content['text'] ?? '';
        $errors = array_merge($errors, $this->validateContent($content));
        
        $media = $post->media ?? [];
        
        if (empty($media)) {
            $errors[] = "Instagram posts require at least one image or video";
        }
        
        $errors = array_merge($errors, $this->validateMedia($media));
        
        $hashtags = $post->hashtags ?? [];
        if (count($hashtags) > 30) {
            $errors[] = "Instagram allows maximum 30 hashtags per post";
        }
        
        return $errors;
    }

    public function getCharacterLimit(): int
    {
        return 2200;
    }

    public function getMediaLimit(): int
    {
        return 10;
    }

    public function getSupportedMediaTypes(): array
    {
        return ['image', 'video', 'reel'];
    }

    public function getDefaultScopes(): array
    {
        return ['instagram_basic', 'instagram_content_publish'];
    }
}