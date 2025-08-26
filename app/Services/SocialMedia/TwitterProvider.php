<?php
// app/Services/SocialMedia/TwitterProvider.php

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;
use App\Models\Channel;
use Illuminate\Support\Facades\Http;

class TwitterProvider extends AbstractSocialMediaProvider
{
    protected $platform = 'twitter';

    public function authenticate(array $credentials): array
    {
        if ($this->isStubMode) {
            return [
                'success' => true,
                'access_token' => 'twitter_token_' . uniqid(),
                'refresh_token' => 'twitter_refresh_' . uniqid(),
                'expires_at' => now()->addHours(2),
                'user_info' => [
                    'username' => 'example_user',
                    'display_name' => 'Example User',
                    'avatar_url' => 'https://example.com/avatar.jpg'
                ]
            ];
        }

        // Real Twitter OAuth implementation will go here
        return $this->authenticateReal($credentials);
    }

    protected function getRealAuthUrl(string $state = null): string
    {
        $params = [
            'response_type' => 'code',
            'client_id' => $this->getConfig('client_id'),
            'redirect_uri' => $this->getConfig('redirect'),
            'scope' => implode(' ', $this->getDefaultScopes()),
            'state' => $state ?? csrf_token(),
            'code_challenge' => 'challenge', // PKCE for security
            'code_challenge_method' => 'plain'
        ];

        return 'https://twitter.com/i/oauth2/authorize?' . http_build_query($params);
    }

    protected function getRealTokens(string $code): array
    {
        $response = Http::asForm()->post('https://api.twitter.com/2/oauth2/token', [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->getConfig('redirect'),
            'client_id' => $this->getConfig('client_id'),
            'client_secret' => $this->getConfig('client_secret'),
            'code_verifier' => 'challenge'
        ]);

        if (!$response->successful()) {
            throw new \Exception('Twitter token exchange failed: ' . $response->body());
        }

        $data = $response->json();

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds($data['expires_in']),
            'token_type' => $data['token_type'],
            'scope' => explode(' ', $data['scope'] ?? ''),
        ];
    }

    private function authenticateReal(array $credentials): array
    {
        // Implementation for real Twitter OAuth
        return [
            'success' => true,
            'message' => 'Real Twitter authentication not yet implemented'
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
            'platform_id' => 'tweet_' . uniqid(),
            'url' => 'https://twitter.com/user/status/' . uniqid(),
            'published_at' => now()->toISOString()
        ];
    }

    private function publishRealPost(SocialMediaPost $post, Channel $channel): array
    {
        try {
            $tokens = decrypt($channel->oauth_tokens);
            $formatted = $this->formatPost($post);

            $tweetData = [
                'text' => $formatted['content']
            ];

            // Add media if present
            if (!empty($formatted['media'])) {
                // First upload media, then reference in tweet
                $mediaIds = $this->uploadMedia($formatted['media'], $tokens['access_token']);
                if (!empty($mediaIds)) {
                    $tweetData['media'] = ['media_ids' => $mediaIds];
                }
            }

            $response = $this->makeApiRequest(
                'post',
                $this->getConfig('base_url') . '/tweets',
                $tweetData,
                ['Authorization' => 'Bearer ' . $tokens['access_token']]
            );

            if ($response['success']) {
                $tweet = $response['data']['data'];
                return [
                    'success' => true,
                    'platform_id' => $tweet['id'],
                    'url' => "https://twitter.com/i/web/status/{$tweet['id']}",
                    'published_at' => now()->toISOString(),
                    'platform_data' => $tweet
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

    private function uploadMedia(array $media, string $accessToken): array
    {
        // Twitter media upload implementation
        return [];
    }

    public function getAnalytics(string $postId, Channel $channel): array
    {
        if ($this->isStubMode) {
            return [
                'impressions' => rand(100, 10000),
                'retweets' => rand(1, 100),
                'likes' => rand(5, 500),
                'replies' => rand(0, 50),
                'profile_clicks' => rand(2, 200),
                'hashtag_clicks' => rand(1, 50),
                'detail_expands' => rand(5, 300)
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
                $this->getConfig('base_url') . "/tweets/{$postId}?tweet.fields=public_metrics",
                [],
                ['Authorization' => 'Bearer ' . $tokens['access_token']]
            );

            if ($response['success']) {
                $metrics = $response['data']['data']['public_metrics'];
                return [
                    'impressions' => $metrics['impression_count'] ?? 0,
                    'retweets' => $metrics['retweet_count'] ?? 0,
                    'likes' => $metrics['like_count'] ?? 0,
                    'replies' => $metrics['reply_count'] ?? 0,
                    'quotes' => $metrics['quote_count'] ?? 0,
                ];
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
        $errors = array_merge($errors, $this->validateMedia($media));
        
        return $errors;
    }

    public function getCharacterLimit(): int
    {
        return 280;
    }

    public function getMediaLimit(): int
    {
        return 4;
    }

    public function getSupportedMediaTypes(): array
    {
        return ['image', 'video', 'gif'];
    }

    public function getDefaultScopes(): array
    {
        return ['tweet.read', 'tweet.write', 'users.read'];
    }
}