<?php
// database/factories/BrandFactory.php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Organization;

class BrandFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company() . ' Brand';
        
        return [
            'organization_id' => Organization::factory(),
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
            'active' => fake()->boolean(85), // 85% chance of being active
            'settings' => [
                'timezone' => fake()->randomElement([
                    'UTC', 'America/New_York', 'Europe/London', 
                    'Asia/Tokyo', 'Australia/Sydney'
                ]),
                'default_publish_time' => fake()->randomElement([
                    '09:00', '10:00', '11:00', '14:00', '15:00', '16:00'
                ]),
                'branding' => [
                    'logo_url' => fake()->imageUrl(200, 200, 'business'),
                    'primary_color' => fake()->hexColor(),
                ],
            ],
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    public function withCustomBranding(): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], [
                'branding' => [
                    'logo_url' => '/assets/custom-logo.png',
                    'primary_color' => '#FF6B35',
                ],
            ]),
        ]);
    }
}