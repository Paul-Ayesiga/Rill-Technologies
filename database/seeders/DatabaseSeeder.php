<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Run the permission seeder first
        $this->call(PermissionSeeder::class);

        // Then run the role seeder to create roles and assign permissions
        $this->call(RoleSeeder::class);

        // Create the super admin user
        $this->call(SuperAdminSeeder::class);

        // Create a test user with customer role
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Assign customer role to the test user
        $user->assignRole('customer');

        // Uncomment to create more test users
        // User::factory(10)->create()->each(function ($user) {
        //     $user->assignRole('customer');
        // });
    }
}
