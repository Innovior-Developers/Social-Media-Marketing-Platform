<?php
// app/Services/SocialMedia/YouTubeProvider.php

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;
use App\Models\Channel;
use Google\Client as GoogleClient;
use Google\Service\YouTube;


class YouTubeProvider extends AbstractSocialMediaProvider
{
    protected $platform = 'youtube';
    private $client;

    public function __construct(array $config = [])
    {
        parent::__construct($config);
        
        if (!$this->isStubMode) {
            $this->initializeGoogleClient();
        }   
    }

    private function initializeGoogleClient()
    {
        $this->client = new GoogleClient();
        $this->client->setClientId($this->getConfig('client_id'));
        $this->client->setClientSecret($this->getConfig('client_secret'));
        $this->client->setRedirectUri($this->getConfig('redirect'));
        $this->client->addScope(YouTube::YOUTUBE_UPLOAD);
        $this->client->setAccessType('offline');
        $this->client->setIncludeGrantedScopes(true);
    }

    public function authenticate(array $credentials): array
    {
        if ($this->isStubMode) {
            return [
                'success' => true,
                'access_token' => 'youtube_token_' . uniqid(),
                'refresh_token' => 'youtube_refresh_' . uniqid(),
                'expires_at' => now()->addHour(),
                'user_info' => [
                    'channel_id' => 'UC' . uniqid(),
                    'channel_name' => 'YouTube Channel',
                    'avatar_url' => 'https://yt3.ggpht.com/avatar.jpg',
                    'subscriber_count' => rand(100, 100000),
                    'video_count' => rand(10, 1000),
                    'view_count' => rand(10000, 10000000)
                ]
            ];
        }

        return $this->authenticateReal($credentials);
    }

    protected function getRealAuthUrl(string $state = null): string
    {
        if ($this->client) {
            return $this->client->createAuthUrl();
        }
        
        throw new \Exception('Google client not initialized');
    }

    protected function getRealTokens(string $code): array
    {
        if (!$this->client) {
            throw new \Exception('Google client not initialized');
        }

        $this->client->authenticate($code);
        $tokens = $this->client->getAccessToken();

        return [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'] ?? null,
            'expires_at' => now()->addSeconds($tokens['expires_in']),
            'token_type' => 'Bearer',
            'scope' => explode(' ', $tokens['scope'] ?? ''),
        ];
    }

    private function authenticateReal(array $credentials): array
    {
        return [
            'success' => true,
            'message' => 'YouTube authentication completed'
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
                'error' => 'YouTube posts require video content'
            ];
        }
        
        return [
            'success' => true,
            'platform_id' => 'youtube_video_' . uniqid(),
            'url' => 'https://youtube.com/watch?v=' . uniqid(),
            'published_at' => now()->toISOString(),
            'video_id' => 'vid_' . uniqid(),
            'processing_status' => 'uploaded'
        ];
    }

    private function publishRealPost(SocialMediaPost $post, Channel $channel): array
    {
        try {
            $tokens = decrypt($channel->oauth_tokens);
            $this->client->setAccessToken($tokens['access_token']);
            $formatted = $this->formatPost($post);

            if (empty($formatted['media'])) {
                return [
                    'success' => false,
                    'error' => 'YouTube requires video content',
                    'retryable' => false
                ];
            }

            $videoFile = $formatted['media'][0];
            
            if (!str_starts_with($videoFile['type'], 'video')) {
                return [
                    'success' => false,
                    'error' => 'YouTube only accepts video files',
                    'retryable' => false
                ];
            }

            $youtube = new YouTube($this->client);

            // Create video snippet
            $snippet = new YouTube\VideoSnippet();
            $snippet->setTitle($post->title ?? 'Untitled Video');
            $snippet->setDescription($formatted['content']);
            $snippet->setTags($post->hashtags ?? []);

            // Create video status
            $status = new YouTube\VideoStatus();
            $status->setPrivacyStatus('public');

            // Create video
            $video = new YouTube\Video();
            $video->setSnippet($snippet);
            $video->setStatus($status);

            // Upload video
            $insertRequest = $youtube->videos->insert("status,snippet", $video);
            
            // For real implementation, you'd upload the actual file here
            // This is a simplified version
            
            return [
                'success' => true,
                'platform_id' => 'real_youtube_video_' . uniqid(),
                'url' => 'https://www.youtube.com/watch?v=real_video_id',
                'published_at' => now()->toISOString(),
                'platform_data' => ['status' => 'uploaded']
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
                'views' => rand(100, 50000),
                'likes' => rand(10, 2000),
                'dislikes' => rand(0, 100),
                'comments' => rand(5, 500),
                'shares' => rand(2, 200),
                'subscribers_gained' => rand(0, 50),
                'watch_time_minutes' => rand(50, 5000),
                'average_view_duration' => rand(30, 300),
                'click_through_rate' => round(rand(200, 800) / 100, 2),
                'audience_retention' => round(rand(3000, 7000) / 100, 2),
                'revenue' => [
                    'estimated_ad_revenue' => round(rand(1, 100) / 100, 2),
                    'estimated_red_revenue' => round(rand(0, 10) / 100, 2)
                ],
                'traffic_sources' => [
                    'youtube_search' => rand(20, 60),
                    'suggested_videos' => rand(15, 40),
                    'browse_features' => rand(10, 30),
                    'external' => rand(5, 25)
                ]
            ];
        }

        return $this->getRealAnalytics($postId, $channel);
    }

    private function getRealAnalytics(string $postId, Channel $channel): array
    {
        try {
            $tokens = decrypt($channel->oauth_tokens);
            $this->client->setAccessToken($tokens['access_token']);
            
            $youtube = new YouTube($this->client);
            
            $response = $youtube->videos->listVideos('statistics', [
                'id' => $postId
            ]);

            if (!empty($response->getItems())) {
                $video = $response->getItems()[0];
                $stats = $video->getStatistics();

                return [
                    'views' => (int) $stats->getViewCount(),
                    'likes' => (int) $stats->getLikeCount(),
                    'comments' => (int) $stats->getCommentCount(),
                    'shares' => 0, // Not available via API
                ];
            }

            return ['error' => 'Video not found'];

        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
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
            $errors[] = "YouTube requires video content";
        }
        
        $title = $post->content['title'] ?? '';
        if (empty($title)) {
            $errors[] = "YouTube videos require a title";
        }
        
        if (strlen($title) > 100) {
            $errors[] = "YouTube title cannot exceed 100 characters";
        }
        
        if (strlen($content) > 5000) {
            $errors[] = "YouTube description cannot exceed 5000 characters";
        }
        
        return $errors;
    }

    public function getCharacterLimit(): int
    {
        return 5000;
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
        return [YouTube::YOUTUBE_UPLOAD];
    }
}