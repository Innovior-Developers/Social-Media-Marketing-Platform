<?php
// database/factories/ChannelFactory.php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Brand;

class ChannelFactory extends Factory
{
    public function definition(): array
    {
        $provider = fake()->randomElement(['twitter', 'facebook', 'instagram', 'linkedin', 'youtube']);
        $handle = $this->generateHandle($provider);
        
        return [
            'brand_id' => Brand::factory(),
            'provider' => $provider,
            'handle' => $handle,
            'display_name' => fake()->company() . ' Official',
            'avatar_url' => fake()->imageUrl(150, 150, 'people'),
            'oauth_tokens' => [
                'access_token' => 'fake_access_token_' . fake()->uuid(),
                'refresh_token' => 'fake_refresh_token_' . fake()->uuid(),
                'expires_at' => fake()->dateTimeBetween('now', '+30 days'),
            ],
            'provider_constraints' => $this->getProviderConstraints($provider),
            'connection_status' => fake()->randomElement(['connected', 'disconnected', 'expired']),
            'last_sync_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'active' => fake()->boolean(90), // 90% chance of being active
        ];
    }

    public function connected(): static
    {
        return $this->state(fn (array $attributes) => [
            'connection_status' => 'connected',
            'last_sync_at' => now(),
        ]);
    }

    public function disconnected(): static
    {
        return $this->state(fn (array $attributes) => [
            'connection_status' => 'disconnected',
            'oauth_tokens' => [
                'access_token' => null,
                'refresh_token' => null,
                'expires_at' => null,
            ],
        ]);
    }

    public function twitter(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'twitter',
            'handle' => '@' . fake()->userName(),
            'provider_constraints' => $this->getProviderConstraints('twitter'),
        ]);
    }

    public function facebook(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'facebook',
            'handle' => fake()->company() . ' Page',
            'provider_constraints' => $this->getProviderConstraints('facebook'),
        ]);
    }

    public function instagram(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'instagram',
            'handle' => '@' . fake()->userName(),
            'provider_constraints' => $this->getProviderConstraints('instagram'),
        ]);
    }

    private function generateHandle(string $provider): string
    {
        return match($provider) {
            'twitter', 'instagram' => '@' . fake()->userName(),
            'facebook' => fake()->company() . ' Page',
            'linkedin' => fake()->company() . ' Company',
            'youtube' => fake()->company() . ' Channel',
            default => fake()->userName()
        };
    }

    private function getProviderConstraints(string $provider): array
    {
        return match($provider) {
            'twitter' => [
                'max_characters' => 280,
                'max_media' => 4,
                'supported_media_types' => ['image', 'video', 'gif'],
                'rate_limits' => [
                    'posts_per_hour' => 300,
                    'posts_per_day' => 2400,
                ],
            ],
            'facebook' => [
                'max_characters' => 63206,
                'max_media' => 10,
                'supported_media_types' => ['image', 'video'],
                'rate_limits' => [
                    'posts_per_hour' => 600,
                    'posts_per_day' => 14400,
                ],
            ],
            'instagram' => [
                'max_characters' => 2200,
                'max_media' => 10,
                'supported_media_types' => ['image', 'video'],
                'rate_limits' => [
                    'posts_per_hour' => 200,
                    'posts_per_day' => 4800,
                ],
            ],
            'linkedin' => [
                'max_characters' => 3000,
                'max_media' => 9,
                'supported_media_types' => ['image', 'video', 'document'],
                'rate_limits' => [
                    'posts_per_hour' => 150,
                    'posts_per_day' => 3600,
                ],
            ],
            'youtube' => [
                'max_characters' => 5000,
                'max_media' => 1,
                'supported_media_types' => ['video'],
                'rate_limits' => [
                    'posts_per_hour' => 6,
                    'posts_per_day' => 144,
                ],
            ],
            default => [
                'max_characters' => 280,
                'max_media' => 4,
                'supported_media_types' => ['image'],
                'rate_limits' => [
                    'posts_per_hour' => 100,
                    'posts_per_day' => 1000,
                ],
            ]
        };
    }
}