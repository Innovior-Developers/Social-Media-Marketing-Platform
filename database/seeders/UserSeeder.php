<?php
// database/seeders/UserSeeder.php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Create permissions
        $permissions = [
            'create posts',
            'edit posts',
            'delete posts',
            'schedule posts',
            'view analytics',
            'manage team',
            'manage billing',
            'admin access',
        ];

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles
        $adminRole = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $managerRole = Role::create(['name' => 'manager', 'guard_name' => 'web']);
        $editorRole = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $userRole = Role::create(['name' => 'user', 'guard_name' => 'web']);

        // Assign permissions to roles
        $adminRole->givePermissionTo(Permission::all());
        $managerRole->givePermissionTo(['create posts', 'edit posts', 'delete posts', 'schedule posts', 'view analytics', 'manage team']);
        $editorRole->givePermissionTo(['create posts', 'edit posts', 'schedule posts', 'view analytics']);
        $userRole->givePermissionTo(['create posts', 'edit posts', 'schedule posts']);

        // Create admin user
        $admin = User::create([
            'name' => 'J33WAKASUPUN',
            'email' => 'admin@socialmedia.com',
            'password' => 'password123',
            'profile' => [
                'company' => 'Social Media Marketing Platform',
                'bio' => 'Platform Administrator and Lead Developer',
                'location' => 'Global',
            ],
            'subscription' => [
                'plan' => 'enterprise',
                'status' => 'active',
                'started_at' => now()->format('Y-m-d H:i:s'),
            ],
            'email_verified_at' => now(),
        ]);
        $admin->assignRole('admin');

        // Create sample users
        User::factory(10)->create()->each(function ($user) {
            $user->assignRole('user');
        });

        User::factory(3)->create()->each(function ($user) {
            $user->assignRole('editor');
        });

        User::factory(2)->create()->each(function ($user) {
            $user->assignRole('manager');
        });
    }
}