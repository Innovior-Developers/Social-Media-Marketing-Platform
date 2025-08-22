<?php
// database/seeders/SimpleUserSeeder.php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SimpleUserSeeder extends Seeder
{
    public function run(): void
    {
        // Clear existing users
        User::query()->delete();

        $this->command->info('Creating users for Social Media Marketing Platform...');

        // Create admin user
        $admin = User::create([
            'name' => 'J33WAKASUPUN',
            'email' => 'admin@socialmedia.com',
            'password' => Hash::make('password123'),
            'roles' => ['admin'],
            'permissions' => ['system access'],
            'profile' => [
                'company' => 'Social Media Marketing Platform',
                'bio' => 'Platform Administrator and Lead Developer',
                'location' => 'Global',
                'website' => 'https://socialmedia.com',
            ],
            'subscription' => [
                'plan' => 'enterprise',
                'status' => 'active',
                'started_at' => now()->format('Y-m-d H:i:s'),
                'limits' => [
                    'posts_per_month' => 10000,
                    'social_accounts' => 50,
                    'scheduled_posts' => 1000,
                    'team_members' => 100,
                    'analytics_history_days' => 365,
                ],
            ],
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $this->command->info('✅ Admin user created: admin@socialmedia.com');

        // Create manager user
        $manager = User::create([
            'name' => 'Sarah Manager',
            'email' => 'manager@socialmedia.com',
            'password' => Hash::make('password123'),
            'roles' => ['manager'],
            'permissions' => [],
            'profile' => [
                'company' => 'Digital Marketing Agency',
                'bio' => 'Social media marketing manager',
                'location' => 'New York, USA',
            ],
            'subscription' => [
                'plan' => 'pro',
                'status' => 'active',
                'started_at' => now()->subDays(30)->format('Y-m-d H:i:s'),
                'limits' => [
                    'posts_per_month' => 1000,
                    'social_accounts' => 25,
                    'scheduled_posts' => 200,
                ],
            ],
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $this->command->info('✅ Manager user created: manager@socialmedia.com');

        // Create editor user
        $editor = User::create([
            'name' => 'Alex Editor',
            'email' => 'editor@socialmedia.com',
            'password' => Hash::make('password123'),
            'roles' => ['editor'],
            'permissions' => [],
            'profile' => [
                'company' => 'Content Creation Studio',
                'bio' => 'Content creator and social media editor',
                'location' => 'London, UK',
            ],
            'subscription' => [
                'plan' => 'basic',
                'status' => 'active',
                'started_at' => now()->subDays(15)->format('Y-m-d H:i:s'),
                'limits' => [
                    'posts_per_month' => 200,
                    'social_accounts' => 10,
                    'scheduled_posts' => 50,
                ],
            ],
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $this->command->info('✅ Editor user created: editor@socialmedia.com');

        // Create regular user
        $user = User::create([
            'name' => 'John User',
            'email' => 'user@socialmedia.com',
            'password' => Hash::make('password123'),
            'roles' => ['user'],
            'permissions' => [],
            'profile' => [
                'company' => 'Freelance',
                'bio' => 'Social media enthusiast',
                'location' => 'California, USA',
            ],
            'subscription' => [
                'plan' => 'free',
                'status' => 'active',
                'started_at' => now()->subDays(5)->format('Y-m-d H:i:s'),
                'limits' => [
                    'posts_per_month' => 50,
                    'social_accounts' => 3,
                    'scheduled_posts' => 10,
                ],
            ],
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $this->command->info('✅ Regular user created: user@socialmedia.com');

        // Create sample users with Factory
        $this->command->info('Creating 10 sample users...');
        
        User::factory(5)->withPlan('free')->withRole('user')->create();
        User::factory(3)->withPlan('basic')->withRole('user')->create();
        User::factory(2)->withPlan('pro')->withRole('editor')->create();

        $totalUsers = User::count();

        $this->command->info("🎉 Successfully created {$totalUsers} users!");
        $this->command->info('');
        $this->command->info('📋 Login Credentials:');
        $this->command->info('- Admin: admin@socialmedia.com / password123');
        $this->command->info('- Manager: manager@socialmedia.com / password123');
        $this->command->info('- Editor: editor@socialmedia.com / password123');
        $this->command->info('- User: user@socialmedia.com / password123');
        $this->command->info('');
        $this->command->info('🚀 Ready to test: curl http://localhost:8000/test-auth');
    }
}