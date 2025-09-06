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

    /**
     * Check if post is still active on platform
     */
    public function checkPlatformStatus(string $platform): bool
    {
        $platformData = $this->platform_posts[$platform] ?? null;

        if (!$platformData) {
            return false;
        }

        // This would call the actual platform API to verify
        // For now, return true if we have platform data
        return !empty($platformData['platform_id']);
    }

    /**
     * Mark post as deleted on platform
     */
    public function markDeletedOnPlatform(string $platform, array $details = []): void
    {
        $platformPosts = $this->platform_posts ?? [];

        if (isset($platformPosts[$platform])) {
            $platformPosts[$platform]['deleted_on_platform'] = true;
            $platformPosts[$platform]['deleted_at'] = now()->toISOString();
            $platformPosts[$platform]['deletion_details'] = $details;

            $this->update([
                'platform_posts' => $platformPosts,
                'post_status' => 'deleted_on_platform'
            ]);
        }
    }

    /**
     * Get posts by status
     */
    public static function getByStatus(string $status, string $userId = null)
    {
        $query = static::where('post_status', $status);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Get post lifecycle status
     */
    public function getLifecycleStatus(): array
    {
        return [
            'current_status' => $this->post_status,
            'created_at' => $this->created_at,
            'published_at' => $this->published_at,
            'deleted_at' => $this->deleted_at ?? null,
            'platforms' => $this->platforms,
            'platform_status' => collect($this->platform_posts ?? [])->map(function ($data, $platform) {
                return [
                    'platform' => $platform,
                    'published' => isset($data['published_at']),
                    'deleted_on_platform' => $data['deleted_on_platform'] ?? false,
                    'platform_id' => $data['platform_id'] ?? null
                ];
            })->values()->toArray()
        ];
    }

    /**
     * Create a new version of the post
     */
    public function createVersion(array $newData): SocialMediaPost
    {
        $versionData = array_merge($this->toArray(), $newData);
        $versionData['parent_post_id'] = $this->_id;
        $versionData['version_number'] = ($this->version_number ?? 1) + 1;
        $versionData['post_status'] = 'draft'; // New version starts as draft
        unset($versionData['_id']); // Remove ID so a new one is created

        return static::create($versionData);
    }

    /**
     * Get all versions of this post
     */
    public function getVersions()
    {
        return static::where('parent_post_id', $this->_id)
            ->orWhere('_id', $this->parent_post_id ?? 'none')
            ->orderBy('version_number')
            ->get();
    }
}
