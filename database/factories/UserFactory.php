<?php
// database/factories/UserFactory.php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'status' => 'active',
            'roles' => ['user'], // Simple array, no casting needed
            'permissions' => [], // Simple array, no casting needed
            'profile' => [
                'company' => fake()->company(),
                'website' => fake()->url(),
                'bio' => fake()->sentence(20),
                'location' => fake()->city() . ', ' . fake()->country(),
                'phone' => fake()->phoneNumber(),
            ],
            'preferences' => [
                'timezone' => fake()->randomElement(['UTC', 'America/New_York', 'Europe/London', 'Asia/Tokyo']),
                'language' => 'en',
                'theme' => fake()->randomElement(['light', 'dark']),
                'notifications' => [
                    'email' => fake()->boolean(80),
                    'push' => fake()->boolean(70),
                    'post_success' => true,
                    'post_failure' => true,
                    'weekly_report' => fake()->boolean(60),
                ],
            ],
            'subscription' => [
                'plan' => fake()->randomElement(['free', 'basic', 'pro']),
                'status' => 'active',
                'started_at' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d H:i:s'),
                'limits' => [
                    'posts_per_month' => fake()->randomElement([50, 200, 1000]),
                    'social_accounts' => fake()->randomElement([3, 10, 25]),
                    'scheduled_posts' => fake()->randomElement([10, 50, 200]),
                ],
            ],
            'social_accounts' => [], // Empty by default
            'api_limits' => [
                'requests_per_hour' => 100,
                'requests_today' => 0,
                'last_reset' => null,
            ],
            'last_login_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withPlan(string $plan): static
    {
        $limits = match($plan) {
            'free' => ['posts_per_month' => 50, 'social_accounts' => 3, 'scheduled_posts' => 10],
            'basic' => ['posts_per_month' => 200, 'social_accounts' => 10, 'scheduled_posts' => 50],
            'pro' => ['posts_per_month' => 1000, 'social_accounts' => 25, 'scheduled_posts' => 200],
            'enterprise' => ['posts_per_month' => 10000, 'social_accounts' => 100, 'scheduled_posts' => 1000],
            default => ['posts_per_month' => 50, 'social_accounts' => 3, 'scheduled_posts' => 10],
        };

        return $this->state(fn (array $attributes) => [
            'subscription' => array_merge($attributes['subscription'] ?? [], [
                'plan' => $plan,
                'limits' => $limits,
            ]),
        ]);
    }

    public function withRole(string $role): static
    {
        return $this->state(fn (array $attributes) => [
            'roles' => [$role],
        ]);
    }
}