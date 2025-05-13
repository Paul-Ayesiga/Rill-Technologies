<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class PermissionsController extends Controller
{
    /**
     * Display a listing of permissions.
     */
    public function index()
    {
        // Get all permissions grouped by category
        $groupedPermissions = $this->getPermissionsList();

        // Get all roles for assigning to permissions
        $roles = Role::all()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'is_protected' => in_array($role->name, ['super-admin', 'admin', 'customer']),
            ];
        });

        return Inertia::render('admin/Permissions/Index', [
            'permissions' => $groupedPermissions,
            'roles' => $roles,
            'categories' => $this->getCategories(),
        ]);
    }

    /**
     * Show the form for creating a new permission.
     */
    public function create()
    {
        // Get all roles for assigning to the new permission
        $roles = Role::all()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'is_protected' => in_array($role->name, ['super-admin', 'admin', 'customer']),
            ];
        });

        return Inertia::render('admin/Permissions/Create', [
            'roles' => $roles,
            'categories' => $this->getCategories(),
        ]);
    }

    /**
     * Store a newly created permission in storage.
     */
    public function store(Request $request)
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

            // Get updated permissions list
            $permissions = $this->getPermissionsList();

            return redirect()->route('admin.roles.index')
                ->with('success', 'Permission created successfully.')
                ->with('permissions', $permissions);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating permission: ' . $e->getMessage());
            return redirect()->route('admin.roles.index')
                ->with('error', 'Failed to create permission: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified permission.
     */
    public function show($id)
    {
        $permission = Permission::with('roles')->findOrFail($id);

        $permissionData = [
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
        ];

        // Return a partial response for Inertia
        if (request()->header('X-Inertia-Partial-Data')) {
            return Inertia::render('admin/Roles/Index', [
                'permission' => $permissionData,
            ]);
        }

        // For a full page request, redirect to the roles page
        return redirect()->route('admin.roles.index');
    }

    /**
     * Show the form for editing the specified permission.
     */
    public function edit($id)
    {
        $permission = Permission::with('roles')->findOrFail($id);

        // Get all roles
        $roles = Role::all()->map(function ($role) use ($permission) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'is_protected' => in_array($role->name, ['super-admin', 'admin', 'customer']),
                'assigned' => $permission->roles->contains('id', $role->id),
            ];
        });

        return Inertia::render('admin/Permissions/Edit', [
            'permission' => [
                'id' => $permission->id,
                'name' => $permission->name,
                'roles' => $permission->roles->pluck('name'),
            ],
            'roles' => $roles,
            'categories' => $this->getCategories(),
        ]);
    }

    /**
     * Update the specified permission in storage.
     */
    public function update(Request $request, $id)
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

            // Get updated permissions list
            $permissions = $this->getPermissionsList();

            return redirect()->route('admin.roles.index')
                ->with('success', 'Permission updated successfully.')
                ->with('permissions', $permissions);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating permission: ' . $e->getMessage());
            return redirect()->route('admin.roles.index')
                ->with('error', 'Failed to update permission: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified permission from storage.
     */
    public function destroy($id)
    {
        $permission = Permission::findOrFail($id);

        try {
            $permission->delete();

            // Get updated permissions list
            $permissions = $this->getPermissionsList();

            return redirect()->route('admin.roles.index')
                ->with('success', 'Permission deleted successfully.')
                ->with('permissions', $permissions);
        } catch (\Exception $e) {
            Log::error('Error deleting permission: ' . $e->getMessage());
            return redirect()->route('admin.roles.index')
                ->with('error', 'Failed to delete permission: ' . $e->getMessage());
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

    /**
     * Get formatted permissions list grouped by category.
     */
    private function getPermissionsList()
    {
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
        return $permissions->groupBy('category');
    }
}
