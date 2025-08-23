<?php
// app/Models/Brand.php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'mongodb';
    protected $collection = 'brands';

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'settings',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $attributes = [
        'active' => true,
        'settings' => [
            'timezone' => 'UTC',
            'default_publish_time' => '09:00',
            'branding' => [
                'logo_url' => null,
                'primary_color' => '#3b82f6',
            ],
        ],
    ];

    /**
     * Relationships
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function channels()
    {
        return $this->hasMany(Channel::class);
    }

    public function memberships()
    {
        return $this->hasMany(Membership::class);
    }

    public function posts()
    {
        return $this->hasMany(SocialMediaPost::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByOrganization($query, $organizationId)
    {
        return $query->where('organization_id', $organizationId);
    }

    /**
     * Custom Methods
     */
    public function getTimezone(): string
    {
        $settings = $this->getAttribute('settings') ?? [];
        return $settings['timezone'] ?? $this->organization->getTimezone() ?? 'UTC';
    }

    public function getDefaultPublishTime(): string
    {
        $settings = $this->getAttribute('settings') ?? [];
        return $settings['default_publish_time'] ?? '09:00';
    }

    public function getBrandingInfo(): array
    {
        $settings = $this->getAttribute('settings') ?? [];
        return $settings['branding'] ?? [
            'logo_url' => null,
            'primary_color' => '#3b82f6',
        ];
    }

    public function updateBranding(array $branding): void
    {
        $settings = $this->getAttribute('settings') ?? [];
        $settings['branding'] = array_merge($settings['branding'] ?? [], $branding);
        $this->setAttribute('settings', $settings);
        $this->save();
    }

    public function getConnectedChannelsCount(): int
    {
        return $this->channels()->where('connection_status', 'connected')->count();
    }

    public function getTotalPostsCount(): int
    {
        return $this->posts()->count();
    }

    public function getThisMonthPostsCount(): int
    {
        return $this->posts()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }
}