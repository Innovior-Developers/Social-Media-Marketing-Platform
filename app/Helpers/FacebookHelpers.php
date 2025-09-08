<?php

namespace App\Helpers;

use App\Models\SocialMediaPost;
use App\Models\Channel;
use App\Services\SocialMedia\FacebookProvider;
use Illuminate\Support\Facades\Log;

class FacebookHelpers
{
    /**
     * Check Facebook post status using provider
     * 
     * @param SocialMediaPost $post
     * @return array Status check result
     */
    public static function checkFacebookPostStatusWithProvider(SocialMediaPost $post): array
    {
        try {
            $facebookData = $post->platform_posts['facebook'] ?? null;

            if (!$facebookData || !isset($facebookData['platform_id'])) {
                return [
                    'success' => false,
                    'error' => 'No Facebook post data found',
                    'exists' => false
                ];
            }

            // Get Facebook channel
            $channel = Channel::where('provider', 'facebook')
                ->where('connection_status', 'connected')
                ->first();

            if (!$channel) {
                return [
                    'success' => false,
                    'error' => 'No Facebook channel available',
                    'exists' => 'unknown',
                    'requires_manual_deletion' => true
                ];
            }

            // Use Facebook provider to check post status
            $provider = new FacebookProvider();
            $result = $provider->checkPostExists(
                $facebookData['platform_id'],
                $channel
            );

            return [
                'success' => $result['success'],
                'exists' => $result['exists'] ?? 'unknown',
                'post_id' => $facebookData['platform_id'],
                'post_url' => $facebookData['url'] ?? "https://facebook.com/{$facebookData['platform_id']}",
                'checked_at' => $result['checked_at'] ?? now()->toISOString(),
                'provider_response' => $result
            ];
        } catch (\Exception $e) {
            Log::error('FacebookHelpers: Status check failed', [
                'post_id' => $post->_id ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'exists' => 'unknown',
                'requires_manual_deletion' => true
            ];
        }
    }

    /**
     * Get user's Facebook pages using provider
     * 
     * @param Channel $channel
     * @return array Pages list result
     */
    public static function getUserFacebookPages(Channel $channel): array
    {
        try {
            $provider = new FacebookProvider();

            if (!$provider->isConfigured()) {
                return [
                    'success' => false,
                    'error' => 'Facebook provider not configured',
                    'pages' => []
                ];
            }

            $result = $provider->getUserPages($channel);

            Log::info('FacebookHelpers: Pages retrieved', [
                'success' => $result['success'],
                'page_count' => count($result['pages'] ?? []),
                'mode' => $result['mode'] ?? 'unknown'
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('FacebookHelpers: Get pages failed', [
                'channel_id' => $channel->_id ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'pages' => []
            ];
        }
    }

    /**
     * Create a temporary Facebook channel for API calls
     */
    public static function createTemporaryChannel(?array $tokenData = null): ?Channel
    {
        if (!$tokenData) {
            // Try to find existing Facebook channel
            $channel = Channel::where('provider', 'facebook')
                ->where('connection_status', 'connected')
                ->first();

            if ($channel) {
                return $channel;
            }

            return null;
        }

        if (!isset($tokenData['access_token'])) {
            return null;
        }

        return new Channel([
            'oauth_tokens' => $tokenData,
            'provider' => 'facebook',
            'connection_status' => 'connected'
        ]);
    }

    /**
     * Validate Facebook media file
     * 
     * @param mixed $file
     * @param string $mediaType
     * @return array Validation result
     */
    public static function validateFacebookMediaFile($file, $mediaType): array
    {
        if (!$file) {
            return ['valid' => false, 'error' => 'No file uploaded'];
        }

        $facebookConstraints = config('services.facebook.constraints', []);

        switch ($mediaType) {
            case 'image':
                $allowedExtensions = $facebookConstraints['supported_image_formats'] ?? ['jpg', 'jpeg', 'png', 'gif'];
                $maxSize = $facebookConstraints['image_max_size'] ?? (100 * 1024 * 1024); // 100MB
                break;
            case 'video':
                $allowedExtensions = $facebookConstraints['supported_video_formats'] ?? ['mp4', 'mov', 'avi'];
                $maxSize = $facebookConstraints['video_max_size'] ?? (10 * 1024 * 1024 * 1024); // 10GB
                break;
            default:
                return ['valid' => false, 'error' => 'Unsupported media type for Facebook'];
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $allowedExtensions)) {
            return [
                'valid' => false,
                'error' => "Facebook only supports " . implode(', ', $allowedExtensions) . " files for {$mediaType}"
            ];
        }

        if ($file->getSize() > $maxSize) {
            return [
                'valid' => false,
                'error' => "Facebook {$mediaType} must be smaller than " . self::formatFileSize($maxSize)
            ];
        }

        return ['valid' => true];
    }

    /**
     * Get Facebook posting constraints
     * 
     * @return array Facebook constraints
     */
    public static function getFacebookConstraints(): array
    {
        return config('services.facebook.constraints', [
            'character_limit' => 63206,
            'media_limit' => 10,
            'video_max_size' => 10737418240, // 10GB
            'image_max_size' => 104857600,   // 100MB
            'supported_video_formats' => ['mp4', 'mov', 'avi'],
            'supported_image_formats' => ['jpg', 'jpeg', 'png', 'gif']
        ]);
    }

    /**
     * Check if Facebook posting is enabled
     * 
     * @return bool
     */
    public static function isFacebookEnabled(): bool
    {
        return config('services.facebook.enabled', false) &&
               !empty(config('services.facebook.app_id')) &&
               !empty(config('services.facebook.app_secret'));
    }

    /**
     * Get Facebook provider mode
     * 
     * @return string 'stub' or 'real'
     */
    public static function getFacebookMode(): string
    {
        $realProviders = config('services.social_media.real_providers', []);
        return ($realProviders['facebook'] ?? false) ? 'real' : 'stub';
    }

    /**
     * Format file size for human reading
     * 
     * @param int $bytes
     * @return string
     */
    private static function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $pow = floor(log($bytes) / log(1024));
        return round($bytes / (1024 ** $pow), 1) . ' ' . $units[$pow];
    }

    /**
     * Get Facebook analytics summary for a post
     * 
     * @param SocialMediaPost $post
     * @return array Analytics summary
     */
    public static function getFacebookAnalyticsSummary(SocialMediaPost $post): array
    {
        try {
            $facebookData = $post->platform_posts['facebook'] ?? null;

            if (!$facebookData || !isset($facebookData['platform_id'])) {
                return [
                    'success' => false,
                    'error' => 'No Facebook post data found'
                ];
            }

            $channel = Channel::where('provider', 'facebook')
                ->where('connection_status', 'connected')
                ->first();

            if (!$channel) {
                return [
                    'success' => false,
                    'error' => 'No Facebook channel available'
                ];
            }

            $provider = new FacebookProvider();
            $analytics = $provider->getAnalytics($facebookData['platform_id'], $channel);

            if ($analytics['success']) {
                $metrics = $analytics['metrics'];

                return [
                    'success' => true,
                    'summary' => [
                        'impressions' => $metrics['impressions'] ?? 0,
                        'reach' => $metrics['reach'] ?? 0,
                        'total_reactions' => $metrics['total_reactions'] ?? 0,
                        'engagement_rate' => $metrics['engagement_rate'] ?? 0,
                        'clicks' => $metrics['clicks'] ?? 0,
                        'video_views' => $metrics['video_views'] ?? 0
                    ],
                    'reactions_breakdown' => [
                        'likes' => $metrics['likes'] ?? 0,
                        'loves' => $metrics['loves'] ?? 0,
                        'wows' => $metrics['wows'] ?? 0,
                        'hahas' => $metrics['hahas'] ?? 0,
                        'sorrys' => $metrics['sorrys'] ?? 0,
                        'angers' => $metrics['angers'] ?? 0
                    ],
                    'demographics' => $analytics['demographics'] ?? [],
                    'mode' => $analytics['mode'] ?? 'unknown',
                    'collected_at' => $analytics['collected_at']
                ];
            }

            return $analytics;
        } catch (\Exception $e) {
            Log::error('FacebookHelpers: Analytics summary failed', [
                'post_id' => $post->_id ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete Facebook post using provider
     * 
     * @param SocialMediaPost $post
     * @return array Deletion result
     */
    public static function deleteFacebookPost(SocialMediaPost $post): array
    {
        try {
            $facebookData = $post->platform_posts['facebook'] ?? null;

            if (!$facebookData || !isset($facebookData['platform_id'])) {
                return [
                    'success' => false,
                    'error' => 'No Facebook post data found'
                ];
            }

            $channel = Channel::where('provider', 'facebook')
                ->where('connection_status', 'connected')
                ->first();

            if (!$channel) {
                return [
                    'success' => false,
                    'error' => 'No Facebook channel available'
                ];
            }

            $provider = new FacebookProvider();
            $result = $provider->deletePost($facebookData['platform_id'], $channel);

            if ($result['success']) {
                // Mark as deleted in database
                $post->markDeletedOnPlatform('facebook', [
                    'deletion_method' => 'api',
                    'deleted_by' => 'system',
                    'provider_response' => $result
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('FacebookHelpers: Post deletion failed', [
                'post_id' => $post->_id ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}