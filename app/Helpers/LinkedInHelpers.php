<?php

namespace App\Helpers;

use App\Models\SocialMediaPost;
use App\Models\Channel;
use App\Services\SocialMedia\LinkedInProvider;
use Illuminate\Support\Facades\Log;

class LinkedInHelpers
{
    /**
     * Check LinkedIn post status using provider
     * 
     * @param SocialMediaPost $post
     * @return array Status check result
     */
    public static function checkLinkedInPostStatusWithProvider(SocialMediaPost $post): array
    {
        try {
            $linkedinData = $post->platform_posts['linkedin'] ?? null;

            if (!$linkedinData || !isset($linkedinData['platform_id'])) {
                return [
                    'success' => false,
                    'error' => 'No LinkedIn post data found',
                    'exists' => false
                ];
            }

            // Get LinkedIn token
            $sessionFiles = glob(storage_path('app/oauth_sessions/oauth_tokens_linkedin_*.json'));

            if (empty($sessionFiles)) {
                return [
                    'success' => false,
                    'error' => 'No LinkedIn token available',
                    'exists' => 'unknown',
                    'requires_manual_deletion' => true
                ];
            }

            $latestFile = array_reduce($sessionFiles, function ($latest, $file) {
                return (!$latest || filemtime($file) > filemtime($latest)) ? $file : $latest;
            });

            $tokenData = json_decode(file_get_contents($latestFile), true);

            if (!isset($tokenData['access_token'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid LinkedIn token',
                    'exists' => 'unknown',
                    'requires_manual_deletion' => true
                ];
            }

            // Create temporary channel
            $channel = new Channel([
                'oauth_tokens' => $tokenData,
                'provider' => 'linkedin'
            ]);

            // Use LinkedIn provider to check post status
            $provider = new LinkedInProvider();
            $result = $provider->getPostDeletionStatus(
                $linkedinData['platform_id'],
                $channel,
                $linkedinData['url'] ?? null
            );

            return [
                'success' => true,
                'exists' => $result['status'] === 'EXISTS',
                'status' => $result['status'],
                'message' => $result['message'],
                'deletion_steps' => $result['manual_deletion_steps'] ?? null,
                'post_url' => $result['post_url'] ?? null,
                'checked_at' => $result['checked_at'] ?? now()->toISOString()
            ];
        } catch (\Exception $e) {
            Log::error('LinkedInHelpers: Status check failed', [
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
     * Get the latest LinkedIn token file
     */
    public static function getLatestTokenFile(): ?array
    {
        $sessionFiles = glob(storage_path('app/oauth_sessions/oauth_tokens_linkedin_*.json'));

        if (empty($sessionFiles)) {
            return null;
        }

        $latestFile = array_reduce($sessionFiles, function ($latest, $file) {
            return (!$latest || filemtime($file) > filemtime($latest)) ? $file : $latest;
        });

        $tokenData = json_decode(file_get_contents($latestFile), true);

        return [
            'file_path' => $latestFile,
            'file_name' => basename($latestFile, '.json'),
            'token_data' => $tokenData
        ];
    }

    /**
     * Create a temporary LinkedIn channel for API calls
     */
    public static function createTemporaryChannel(?array $tokenData = null): ?Channel
    {
        if (!$tokenData) {
            $tokenFile = self::getLatestTokenFile();
            if (!$tokenFile) {
                return null;
            }
            $tokenData = $tokenFile['token_data'];
        }

        if (!isset($tokenData['access_token'])) {
            return null;
        }

        return new Channel([
            'oauth_tokens' => $tokenData,
            'provider' => 'linkedin',
            'connection_status' => 'connected'
        ]);
    }
}