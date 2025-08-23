<?php
// app/Models/PostAnalytics.php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PostAnalytics extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'post_analytics';

    protected $fillable = [
        'user_id',
        'social_media_post_id',
        'platform',
        'metrics',
        'performance_score',
        'collected_at',
        'period_start',
        'period_end',
        'demographic_data',
        'geographic_data',
        'engagement_timeline',
        'comparison_data',
    ];

    protected $casts = [
        'collected_at' => 'datetime',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
    ];

    protected $attributes = [
        'metrics' => [
            'impressions' => 0,
            'reach' => 0,
            'likes' => 0,
            'shares' => 0,
            'comments' => 0,
            'clicks' => 0,
            'saves' => 0,
            'engagement_rate' => 0,
            'click_through_rate' => 0,
        ],
        'demographic_data' => [
            'age_groups' => [],
            'gender_split' => [],
            'top_locations' => [],
        ],
        'engagement_timeline' => [],
        'comparison_data' => [
            'vs_previous_post' => [],
            'vs_account_average' => [],
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
     * Calculate performance score
     */
    public function calculatePerformanceScore(): float
    {
        $metrics = $this->getAttribute('metrics') ?? [];
        
        $impressions = $metrics['impressions'] ?? 0;
        $engagement = ($metrics['likes'] ?? 0) + 
                     ($metrics['shares'] ?? 0) + 
                     ($metrics['comments'] ?? 0);
        
        if ($impressions === 0) return 0;
        
        $engagementRate = ($engagement / $impressions) * 100;
        
        // Simple scoring: 0-100 based on engagement rate
        return min(100, $engagementRate * 10);
    }

    /**
     * Update performance score
     */
    public function updatePerformanceScore(): void
    {
        $score = $this->calculatePerformanceScore();
        $this->update(['performance_score' => $score]);
    }
}