<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class RolesController extends Controller
{
    /**
     * Display a listing of roles and permissions.
     */
    public function index()
    {
        // Get all roles with their permissions
        $roles = $this->getRolesList();

        // Get all permissions grouped by category
        $permissions = Permission::all()->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'category' => $this->getPermissionCategory($permission->name),
            ];
        })->groupBy('category');

        // Get stats
        $stats = [
            'roles_count' => $roles->count(),
            'permissions_count' => Permission::count(),
            'users_with_roles' => User::whereHas('roles')->count(),
            'users_without_roles' => User::whereDoesntHave('roles')->count(),
        ];

        // Get all roles for assigning to permissions
        $allRoles = Role::all()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'is_protected' => in_array($role->name, ['super-admin', 'admin', 'customer']),
            ];
        });

        return Inertia::render('admin/Roles/Index', [
            'roles' => $roles,
            'permissions' => $permissions,
            'stats' => $stats,
            'allRoles' => $allRoles,
        ]);
    }

    /**
     * Show the form for creating a new role.
     */
    public function create()
    {
        // Get all permissions grouped by category
        $permissions = Permission::all()->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'category' => $this->getPermissionCategory($permission->name),
            ];
        })->groupBy('category');

        return Inertia::render('admin/Roles/Create', [
            'permissions' => $permissions,
        ]);
    }

    /**
     * Store a newly created role in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        try {
            DB::beginTransaction();

            // Create the role
            $role = Role::create(['name' => $request->name]);

            // Assign permissions to the role
            $role->syncPermissions($request->permissions);

            DB::commit();

            // Get updated roles list
            $roles = $this->getRolesList();

            return redirect()->route('admin.roles.index')
                ->with('success', 'Role created successfully.')
                ->with('roles', $roles);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating role: ' . $e->getMessage());
            return back()->with('error', 'Failed to create role: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified role.
     */
    public function show($id)
    {
        $role = Role::with('permissions')->findOrFail($id);

        // Get users with this role
        $users = User::role($role->name)
            ->latest()
            ->take(10)
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => $user->created_at_formatted,
                ];
            });

        // Get all permissions grouped by category
        $allPermissions = Permission::all()->map(function ($permission) use ($role) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'category' => $this->getPermissionCategory($permission->name),
                'assigned' => $role->hasPermissionTo($permission->name),
            ];
        })->groupBy('category');

        $roleData = [
            'id' => $role->id,
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('name'),
            'users_count' => User::role($role->name)->count(),
            'is_protected' => in_array($role->name, ['super-admin', 'admin', 'customer']),
        ];

        // Return a partial response for Inertia
        if (request()->header('X-Inertia-Partial-Data')) {
            return Inertia::render('admin/Roles/Index', [
                'role' => $roleData,
                'users' => $users,
                'allPermissions' => $allPermissions,
            ]);
        }

        // For a full page request, redirect to the roles index
        return redirect()->route('admin.roles.index');
    }

    /**
     * Show the form for editing the specified role.
     */
    public function edit($id)
    {
        $role = Role::with('permissions')->findOrFail($id);

        // Check if role is protected
        if (in_array($role->name, ['super-admin', 'admin', 'customer'])) {
            return redirect()->route('admin.roles.show', $id)
                ->with('error', 'Protected roles cannot be edited.');
        }

        // Get all permissions grouped by category
        $permissions = Permission::all()->map(function ($permission) use ($role) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'category' => $this->getPermissionCategory($permission->name),
                'assigned' => $role->hasPermissionTo($permission->name),
            ];
        })->groupBy('category');

        return Inertia::render('admin/Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
            ],
            'permissions' => $permissions,
        ]);
    }

    /**
     * Update the specified role in storage.
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        // Check if role is protected
        if (in_array($role->name, ['super-admin', 'admin', 'customer'])) {
            return redirect()->route('admin.roles.show', $id)
                ->with('error', 'Protected roles cannot be updated.');
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('roles', 'name')->ignore($id)],
            'permissions' => ['required', 'array'],
            'permissions.*' => ['exists:permissions,name'],
        ]);

        try {
            DB::beginTransaction();

            // Update the role name
            $role->name = $request->name;
            $role->save();

            // Sync permissions
            $role->syncPermissions($request->permissions);

            DB::commit();

            // Get updated roles list
            $roles = $this->getRolesList();

            return redirect()->route('admin.roles.show', $role->id)
                ->with('success', 'Role updated successfully.')
                ->with('roles', $roles);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating role: ' . $e->getMessage());
            return back()->with('error', 'Failed to update role: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        // Check if role is protected
        if (in_array($role->name, ['super-admin', 'admin', 'customer'])) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'Protected roles cannot be deleted.');
        }

        // Check if role has users
        if (User::role($role->name)->exists()) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'Cannot delete role with assigned users.');
        }

        try {
            $role->delete();

            // Get updated roles list
            $roles = $this->getRolesList();

            return redirect()->route('admin.roles.index')
                ->with('success', 'Role deleted successfully.')
                ->with('roles', $roles);
        } catch (\Exception $e) {
            Log::error('Error deleting role: ' . $e->getMessage());
            return redirect()->route('admin.roles.index')
                ->with('error', 'Failed to delete role: ' . $e->getMessage());
        }
    }

    /**
     * Get the category for a permission based on its name.
     */
    private function getPermissionCategory($permissionName)
    {
        if (strpos($permissionName, 'dashboard') !== false) {
            return 'Dashboard';
        } elseif (strpos($permissionName, 'user') !== false) {
            return 'Users';
        } elseif (strpos($permissionName, 'agent') !== false) {
            return 'Agents';
        } elseif (strpos($permissionName, 'subscription') !== false) {
            return 'Subscriptions';
        } elseif (strpos($permissionName, 'setting') !== false) {
            return 'Settings';
        } else {
            return 'Other';
        }
    }

    /**
     * Get formatted roles list for the table.
     */
    private function getRolesList()
    {
        return Role::with('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'users_count' => User::role($role->name)->count(),
                'is_protected' => in_array($role->name, ['super-admin', 'admin', 'customer']),
            ];
        });
    }
}
