<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin dashboard permissions
        Permission::create(['name' => 'view dashboard']);
        
        // User management permissions
        Permission::create(['name' => 'view users']);
        Permission::create(['name' => 'create users']);
        Permission::create(['name' => 'edit users']);
        Permission::create(['name' => 'delete users']);
        Permission::create(['name' => 'manage users']);
        
        // Agent permissions (admin)
        Permission::create(['name' => 'view agents']);
        Permission::create(['name' => 'create agents']);
        Permission::create(['name' => 'edit agents']);
        Permission::create(['name' => 'delete agents']);
        Permission::create(['name' => 'manage agents']);
        
        // Subscription permissions (admin)
        Permission::create(['name' => 'view subscriptions']);
        Permission::create(['name' => 'manage subscriptions']);
        
        // Customer permissions
        Permission::create(['name' => 'view own agents']);
        Permission::create(['name' => 'create own agents']);
        Permission::create(['name' => 'edit own agents']);
        Permission::create(['name' => 'delete own agents']);
        Permission::create(['name' => 'view own subscription']);
        Permission::create(['name' => 'manage own subscription']);
        
        // Settings permissions
        Permission::create(['name' => 'manage settings']);
        Permission::create(['name' => 'view settings']);
    }
}
