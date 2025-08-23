<?php
// app/Jobs/PublishScheduledPost.php

namespace App\Jobs;

use App\Models\ScheduledPost;
use App\Models\SocialMediaPost;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishScheduledPost implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $scheduledPost;

    public function __construct(ScheduledPost $scheduledPost)
    {
        $this->scheduledPost = $scheduledPost;
    }

    public function handle(): void
    {
        try {
            $post = $this->scheduledPost->socialMediaPost;
            $platform = $this->scheduledPost->platform;

            Log::info("Publishing scheduled post", [
                'post_id' => $post->_id,
                'platform' => $platform,
                'scheduled_at' => $this->scheduledPost->scheduled_at
            ]);

            // Simulate publishing to social media platform
            $response = $this->publishToSocialMedia($post, $platform);

            if ($response['success']) {
                // Mark as published
                $this->scheduledPost->markAsPublished($response['data']);
                
                // Update the main post
                $post->updatePlatformPost($platform, [
                    'platform_id' => $response['data']['id'],
                    'published_at' => now(),
                    'url' => $response['data']['url']
                ]);

                Log::info("Post published successfully", [
                    'post_id' => $post->_id,
                    'platform' => $platform,
                    'platform_id' => $response['data']['id']
                ]);
            } else {
                throw new \Exception($response['error']);
            }

        } catch (\Exception $e) {
            Log::error("Failed to publish scheduled post", [
                'post_id' => $this->scheduledPost->social_media_post_id,
                'platform' => $this->scheduledPost->platform,
                'error' => $e->getMessage()
            ]);

            $this->scheduledPost->markAsFailed($e->getMessage());

            // Retry if possible
            if ($this->scheduledPost->canRetry()) {
                $this->release(300); // Retry in 5 minutes
            }
        }
    }

    private function publishToSocialMedia($post, $platform): array
    {
        // This is a simulation - in real implementation, you'd call actual APIs
        $simulatedResponse = [
            'success' => true,
            'data' => [
                'id' => 'sim_' . uniqid(),
                'url' => "https://{$platform}.com/post/sim_" . uniqid(),
                'published_at' => now()->toISOString()
            ]
        ];

        // Simulate occasional failures for testing
        if (rand(1, 10) === 1) {
            return [
                'success' => false,
                'error' => 'Simulated API error for testing'
            ];
        }

        return $simulatedResponse;
    }
}