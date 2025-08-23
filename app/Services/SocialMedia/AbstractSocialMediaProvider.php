<?php
// app/Services/SocialMedia/AbstractSocialMediaProvider.php

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;
use App\Models\Channel;

abstract class AbstractSocialMediaProvider
{
    protected $platform;
    protected $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    /**
     * Get platform name
     */
    public function getPlatform(): string
    {
        return $this->platform;
    }

    /**
     * Abstract methods that must be implemented by each provider
     */
    abstract public function authenticate(array $credentials): array;
    abstract public function publishPost(SocialMediaPost $post, Channel $channel): array;
    abstract public function getAnalytics(string $postId, Channel $channel): array;
    abstract public function validatePost(SocialMediaPost $post): array;

    /**
     * Get platform-specific character limits
     */
    abstract public function getCharacterLimit(): int;

    /**
     * Get platform-specific media limits
     */
    abstract public function getMediaLimit(): int;

    /**
     * Get supported media types
     */
    abstract public function getSupportedMediaTypes(): array;

    /**
     * Common validation rules
     */
    protected function validateContent(string $content): array
    {
        $errors = [];

        if (strlen($content) > $this->getCharacterLimit()) {
            $errors[] = "Content exceeds {$this->getCharacterLimit()} character limit";
        }

        if (empty(trim($content))) {
            $errors[] = "Content cannot be empty";
        }

        return $errors;
    }

    /**
     * Common media validation
     */
    protected function validateMedia(array $media): array
    {
        $errors = [];

        if (count($media) > $this->getMediaLimit()) {
            $errors[] = "Too many media files. Maximum allowed: {$this->getMediaLimit()}";
        }

        $supportedTypes = $this->getSupportedMediaTypes();
        foreach ($media as $item) {
            if (!in_array($item['type'], $supportedTypes)) {
                $errors[] = "Unsupported media type: {$item['type']}";
            }
        }

        return $errors;
    }

    /**
     * Format post for platform
     */
    protected function formatPost(SocialMediaPost $post): array
    {
        return [
            'content' => $post->content['text'] ?? '',
            'media' => $post->media ?? [],
            'hashtags' => $post->hashtags ?? [],
            'mentions' => $post->mentions ?? []
        ];
    }
}