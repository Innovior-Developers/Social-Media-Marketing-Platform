<?php
// app/Services/SocialMedia/InstagramProvider.php

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;
use App\Models\Channel;

class InstagramProvider extends AbstractSocialMediaProvider
{
    protected $platform = 'instagram';

    public function authenticate(array $credentials): array
    {
        return [
            'success' => true,
            'access_token' => 'instagram_token_' . uniqid(),
            'refresh_token' => 'instagram_refresh_' . uniqid(),
            'expires_at' => now()->addDays(60), // Instagram tokens last 60 days
            'user_info' => [
                'username' => 'insta_user_' . rand(1000, 9999),
                'display_name' => 'Instagram User',
                'avatar_url' => 'https://instagram.com/avatar.jpg',
                'account_type' => 'BUSINESS', // PERSONAL, CREATOR, BUSINESS
                'followers_count' => rand(100, 50000)
            ]
        ];
    }

    public function publishPost(SocialMediaPost $post, Channel $channel): array
    {
        $formatted = $this->formatPost($post);
        
        // Instagram requires media for posts
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

    public function getAnalytics(string $postId, Channel $channel): array
    {
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
            'engagement_rate' => round(rand(200, 800) / 100, 2) // 2-8%
        ];
    }

    public function validatePost(SocialMediaPost $post): array
    {
        $errors = [];
        
        $content = $post->content['text'] ?? '';
        $errors = array_merge($errors, $this->validateContent($content));
        
        $media = $post->media ?? [];
        
        // Instagram requires media
        if (empty($media)) {
            $errors[] = "Instagram posts require at least one image or video";
        }
        
        $errors = array_merge($errors, $this->validateMedia($media));
        
        // Check hashtag limits
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
        return 10; // Instagram carousel limit
    }

    public function getSupportedMediaTypes(): array
    {
        return ['image', 'video', 'reel'];
    }

    public function getDefaultScopes(): array
    {
        return [
            'instagram_basic',
            'instagram_content_publish',
            'pages_show_list',
            'pages_read_engagement'
        ];
    }
}