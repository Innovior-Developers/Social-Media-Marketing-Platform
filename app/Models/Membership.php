<?php
// app/Models/Membership.php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Membership extends Model
{
    use HasFactory;

    protected $connection = 'mongodb';
    protected $collection = 'memberships';

    protected $fillable = [
        'user_id',
        'brand_id',
        'role',
        'permissions',
        'invited_by',
        'joined_at',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $attributes = [
        'role' => 'VIEWER',
        'permissions' => [],
    ];

    /**
     * Relationships
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Scopes
     */
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', strtoupper($role));
    }

    public function scopeOwners($query)
    {
        return $query->where('role', 'OWNER');
    }

    public function scopeManagers($query)
    {
        return $query->where('role', 'MANAGER');
    }

    public function scopeEditors($query)
    {
        return $query->where('role', 'EDITOR');
    }

    /**
     * Custom Methods
     */
    public function hasPermission(string $permission): bool
    {
        $permissions = $this->getAttribute('permissions') ?? [];
        return in_array($permission, $permissions) || $this->hasRolePermission($permission);
    }

    public function hasRolePermission(string $permission): bool
    {
        $rolePermissions = [
            'OWNER' => [
                'manage_brand', 'manage_team', 'create_posts', 'edit_posts', 
                'delete_posts', 'schedule_posts', 'view_analytics', 'manage_channels'
            ],
            'MANAGER' => [
                'create_posts', 'edit_posts', 'delete_posts', 
                'schedule_posts', 'view_analytics', 'manage_channels'
            ],
            'EDITOR' => [
                'create_posts', 'edit_posts', 'schedule_posts', 'view_analytics'
            ],
            'VIEWER' => [
                'view_analytics'
            ],
        ];

        $permissions = $rolePermissions[$this->role] ?? [];
        return in_array($permission, $permissions);
    }

    public function canManageBrand(): bool
    {
        return $this->hasPermission('manage_brand');
    }

    public function canCreatePosts(): bool
    {
        return $this->hasPermission('create_posts');
    }

    public function canManageTeam(): bool
    {
        return $this->hasPermission('manage_team');
    }

    public function isOwner(): bool
    {
        return $this->role === 'OWNER';
    }

    public function isManager(): bool
    {
        return $this->role === 'MANAGER';
    }

    public function getRolePermissions(): array
    {
        $rolePermissions = [
            'OWNER' => [
                'manage_brand', 'manage_team', 'create_posts', 'edit_posts', 
                'delete_posts', 'schedule_posts', 'view_analytics', 'manage_channels'
            ],
            'MANAGER' => [
                'create_posts', 'edit_posts', 'delete_posts', 
                'schedule_posts', 'view_analytics', 'manage_channels'
            ],
            'EDITOR' => [
                'create_posts', 'edit_posts', 'schedule_posts', 'view_analytics'
            ],
            'VIEWER' => [
                'view_analytics'
            ],
        ];

        return $rolePermissions[$this->role] ?? [];
    }
}