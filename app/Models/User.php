<?php
// app/Models/User.php

namespace App\Models;

use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;
use MongoDB\Laravel\Eloquent\Model;
use MongoDB\Laravel\Auth\User as Authenticatable;

class User extends Authenticatable implements AuthenticatableContract, AuthorizableContract
{
    use HasApiTokens, HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'profile',
        'social_accounts',
        'preferences',
        'subscription',
        'api_limits',
        'timezone',
        'email_verified_at',
        'last_login_at',
        'status',
        'roles',
        'permissions',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // FIXED: Remove array casts for MongoDB - MongoDB handles arrays natively
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'password' => 'hashed',
        // Remove these array casts - MongoDB handles them natively:
        // 'profile' => 'array',
        // 'social_accounts' => 'array', 
        // 'preferences' => 'array',
        // 'subscription' => 'array',
        // 'api_limits' => 'array',
        // 'roles' => 'array',
        // 'permissions' => 'array',
    ];

    protected $attributes = [
        'status' => 'active',
        'roles' => ['user'],
        'permissions' => [],
        'profile' => [],
        'social_accounts' => [],
        'preferences' => [
            'timezone' => 'UTC',
            'language' => 'en', 
            'theme' => 'light',
            'notifications' => [
                'email' => true,
                'push' => true,
                'post_success' => true,
                'post_failure' => true,
                'weekly_report' => true,
                'marketing_emails' => false,
            ],
            'posting' => [
                'default_time' => '09:00',
                'auto_hashtags' => true,
                'link_shortening' => true,
                'cross_posting' => false,
            ],
        ],
        'subscription' => [
            'plan' => 'free',
            'status' => 'active',
            'started_at' => null,
            'expires_at' => null,
            'limits' => [
                'posts_per_month' => 50,
                'social_accounts' => 3,
                'scheduled_posts' => 10,
                'team_members' => 1,
                'analytics_history_days' => 30,
            ],
        ],
        'api_limits' => [
            'requests_per_hour' => 100,
            'requests_today' => 0,
            'last_reset' => null,
            'rate_limit_exceeded_count' => 0,
        ],
    ];

    /**
     * Simple MongoDB Role System
     */
    
    public function hasRole(string $role): bool
    {
        $roles = $this->getAttribute('roles') ?? [];
        return in_array($role, $roles);
    }

    public function hasAnyRole(array $roles): bool
    {
        $userRoles = $this->getAttribute('roles') ?? [];
        return !empty(array_intersect($roles, $userRoles));
    }

    public function assignRole(string $role): void
    {
        $roles = $this->getAttribute('roles') ?? [];
        if (!in_array($role, $roles)) {
            $roles[] = $role;
            $this->setAttribute('roles', $roles);
            $this->save();
        }
    }

    public function removeRole(string $role): void
    {
        $roles = $this->getAttribute('roles') ?? [];
        $roles = array_values(array_filter($roles, fn($r) => $r !== $role));
        $this->setAttribute('roles', $roles);
        $this->save();
    }

    public function getRoleNames(): array
    {
        return $this->getAttribute('roles') ?? [];
    }

    public function hasPermission(string $permission): bool
    {
        // Check direct permissions
        $permissions = $this->getAttribute('permissions') ?? [];
        if (in_array($permission, $permissions)) {
            return true;
        }

        // Check role-based permissions
        $rolePermissions = $this->getRolePermissions();
        return in_array($permission, $rolePermissions);
    }

    public function getRolePermissions(): array
    {
        $allPermissions = [];
        $roles = $this->getAttribute('roles') ?? [];
        
        foreach ($roles as $role) {
            $permissions = $this->getPermissionsForRole($role);
            $allPermissions = array_merge($allPermissions, $permissions);
        }
        
        return array_unique($allPermissions);
    }

    private function getPermissionsForRole(string $role): array
    {
        $rolePermissions = [
            'admin' => [
                'create posts', 'edit posts', 'delete posts', 'schedule posts',
                'view analytics', 'manage team', 'manage billing', 'admin access',
                'manage users', 'manage roles', 'system settings'
            ],
            'manager' => [
                'create posts', 'edit posts', 'delete posts', 'schedule posts',
                'view analytics', 'manage team'
            ],
            'editor' => [
                'create posts', 'edit posts', 'schedule posts', 'view analytics'
            ],
            'user' => [
                'create posts', 'edit posts', 'schedule posts'
            ],
        ];

        return $rolePermissions[$role] ?? [];
    }

    public function getAllPermissions(): array
    {
        $directPermissions = $this->getAttribute('permissions') ?? [];
        $rolePermissions = $this->getRolePermissions();
        
        return array_unique(array_merge($directPermissions, $rolePermissions));
    }

    public function givePermissionTo(string $permission): void
    {
        $permissions = $this->getAttribute('permissions') ?? [];
        if (!in_array($permission, $permissions)) {
            $permissions[] = $permission;
            $this->setAttribute('permissions', $permissions);
            $this->save();
        }
    }

    public function connectedSocialAccounts()
    {
        $socialAccounts = $this->getAttribute('social_accounts') ?? [];
        return collect($socialAccounts)->filter(function ($account) {
            return isset($account['access_token']) && 
                   !empty($account['access_token']) &&
                   ($account['status'] ?? 'inactive') === 'active';
        });
    }

    public function canPostTo(string $platform): bool
    {
        $connected = $this->connectedSocialAccounts();
        return $connected->has($platform) && 
               $connected[$platform]['status'] === 'active' &&
               !$this->hasReachedPostingLimit();
    }

    public function getSubscriptionLimits(): array
    {
        $subscription = $this->getAttribute('subscription') ?? [];
        return $subscription['limits'] ?? [
            'posts_per_month' => 50,
            'social_accounts' => 3,
            'scheduled_posts' => 10,
            'team_members' => 1,
            'analytics_history_days' => 30,
        ];
    }

    public function hasReachedPostingLimit(): bool
    {
        return false; // TODO: Implement when posts model is ready
    }

    public function getRemainingPosts(): int
    {
        $limits = $this->getSubscriptionLimits();
        return $limits['posts_per_month'];
    }

    public function canAddSocialAccount(): bool
    {
        $limits = $this->getSubscriptionLimits();
        $connectedCount = $this->connectedSocialAccounts()->count();
        return $connectedCount < $limits['social_accounts'];
    }

    public function updateLastLogin(): void
    {
        $this->setAttribute('last_login_at', now());
        $this->save();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeWithPlan($query, string $plan)
    {
        return $query->where('subscription.plan', $plan);
    }

    public function scopeWithRole($query, string $role)
    {
        return $query->where('roles', $role);
    }
}