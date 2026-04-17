<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user
        User::firstOrCreate(
            ['username' => 'admin'],
            [
                'password' => Hash::make('password'),
                'role'     => 'admin',
            ]
        );

        // Create regular user
        User::firstOrCreate(
            ['username' => 'user1'],
            [
                'password' => Hash::make('password'),
                'role'     => 'user',
            ]
        );

        // Create technician
        User::firstOrCreate(
            ['username' => 'tech1'],
            [
                'password' => Hash::make('password'),
                'role'     => 'technician',
            ]
        );

        // Seed feature flags
        FeatureFlag::updateOrCreate(
            ['key' => 'recommended_enabled'],
            [
                'enabled'    => true,
                'reason'     => 'Default: recommendations enabled',
                'updated_at' => now(),
            ]
        );
    }
}
