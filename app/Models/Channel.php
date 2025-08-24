<?php
// app/Models/Channel.php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Channel extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'channels';

    protected $fillable = [
        'brand_id',
        'provider',
        'handle',
        'display_name',
        'avatar_url',
        'oauth_tokens',
        'provider_constraints',
        'connection_status',
        'last_sync_at',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'last_sync_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'active' => true,
        'connection_status' => 'disconnected',
        'oauth_tokens' => [
            'access_token' => null,
            'refresh_token' => null,
            'expires_at' => null,
        ],
        'provider_constraints' => [
            'max_characters' => 280,
            'max_media' => 4,
            'supported_media_types' => ['image', 'video'],
            'rate_limits' => [
                'posts_per_hour' => 300,
                'posts_per_day' => 2400,
            ],
        ],
    ];

    /**
     * Relationships
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeConnected($query)
    {
        return $query->where('connection_status', 'connected');
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('provider', $provider);
    }

    /**
     * Custom Methods
     */
    public function isConnected(): bool
    {
        return $this->connection_status === 'connected';
    }

    public function isExpired(): bool
    {
        $tokens = $this->getAttribute('oauth_tokens') ?? [];
        $expiresAt = $tokens['expires_at'] ?? null;
        
        return $expiresAt && now()->isAfter($expiresAt);
    }

    public function getMaxCharacters(): int
    {
        $constraints = $this->getAttribute('provider_constraints') ?? [];
        return $constraints['max_characters'] ?? 280;
    }

    public function getMaxMedia(): int
    {
        $constraints = $this->getAttribute('provider_constraints') ?? [];
        return $constraints['max_media'] ?? 4;
    }

    public function getSupportedMediaTypes(): array
    {
        $constraints = $this->getAttribute('provider_constraints') ?? [];
        return $constraints['supported_media_types'] ?? ['image'];
    }

    public function getRateLimits(): array
    {
        $constraints = $this->getAttribute('provider_constraints') ?? [];
        return $constraints['rate_limits'] ?? [
            'posts_per_hour' => 100,
            'posts_per_day' => 1000,
        ];
    }

    public function updateTokens(array $tokens): void
    {
        $currentTokens = $this->getAttribute('oauth_tokens') ?? [];
        $updatedTokens = array_merge($currentTokens, $tokens);
        $this->setAttribute('oauth_tokens', $updatedTokens);
        $this->save();
    }

    public function markAsConnected(): void
    {
        $this->update([
            'connection_status' => 'connected',
            'last_sync_at' => now(),
        ]);
    }

    public function markAsDisconnected(): void
    {
        $this->update([
            'connection_status' => 'disconnected',
        ]);
    }

    public function getProviderDisplayName(): string
    {
        $providers = [
            'twitter' => 'Twitter/X',
            'facebook' => 'Facebook',
            'instagram' => 'Instagram',
            'linkedin' => 'LinkedIn',
            'youtube' => 'YouTube',
            'tiktok' => 'TikTok',
        ];

        return $providers[$this->provider] ?? ucfirst($this->provider);
    }
}