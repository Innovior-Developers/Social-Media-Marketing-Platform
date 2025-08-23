<?php
// app/Jobs/CollectAnalytics.php

namespace App\Jobs;

use App\Models\SocialMediaPost;
use App\Models\PostAnalytics;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CollectAnalytics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $post;
    protected $platform;

    public function __construct(SocialMediaPost $post, string $platform)
    {
        $this->post = $post;
        $this->platform = $platform;
    }

    public function handle(): void
    {
        try {
            Log::info("Collecting analytics", [
                'post_id' => $this->post->_id,
                'platform' => $this->platform
            ]);

            // Simulate fetching analytics from social media platform
            $analyticsData = $this->fetchAnalyticsFromPlatform();

            // Create or update analytics record
            $analytics = PostAnalytics::updateOrCreate([
                'social_media_post_id' => $this->post->_id,
                'platform' => $this->platform,
                'user_id' => $this->post->user_id
            ], [
                'metrics' => $analyticsData['metrics'],
                'demographic_data' => $analyticsData['demographics'],
                'engagement_timeline' => $analyticsData['timeline'],
                'collected_at' => now()
            ]);

            // Update performance score
            $analytics->updatePerformanceScore();

            Log::info("Analytics collected successfully", [
                'post_id' => $this->post->_id,
                'platform' => $this->platform,
                'performance_score' => $analytics->performance_score
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to collect analytics", [
                'post_id' => $this->post->_id,
                'platform' => $this->platform,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function fetchAnalyticsFromPlatform(): array
    {
        // Simulate real analytics data
        return [
            'metrics' => [
                'impressions' => rand(100, 10000),
                'reach' => rand(80, 8000),
                'likes' => rand(5, 500),
                'shares' => rand(1, 100),
                'comments' => rand(0, 50),
                'clicks' => rand(2, 200),
                'saves' => rand(0, 100),
                'engagement_rate' => round(rand(1, 15) + (rand(0, 99) / 100), 2),
                'click_through_rate' => round(rand(1, 5) + (rand(0, 99) / 100), 2),
            ],
            'demographics' => [
                'age_groups' => [
                    '18-24' => rand(10, 30),
                    '25-34' => rand(20, 40),
                    '35-44' => rand(15, 35),
                    '45-54' => rand(10, 25),
                    '55+' => rand(5, 20)
                ],
                'gender_split' => [
                    'male' => rand(40, 60),
                    'female' => rand(40, 60),
                    'other' => rand(0, 5)
                ],
                'top_locations' => [
                    'United States' => rand(20, 50),
                    'United Kingdom' => rand(10, 30),
                    'Canada' => rand(5, 20),
                    'Australia' => rand(5, 15),
                    'Other' => rand(10, 30)
                ]
            ],
            'timeline' => [
                now()->subHours(24)->toISOString() => rand(10, 100),
                now()->subHours(12)->toISOString() => rand(20, 200),
                now()->subHours(6)->toISOString() => rand(15, 150),
                now()->subHours(1)->toISOString() => rand(5, 50),
            ]
        ];
    }
}