<?php
// app/Models/Organization.php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Organization extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'organizations';

    protected $fillable = [
        'name',
        'settings',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'settings' => [
            'default_timezone' => 'UTC',
            'features' => ['analytics', 'scheduling', 'multi_brand'],
        ],
    ];

    /**
     * Relationships
     */
    public function brands()
    {
        return $this->hasMany(Brand::class);
    }

    public function memberships()
    {
        return $this->hasManyThrough(Membership::class, Brand::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /**
     * Custom Methods
     */
    public function getTimezone(): string
    {
        $settings = $this->getAttribute('settings') ?? [];
        return $settings['default_timezone'] ?? 'UTC';
    }

    public function hasFeature(string $feature): bool
    {
        $settings = $this->getAttribute('settings') ?? [];
        $features = $settings['features'] ?? [];
        return in_array($feature, $features);
    }

    public function addFeature(string $feature): void
    {
        $settings = $this->getAttribute('settings') ?? [];
        $features = $settings['features'] ?? [];
        
        if (!in_array($feature, $features)) {
            $features[] = $feature;
            $settings['features'] = $features;
            $this->setAttribute('settings', $settings);
            $this->save();
        }
    }

    public function getTotalBrandsCount(): int
    {
        return $this->brands()->count();
    }

    public function getActiveBrandsCount(): int
    {
        return $this->brands()->where('active', true)->count();
    }
}