<?php
// app/Services/SocialMedia/TikTokProvider.php

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;
use App\Models\Channel;

class TikTokProvider extends AbstractSocialMediaProvider
{
    protected $platform = 'tiktok';

    public function authenticate(array $credentials): array
    {
        return [
            'success' => true,
            'access_token' => 'tiktok_token_' . uniqid(),
            'refresh_token' => 'tiktok_refresh_' . uniqid(),
            'expires_at' => now()->addDays(1), // TikTok tokens expire daily
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

    public function publishPost(SocialMediaPost $post, Channel $channel): array
    {
        $formatted = $this->formatPost($post);
        
        // TikTok requires video content
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
            'processing_status' => 'processing' // processing, published, failed
        ];
    }

    public function getAnalytics(string $postId, Channel $channel): array
    {
        return [
            'views' => rand(1000, 1000000),
            'likes' => rand(50, 50000),
            'comments' => rand(10, 5000),
            'shares' => rand(5, 2000),
            'downloads' => rand(2, 500),
            'profile_views' => rand(20, 1000),
            'followers_gained' => rand(0, 100),
            'average_watch_time' => round(rand(500, 8000) / 100, 1), // seconds
            'completion_rate' => round(rand(2000, 8000) / 100, 2), // 20-80%
            'engagement_rate' => round(rand(500, 1500) / 100, 2), // 5-15%
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

    public function validatePost(SocialMediaPost $post): array
    {
        $errors = [];
        
        $content = $post->content['text'] ?? '';
        $media = $post->media ?? [];
        
        // TikTok requires video
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
        
        // Caption validation
        if (strlen($content) > 300) {
            $errors[] = "TikTok captions cannot exceed 300 characters";
        }
        
        // Hashtag validation
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
        return 1; // One video per TikTok
    }

    public function getSupportedMediaTypes(): array
    {
        return ['video'];
    }
}