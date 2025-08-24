<?php
// database/factories/MembershipFactory.php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Brand;

class MembershipFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'brand_id' => Brand::factory(),
            'role' => fake()->randomElement(['OWNER', 'MANAGER', 'EDITOR', 'VIEWER']),
            'permissions' => fake()->randomElements([
                'create_posts', 'edit_posts', 'delete_posts', 
                'schedule_posts', 'view_analytics', 'manage_channels'
            ], fake()->numberBetween(1, 3)),
            'invited_by' => null, // Will be set separately if needed
            'joined_at' => fake()->dateTimeBetween('-1 year', 'now'),
        ];
    }

    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'OWNER',
            'permissions' => [
                'manage_brand', 'manage_team', 'create_posts', 'edit_posts', 
                'delete_posts', 'schedule_posts', 'view_analytics', 'manage_channels'
            ],
        ]);
    }

    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'MANAGER',
            'permissions' => [
                'create_posts', 'edit_posts', 'delete_posts', 
                'schedule_posts', 'view_analytics', 'manage_channels'
            ],
        ]);
    }

    public function editor(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'EDITOR',
            'permissions' => ['create_posts', 'edit_posts', 'schedule_posts', 'view_analytics'],
        ]);
    }

    public function viewer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'VIEWER',
            'permissions' => ['view_analytics'],
        ]);
    }
}