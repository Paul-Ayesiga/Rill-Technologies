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

class RolesPermissionsController extends Controller
{
    /**
     * Display a listing of roles and permissions.
     */
    public function index()
    {
        // Get all roles with their permissions
        $roles = Role::with('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'users_count' => User::role($role->name)->count(),
                'is_protected' => in_array($role->name, ['super-admin', 'admin', 'customer']),
            ];
        });

        // Get all permissions with their roles
        $permissions = Permission::with('roles')->get()->map(function ($permission) {
            return [
                'id' => $permission->id,
                'name' => $permission->name,
                'roles' => $permission->roles->pluck('name'),
                'category' => $this->getPermissionCategory($permission->name),
            ];
        });

        // Group permissions by category
        $groupedPermissions = $permissions->groupBy('category');

        // Get stats
        $stats = [
            'roles_count' => $roles->count(),
            'permissions_count' => Permission::count(),
            'users_with_roles' => User::whereHas('roles')->count(),
            'users_without_roles' => User::whereDoesntHave('roles')->count(),
        ];

        return Inertia::render('admin/RolesPermissions/Index', [
            'roles' => $roles,
            'permissions' => $groupedPermissions,
            'stats' => $stats,
            'categories' => $this->getCategories(),
        ]);
    }

    /**
     * Store a newly created role in storage.
     */
    public function storeRole(Request $request)
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

            return redirect()->back()
                ->with('success', 'Role created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating role: ' . $e->getMessage());
            return back()->with('error', 'Failed to create role: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified role in storage.
     */
    public function updateRole(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        // Check if role is protected
        if (in_array($role->name, ['super-admin', 'admin', 'customer'])) {
            return redirect()->back()
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

            return redirect()->back()
                ->with('success', 'Role updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating role: ' . $e->getMessage());
            return back()->with('error', 'Failed to update role: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified role from storage.
     */
    public function destroyRole($id)
    {
        $role = Role::findOrFail($id);

        // Check if role is protected
        if (in_array($role->name, ['super-admin', 'admin', 'customer'])) {
            return redirect()->back()
                ->with('error', 'Protected roles cannot be deleted.');
        }

        // Check if role has users
        if (User::role($role->name)->exists()) {
            return redirect()->back()
                ->with('error', 'Cannot delete role with assigned users.');
        }

        try {
            $role->delete();
            return redirect()->back()
                ->with('success', 'Role deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Error deleting role: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to delete role: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created permission in storage.
     */
    public function storePermission(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name'],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,name'],
        ]);

        try {
            DB::beginTransaction();

            // Create the permission
            $permission = Permission::create(['name' => $request->name]);

            // Assign roles to the permission
            if ($request->has('roles') && !empty($request->roles)) {
                $roles = Role::whereIn('name', $request->roles)->get();
                foreach ($roles as $role) {
                    $role->givePermissionTo($permission);
                }
            }

            DB::commit();

            return redirect()->back()
                ->with('success', 'Permission created successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating permission: ' . $e->getMessage());
            return back()->with('error', 'Failed to create permission: ' . $e->getMessage());
        }
    }

    /**
     * Update the specified permission in storage.
     */
    public function updatePermission(Request $request, $id)
    {
        $permission = Permission::findOrFail($id);

        $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('permissions', 'name')->ignore($id)],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,name'],
        ]);

        try {
            DB::beginTransaction();

            // Update the permission name
            $permission->name = $request->name;
            $permission->save();

            // Sync roles
            if ($request->has('roles')) {
                $roles = Role::whereIn('name', $request->roles)->get();
                $permission->roles()->sync($roles);
            } else {
                $permission->roles()->sync([]);
            }

            DB::commit();

            return redirect()->back()
                ->with('success', 'Permission updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating permission: ' . $e->getMessage());
            return back()->with('error', 'Failed to update permission: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified permission from storage.
     */
    public function destroyPermission($id)
    {
        $permission = Permission::findOrFail($id);

        try {
            $permission->delete();
            return redirect()->back()
                ->with('success', 'Permission deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Error deleting permission: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to delete permission: ' . $e->getMessage());
        }
    }

    /**
     * Get role details with users.
     */
    public function getRoleDetails($id)
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

        return response()->json([
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'users_count' => User::role($role->name)->count(),
                'is_protected' => in_array($role->name, ['super-admin', 'admin', 'customer']),
            ],
            'users' => $users,
        ]);
    }

    /**
     * Get permission details with roles.
     */
    public function getPermissionDetails($id)
    {
        $permission = Permission::with('roles')->findOrFail($id);

        return response()->json([
            'permission' => [
                'id' => $permission->id,
                'name' => $permission->name,
                'roles' => $permission->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'is_protected' => in_array($role->name, ['super-admin', 'admin', 'customer']),
                    ];
                }),
                'category' => $this->getPermissionCategory($permission->name),
            ],
        ]);
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
     * Get available permission categories.
     */
    private function getCategories()
    {
        return [
            'Dashboard',
            'Users',
            'Agents',
            'Subscriptions',
            'Settings',
            'Other',
        ];
    }
}
