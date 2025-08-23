<?php
// app/Models/SocialMediaPost.php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SocialMediaPost extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'social_media_posts';

    protected $fillable = [
        'user_id',
        'content',
        'media',
        'platforms',
        'post_status',
        'scheduled_at',
        'published_at',
        'platform_posts',
        'engagement',
        'analytics',
        'hashtags',
        'mentions',
        'settings',
        'errors',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    protected $attributes = [
        'post_status' => 'draft',
        'platforms' => [],
        'platform_posts' => [],
        'engagement' => [
            'likes' => 0,
            'shares' => 0,
            'comments' => 0,
            'clicks' => 0,
            'impressions' => 0,
        ],
        'analytics' => [
            'reach' => 0,
            'engagement_rate' => 0,
            'click_through_rate' => 0,
            'last_updated' => null,
        ],
        'hashtags' => [],
        'mentions' => [],
        'media' => [],
        'settings' => [
            'auto_hashtags' => true,
            'cross_post' => false,
            'track_analytics' => true,
        ],
        'errors' => [],
    ];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get posts by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('post_status', $status);
    }

    /**
     * Get scheduled posts
     */
    public function scopeScheduled($query)
    {
        return $query->where('post_status', 'scheduled')
                    ->where('scheduled_at', '>', now());
    }

    /**
     * Get published posts
     */
    public function scopePublished($query)
    {
        return $query->where('post_status', 'published');
    }

    /**
     * Get posts for specific platform
     */
    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platforms', $platform);
    }

    /**
     * Check if post is scheduled for a platform
     */
    public function isScheduledFor(string $platform): bool
    {
        $platforms = $this->getAttribute('platforms') ?? [];
        return in_array($platform, $platforms);
    }

    /**
     * Get platform-specific post data
     */
    public function getPlatformPost(string $platform): ?array
    {
        $platformPosts = $this->getAttribute('platform_posts') ?? [];
        return $platformPosts[$platform] ?? null;
    }

    /**
     * Update platform post data
     */
    public function updatePlatformPost(string $platform, array $data): void
    {
        $platformPosts = $this->getAttribute('platform_posts') ?? [];
        $platformPosts[$platform] = array_merge($platformPosts[$platform] ?? [], $data);
        $this->setAttribute('platform_posts', $platformPosts);
        $this->save();
    }

    /**
     * Calculate total engagement
     */
    public function getTotalEngagement(): int
    {
        $engagement = $this->getAttribute('engagement') ?? [];
        return ($engagement['likes'] ?? 0) + 
               ($engagement['shares'] ?? 0) + 
               ($engagement['comments'] ?? 0);
    }

    /**
     * Update engagement data
     */
    public function updateEngagement(array $data): void
    {
        $engagement = $this->getAttribute('engagement') ?? [];
        $engagement = array_merge($engagement, $data);
        $this->setAttribute('engagement', $engagement);
        $this->save();
    }
}