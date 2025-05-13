<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create roles
        $superAdminRole = Role::create(['name' => 'super-admin']);
        $adminRole = Role::create(['name' => 'admin']);
        $customerRole = Role::create(['name' => 'customer']);

        // Get all permissions
        $permissions = Permission::all();

        // Assign all permissions to super-admin
        $superAdminRole->syncPermissions($permissions);

        // Assign specific permissions to admin
        $adminPermissions = Permission::whereIn('name', [
            'view dashboard',
            'manage users',
            'view users',
            'view agents',
            'manage agents',
            'view subscriptions',
            'manage subscriptions',
        ])->get();
        
        $adminRole->syncPermissions($adminPermissions);

        // Assign specific permissions to customer
        $customerPermissions = Permission::whereIn('name', [
            'view own agents',
            'create own agents',
            'edit own agents',
            'delete own agents',
            'view own subscription',
            'manage own subscription',
        ])->get();
        
        $customerRole->syncPermissions($customerPermissions);
    }
}
