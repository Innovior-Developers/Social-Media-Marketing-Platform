<?php
// app/Services/SocialMedia/AbstractSocialMediaProvider.php

namespace App\Services\SocialMedia;

use App\Models\SocialMediaPost;
use App\Models\Channel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

abstract class AbstractSocialMediaProvider
{
    protected $platform;
    protected $config;
    protected $isStubMode;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->isStubMode = config('services.social_media.mode') === 'stub';
    }

    /**
     * Get platform name
     */
    public function getPlatform(): string
    {
        return $this->platform;
    }

    /**
     * Check if provider is in stub mode
     */
    public function isStubMode(): bool
    {
        return $this->isStubMode;
    }

    /**
     * Check if provider is enabled
     */
    public function isEnabled(): bool
    {
        return config("services.{$this->platform}.enabled", false);
    }

    /**
     * Abstract methods that must be implemented by each provider
     */
    abstract public function authenticate(array $credentials): array;
    abstract public function publishPost(SocialMediaPost $post, Channel $channel): array;
    abstract public function getAnalytics(string $postId, Channel $channel): array;
    abstract public function validatePost(SocialMediaPost $post): array;

    /**
     * Real OAuth methods (to be implemented by real providers)
     */
    public function getAuthUrl(string $state = null): string
    {
        if ($this->isStubMode) {
            return route('oauth.callback', [
                'provider' => $this->platform, 
                'code' => 'stub_code_' . uniqid(),
                'state' => $state
            ]);
        }

        return $this->getRealAuthUrl($state);
    }

    public function exchangeCodeForTokens(string $code): array
    {
        if ($this->isStubMode) {
            return $this->getStubTokens();
        }

        return $this->getRealTokens($code);
    }

    /**
     * Methods to be implemented by real providers
     */
    protected function getRealAuthUrl(string $state = null): string
    {
        throw new \Exception("Real OAuth not implemented for {$this->platform}");
    }

    protected function getRealTokens(string $code): array
    {
        throw new \Exception("Real token exchange not implemented for {$this->platform}");
    }

    protected function getStubTokens(): array
    {
        return [
            'access_token' => $this->platform . '_token_' . uniqid(),
            'refresh_token' => $this->platform . '_refresh_' . uniqid(),
            'expires_at' => now()->addHours(24),
            'token_type' => 'Bearer',
            'scope' => $this->getDefaultScopes(),
        ];
    }

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
     * Get default OAuth scopes for this platform
     */
    abstract public function getDefaultScopes(): array;

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

    /**
     * Common HTTP request with error handling
     */
    protected function makeApiRequest(string $method, string $url, array $data = [], array $headers = []): array
    {
        try {
            $response = Http::withHeaders($headers)
                ->timeout(30)
                ->$method($url, $data);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json(),
                    'status_code' => $response->status()
                ];
            }

            Log::warning("API request failed for {$this->platform}", [
                'url' => $url,
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return [
                'success' => false,
                'error' => $response->body(),
                'status_code' => $response->status(),
                'retryable' => $this->isRetryableError($response->status())
            ];

        } catch (\Exception $e) {
            Log::error("API request exception for {$this->platform}", [
                'url' => $url,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'retryable' => true
            ];
        }
    }

    /**
     * Determine if error is retryable
     */
    protected function isRetryableError($statusCode): bool
    {
        return in_array($statusCode, [408, 429, 500, 502, 503, 504]);
    }

    /**
     * Get provider configuration
     */
    protected function getConfig(string $key = null)
    {
        $config = config("services.{$this->platform}");
        
        if ($key) {
            return $config[$key] ?? null;
        }
        
        return $config;
    }
}