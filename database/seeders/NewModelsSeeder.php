<?php
// database/seeders/NewModelsSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Organization;
use App\Models\Brand;
use App\Models\Membership;
use App\Models\Channel;
use App\Models\User;

class NewModelsSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🏗️ Seeding new models...');

        // Create Organizations
        $this->command->info('Creating Organizations...');
        $organizations = collect([
            Organization::factory()->enterprise()->create([
                'name' => 'J33WAKASUPUN Digital Agency'
            ]),
            Organization::factory()->basic()->create([
                'name' => 'Startup Marketing Hub'
            ]),
            Organization::factory()->create([
                'name' => 'Tech Innovation Group'
            ]),
        ]);

        $this->command->info("✅ Created {$organizations->count()} organizations");

        // Create Brands for each Organization
        $this->command->info('Creating Brands...');
        $brands = collect();
        
        $organizations->each(function ($organization) use ($brands) {
            $orgBrands = collect([
                Brand::factory()->create([
                    'organization_id' => $organization->_id,
                    'name' => $organization->name . ' - Main Brand',
                    'slug' => \Illuminate\Support\Str::slug($organization->name . ' main brand'),
                ]),
                Brand::factory()->withCustomBranding()->create([
                    'organization_id' => $organization->_id,
                    'name' => $organization->name . ' - Premium Brand',
                    'slug' => \Illuminate\Support\Str::slug($organization->name . ' premium brand'),
                ]),
            ]);
            $brands = $brands->merge($orgBrands);
        });

        $this->command->info("✅ Created {$brands->count()} brands");

        // Get or create users
        $adminUser = User::where('email', 'admin@socialmedia.com')->first();
        if (!$adminUser) {
            $adminUser = User::factory()->create([
                'name' => 'Admin User',
                'email' => 'admin@socialmedia.com',
            ]);
        }

        $users = collect([$adminUser]);
        $users = $users->merge(User::factory(5)->create());

        $this->command->info("✅ Using {$users->count()} users");

        // Create Memberships
        $this->command->info('Creating Memberships...');
        $memberships = collect();
        
        $brands->each(function ($brand, $index) use ($users, $memberships) {
            // Each brand gets an owner
            $owner = $users->random();
            $membership = Membership::factory()->owner()->create([
                'user_id' => $owner->_id,
                'brand_id' => $brand->_id,
                'invited_by' => null,
            ]);
            $memberships->push($membership);

            // Add 1-3 additional team members per brand
            $teamCount = fake()->numberBetween(1, 3);
            for ($i = 0; $i < $teamCount; $i++) {
                $teamMember = $users->where('_id', '!=', $owner->_id)->random();
                $role = fake()->randomElement(['MANAGER', 'EDITOR', 'VIEWER']);
                
                $teamMembership = Membership::factory()->state([
                    'role' => $role
                ])->create([
                    'user_id' => $teamMember->_id,
                    'brand_id' => $brand->_id,
                    'invited_by' => $owner->_id,
                ]);
                $memberships->push($teamMembership);
            }
        });

        $this->command->info("✅ Created {$memberships->count()} memberships");

        // Create Channels
        $this->command->info('Creating Channels...');
        $channels = collect();
        
        $brands->each(function ($brand) use ($channels) {
            // Each brand gets 2-4 channels
            $channelCount = fake()->numberBetween(2, 4);
            $providers = ['twitter', 'facebook', 'instagram', 'linkedin', 'youtube'];
            $selectedProviders = fake()->randomElements($providers, $channelCount);

            foreach ($selectedProviders as $provider) {
                $channel = Channel::factory()->state([
                    'provider' => $provider
                ])->connected()->create([
                    'brand_id' => $brand->_id,
                ]);
                $channels->push($channel);
            }
        });

        $this->command->info("✅ Created {$channels->count()} channels");

        // Summary
        $this->command->info('');
        $this->command->info('🎉 NEW MODELS SEEDING COMPLETE!');
        $this->command->info("📊 Summary:");
        $this->command->info("   Organizations: {$organizations->count()}");
        $this->command->info("   Brands: {$brands->count()}");
        $this->command->info("   Memberships: {$memberships->count()}");
        $this->command->info("   Channels: {$channels->count()}");
        $this->command->info("   Users: {$users->count()}");
    }
}