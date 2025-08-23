<?php
// database/factories/OrganizationFactory.php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Organization',
            'settings' => [
                'default_timezone' => fake()->randomElement([
                    'UTC', 'America/New_York', 'Europe/London', 
                    'Asia/Tokyo', 'Australia/Sydney', 'America/Los_Angeles'
                ]),
                'features' => fake()->randomElements([
                    'analytics', 'scheduling', 'multi_brand', 
                    'team_collaboration', 'advanced_reporting', 
                    'api_access', 'white_label'
                ], fake()->numberBetween(2, 5)),
            ],
        ];
    }

    public function enterprise(): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], [
                'features' => [
                    'analytics', 'scheduling', 'multi_brand', 
                    'team_collaboration', 'advanced_reporting', 
                    'api_access', 'white_label', 'priority_support'
                ],
            ]),
        ]);
    }

    public function basic(): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'] ?? [], [
                'features' => ['analytics', 'scheduling'],
            ]),
        ]);
    }
}