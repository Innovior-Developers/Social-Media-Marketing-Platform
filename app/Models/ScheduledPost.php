<?php
// app/Models/ScheduledPost.php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ScheduledPost extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'scheduled_posts';

    protected $fillable = [
        'user_id',
        'social_media_post_id',
        'platform',
        'scheduled_at',
        'status',
        'retry_count',
        'max_retries',
        'last_attempt_at',
        'published_at',
        'platform_response',
        'error_message',
        'settings',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'pending',
        'retry_count' => 0,
        'max_retries' => 3,
        'platform_response' => [],
        'settings' => [
            'timezone' => 'UTC',
            'auto_retry' => true,
            'notify_on_failure' => true,
        ],
    ];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function socialMediaPost()
    {
        return $this->belongsTo(SocialMediaPost::class);
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeReadyToPublish($query)
    {
        return $query->where('status', 'pending')
                    ->where('scheduled_at', '<=', now());
    }

    public function scopeForPlatform($query, string $platform)
    {
        return $query->where('platform', $platform);
    }

    /**
     * Mark as published
     */
    public function markAsPublished(array $response = []): void
    {
        $this->update([
            'status' => 'published',
            'published_at' => now(),
            'platform_response' => $response,
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $error,
            'last_attempt_at' => now(),
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Check if can retry
     */
    public function canRetry(): bool
    {
        return $this->retry_count < $this->max_retries && 
               $this->status === 'failed';
    }
}