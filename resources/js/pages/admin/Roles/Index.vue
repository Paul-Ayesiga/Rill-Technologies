<script setup lang="ts">
import { Head, Deferred, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { ref, computed, watch } from 'vue';
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow
} from '@/components/ui/table';
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem,
  DropdownMenuTrigger, DropdownMenuSeparator
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
  FlexRender, getCoreRowModel, getFilteredRowModel,
  getPaginationRowModel, getSortedRowModel, useVueTable
} from '@tanstack/vue-table';
import type {
  ColumnDef, ColumnFiltersState, SortingState, VisibilityState
} from '@tanstack/vue-table';
import {
  ChevronDown, MoreHorizontal, Eye, Search, ArrowUpDown,
  ShieldCheck, PlusCircle, Edit, Trash2, Users
} from 'lucide-vue-next';
import { h } from 'vue';
import { valueUpdater } from '@/components/ui/table/utils';
import { toast } from 'vue-sonner';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Label } from '@/components/ui/label';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader,
  DialogTitle, DialogTrigger
} from '@/components/ui/dialog';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue
} from '@/components/ui/select';

// Define the Role type
interface Role {
  id: number;
  name: string;
  permissions: string[];
  users_count: number;
  is_protected: boolean;
}

// Define the Permission type
interface Permission {
  id: number;
  name: string;
  category: string;
  roles?: string[];
}

// Define the Stats type
interface Stats {
  roles_count: number;
  permissions_count: number;
  users_with_roles: number;
  users_without_roles: number;
}

// Define the User type
interface User {
  id: number;
  name: string;
  email: string;
  created_at: string;
}

// Get props
const props = defineProps<{
  roles: Role[];
  permissions: Record<string, Permission[]>;
  stats: Stats;
  allRoles: { id: number; name: string; is_protected: boolean; }[];
}>();

// Table state
const sorting = ref<SortingState>([]);
const columnFilters = ref<ColumnFiltersState>([]);
const columnVisibility = ref<VisibilityState>({});
const rowSelection = ref({});

// Role modal states
const createRoleModalOpen = ref(false);
const viewRoleModalOpen = ref(false);
const editRoleModalOpen = ref(false);
const deleteRoleModalOpen = ref(false);

// Selected role for view/edit/delete
const selectedRole = ref<Role | null>(null);
const selectedRoleDetails = ref<{
  role: Role;
  users: User[];
  allPermissions: Record<string, any[]>;
} | null>(null);

// Create role form
const createRoleForm = useForm({
  name: '',
  permissions: [] as string[],
});

// Edit role form
const editRoleForm = useForm({
  name: '',
  permissions: [] as string[],
});

// Permission modal states
const createPermissionModalOpen = ref(false);
const viewPermissionModalOpen = ref(false);
const editPermissionModalOpen = ref(false);
const deletePermissionModalOpen = ref(false);

// Selected permission for view/edit/delete
const selectedPermission = ref<Permission | null>(null);
const selectedPermissionDetails = ref<{
  permission: {
    id: number;
    name: string;
    roles: { id: number; name: string; is_protected: boolean; }[];
    category: string;
  };
} | null>(null);

// Create permission form
const createPermissionForm = useForm({
  name: '',
  roles: [] as string[],
});

// Edit permission form
const editPermissionForm = useForm({
  name: '',
  roles: [] as string[],
});

// Selected category for permission naming convention
const selectedCategory = ref(props.permissions ? Object.keys(props.permissions)[0] : 'Other');

// Define table columns
const columns: ColumnDef<Role>[] = [
  {
    accessorKey: 'name',
    header: ({ column }: any) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      }, () => ['Role Name', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })])
    },
    cell: ({ row }: any) => h('div', { class: 'font-medium' }, row.getValue('name')),
  },
  {
    accessorKey: 'permissions',
    header: 'Permissions',
    cell: ({ row }: any) => {
      const permissions = row.getValue('permissions') as string[];
      return h('div', { class: 'flex flex-wrap gap-1' }, [
        permissions.length > 0
          ? permissions.slice(0, 3).map(permission =>
              h(Badge, { variant: 'outline', class: 'text-xs' }, () => permission)
            ).concat(
              permissions.length > 3
                ? [h(Badge, { variant: 'outline', class: 'text-xs' }, () => `+${permissions.length - 3} more`)]
                : []
            )
          : h('span', { class: 'text-muted-foreground text-sm' }, 'No permissions')
      ]);
    },
  },
  {
    accessorKey: 'users_count',
    header: ({ column }: any) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      }, () => ['Users', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })])
    },
    cell: ({ row }: any) => h('div', { class: 'text-center' }, row.getValue('users_count')),
  },
  {
    accessorKey: 'is_protected',
    header: 'Status',
    cell: ({ row }: any) => {
      const isProtected = row.getValue('is_protected') as boolean;
      return h(Badge, {
        variant: isProtected ? 'default' : 'outline',
        class: isProtected ? 'bg-amber-500' : '',
      }, () => isProtected ? 'Protected' : 'Custom');
    },
  },
  {
    id: 'actions',
    enableHiding: false,
    cell: ({ row }: any) => {
      const role = row.original;

      return h(DropdownMenu, {}, {
        default: () => [
          h(DropdownMenuTrigger, { asChild: true }, () =>
            h(Button, { variant: 'ghost', class: 'h-8 w-8 p-0' }, () =>
              h(MoreHorizontal, { class: 'h-4 w-4' })
            )
          ),
          h(DropdownMenuContent, { align: 'end' }, () => [
            h(DropdownMenuItem, {
              onClick: () => viewRole(role)
            }, () => [
              h(Eye, { class: 'mr-2 h-4 w-4' }),
              'View Details'
            ]),
            !role.is_protected ? [
              h(DropdownMenuSeparator),
              h(DropdownMenuItem, {
                onClick: () => openEditRoleModal(role)
              }, () => [
                h(Edit, { class: 'mr-2 h-4 w-4' }),
                'Edit Role'
              ]),
              h(DropdownMenuItem, {
                onClick: () => openDeleteRoleModal(role),
                class: 'text-red-600'
              }, () => [
                h(Trash2, { class: 'mr-2 h-4 w-4' }),
                'Delete Role'
              ])
            ] : null
          ].filter(Boolean))
        ]
      });
    },
  },
];

// Create a reactive reference to the roles data
const rolesData = ref(props.roles);

// Initialize table with computed data property
const table = useVueTable({
  get data() { return rolesData.value },
  columns,
  getCoreRowModel: getCoreRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  onSortingChange: (updaterOrValue: any) => valueUpdater(updaterOrValue, sorting),
  onColumnFiltersChange: (updaterOrValue: any) => valueUpdater(updaterOrValue, columnFilters),
  onColumnVisibilityChange: (updaterOrValue: any) => valueUpdater(updaterOrValue, columnVisibility),
  onRowSelectionChange: (updaterOrValue: any) => valueUpdater(updaterOrValue, rowSelection),
  state: {
    get sorting() { return sorting.value },
    get columnFilters() { return columnFilters.value },
    get columnVisibility() { return columnVisibility.value },
    get rowSelection() { return rowSelection.value },
  },
});

// Function to toggle all permissions in a category for create role form
function toggleCategoryCreateRole(category: string, checked: boolean) {
  const categoryPermissions = props.permissions[category].map((p: Permission) => p.name);

  if (checked) {
    // Add all permissions from this category that aren't already selected
    categoryPermissions.forEach((permission: string) => {
      if (!createRoleForm.permissions.includes(permission)) {
        createRoleForm.permissions.push(permission);
      }
    });
  } else {
    // Remove all permissions from this category
    createRoleForm.permissions = createRoleForm.permissions.filter(
      (permission: string) => !categoryPermissions.includes(permission)
    );
  }
}

// Function to check if all permissions in a category are selected for create role form
function isCategorySelectedCreateRole(category: string) {
  const categoryPermissions = props.permissions[category].map((p: Permission) => p.name);
  return categoryPermissions.every((permission: string) => createRoleForm.permissions.includes(permission));
}

// Function to toggle all permissions in a category for edit role form
function toggleCategoryEditRole(category: string, checked: boolean) {
  const categoryPermissions = props.permissions[category].map((p: Permission) => p.name);

  if (checked) {
    // Add all permissions from this category that aren't already selected
    categoryPermissions.forEach((permission: string) => {
      if (!editRoleForm.permissions.includes(permission)) {
        editRoleForm.permissions.push(permission);
      }
    });
  } else {
    // Remove all permissions from this category
    editRoleForm.permissions = editRoleForm.permissions.filter(
      (permission: string) => !categoryPermissions.includes(permission)
    );
  }
}

// Function to check if all permissions in a category are selected for edit role form
function isCategorySelectedEditRole(category: string) {
  const categoryPermissions = props.permissions[category].map((p: Permission) => p.name);
  return categoryPermissions.every((permission: string) => editRoleForm.permissions.includes(permission));
}

// Function to submit create role form
function submitCreateRoleForm() {
  createRoleForm.post(route('admin.roles.store'), {
    onSuccess: (page) => {
      // Update the roles data with the new data from the response
      if (page.props.roles) {
        rolesData.value = page.props.roles;
      }

      createRoleModalOpen.value = false;
      createRoleForm.reset();
      toast.success('Role created successfully');
    },
  });
}

// Function to submit edit role form
function submitEditRoleForm() {
  if (!selectedRole.value) return;

  editRoleForm.put(route('admin.roles.update', selectedRole.value.id), {
    onSuccess: (page: any) => {
      // Update the roles data with the new data from the response
      if (page.props.roles) {
        rolesData.value = page.props.roles;
      }

      editRoleModalOpen.value = false;
      editRoleForm.reset();
      selectedRole.value = null;
      toast.success('Role updated successfully');
    },
  });
}

// Function to delete a role
function deleteRole() {
  if (!selectedRole.value) return;

  router.delete(route('admin.roles.destroy', selectedRole.value.id), {
    onSuccess: (page: any) => {
      // Update the roles data with the new data from the response
      if (page.props.roles) {
        rolesData.value = page.props.roles;
      }

      deleteRoleModalOpen.value = false;
      selectedRole.value = null;
      toast.success('Role deleted successfully');
    },
    onError: (errors: any) => {
      toast.error('Failed to delete role', {
        description: errors.message || 'There was an error deleting the role.'
      });
    }
  });
}

// Function to view role details
function viewRole(role: Role) {
  selectedRole.value = role;

  router.visit(route('admin.roles.show', role.id), {
    only: ['role', 'users', 'allPermissions'],
    preserveState: true,
    preserveScroll: true,
    onSuccess: (page: any) => {
      if (page.props.role && page.props.users && page.props.allPermissions) {
        selectedRoleDetails.value = {
          role: page.props.role,
          users: page.props.users,
          allPermissions: page.props.allPermissions
        };
        viewRoleModalOpen.value = true;
      } else {
        toast.error('Failed to load role details: incomplete data');
      }
    },
    onError: () => {
      toast.error('Failed to load role details');
    }
  });
}

// Function to open edit role modal
function openEditRoleModal(role: Role) {
  selectedRole.value = role;
  editRoleForm.name = role.name;
  editRoleForm.permissions = [...role.permissions];
  editRoleModalOpen.value = true;
}

// Function to open delete role modal
function openDeleteRoleModal(role: Role) {
  selectedRole.value = role;
  deleteRoleModalOpen.value = true;
}

// Function to toggle a permission in an array
function togglePermission(permissionsArray: string[], permissionName: string, isChecked: boolean) {
  if (isChecked && !permissionsArray.includes(permissionName)) {
    permissionsArray.push(permissionName);
  } else if (!isChecked && permissionsArray.includes(permissionName)) {
    const index = permissionsArray.indexOf(permissionName);
    if (index !== -1) {
      permissionsArray.splice(index, 1);
    }
  }
}

// Function to generate permission name based on category
function generatePermissionName(action: string) {
  const category = selectedCategory.value.toLowerCase();
  createPermissionForm.name = `${action}_${category}`;
}

// Function to submit create permission form
function submitCreatePermissionForm() {
  createPermissionForm.post(route('admin.permissions.store'), {
    onSuccess: (page: any) => {
      // Update the permissions data if available
      if (page.props.permissions) {
        props.permissions = page.props.permissions;
      }

      createPermissionModalOpen.value = false;
      createPermissionForm.reset();
      toast.success('Permission created successfully');

      // Reload the page to refresh the permissions list
      router.reload();
    },
  });
}

// Function to submit edit permission form
function submitEditPermissionForm() {
  if (!selectedPermission.value) return;

  editPermissionForm.put(route('admin.permissions.update', selectedPermission.value.id), {
    onSuccess: (page: any) => {
      // Update the permissions data if available
      if (page.props.permissions) {
        props.permissions = page.props.permissions;
      }

      editPermissionModalOpen.value = false;
      editPermissionForm.reset();
      selectedPermission.value = null;
      toast.success('Permission updated successfully');

      // Reload the page to refresh the permissions list
      router.reload();
    },
  });
}

// Function to delete a permission
function deletePermission() {
  if (!selectedPermission.value) return;

  router.delete(route('admin.permissions.destroy', selectedPermission.value.id), {
    onSuccess: (page: any) => {
      // Update the permissions data if available
      if (page.props.permissions) {
        props.permissions = page.props.permissions;
      }

      deletePermissionModalOpen.value = false;
      selectedPermission.value = null;
      toast.success('Permission deleted successfully');

      // Reload the page to refresh the permissions list
      router.reload();
    },
    onError: (errors: any) => {
      toast.error('Failed to delete permission', {
        description: errors.message || 'There was an error deleting the permission.'
      });
    }
  });
}

// Function to view permission details
function viewPermission(permission: Permission) {
  selectedPermission.value = permission;

  router.visit(route('admin.permissions.show', permission.id), {
    only: ['permission'],
    preserveState: true,
    preserveScroll: true,
    onSuccess: (page: any) => {
      if (page.props.permission) {
        selectedPermissionDetails.value = {
          permission: page.props.permission
        };
        viewPermissionModalOpen.value = true;
      } else {
        toast.error('Failed to load permission details: incomplete data');
      }
    },
    onError: () => {
      toast.error('Failed to load permission details');
    }
  });
}

// Function to open edit permission modal
function openEditPermissionModal(permission: Permission) {
  selectedPermission.value = permission;
  editPermissionForm.name = permission.name;
  editPermissionForm.roles = permission.roles || [];
  editPermissionModalOpen.value = true;
}

// Function to open delete permission modal
function openDeletePermissionModal(permission: Permission) {
  selectedPermission.value = permission;
  deletePermissionModalOpen.value = true;
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Admin Dashboard',
    href: route('admin.dashboard'),
  },
  {
    title: 'Roles & Permissions',
    href: route('admin.roles.index'),
  },
];
</script>

<template>
  <Head title="Roles & Permissions" />

  <AdminLayout :breadcrumbs="breadcrumbs">
    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
          <div class="p-6 text-gray-900 dark:text-gray-100">
            <p class="mb-4 text-muted-foreground">
              Manage roles and permissions for users in the system. Create custom roles with specific permissions or modify existing ones.
            </p>

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-4 mb-6">
              <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle class="text-sm font-medium">Total Roles</CardTitle>
                  <ShieldCheck class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                  <div class="text-2xl font-bold">{{ stats.roles_count }}</div>
                </CardContent>
              </Card>
              <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle class="text-sm font-medium">Permissions</CardTitle>
                  <ShieldCheck class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                  <div class="text-2xl font-bold">{{ stats.permissions_count }}</div>
                </CardContent>
              </Card>
              <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle class="text-sm font-medium">Users with Roles</CardTitle>
                  <Users class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                  <div class="text-2xl font-bold">{{ stats.users_with_roles }}</div>
                </CardContent>
              </Card>
              <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle class="text-sm font-medium">Users without Roles</CardTitle>
                  <Users class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                  <div class="text-2xl font-bold">{{ stats.users_without_roles }}</div>
                </CardContent>
              </Card>
            </div>

            <!-- Tabs for Roles and Permissions -->
            <Tabs default-value="roles" class="w-full">
              <TabsList class="grid w-full grid-cols-2">
                <TabsTrigger value="roles">Roles</TabsTrigger>
                <TabsTrigger value="permissions">Permissions</TabsTrigger>
              </TabsList>

              <TabsContent value="roles">
                <div class="flex items-center justify-between py-4">
                  <Input
                    class="max-w-sm"
                    placeholder="Filter by role name..."
                    :model-value="table.getColumn('name')?.getFilterValue()?.toString() || ''"
                    @update:model-value="table.getColumn('name')?.setFilterValue($event)"
                  >
                    <template #prefix>
                      <Search class="h-4 w-4 text-gray-400" />
                    </template>
                  </Input>
                  <Button @click="createRoleModalOpen = true">
                    <PlusCircle class="h-4 w-4 mr-2" />
                    New Role
                  </Button>
                </div>

                <div class="rounded-md border">
                  <Table>
                    <TableHeader>
                      <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
                        <TableHead v-for="header in headerGroup.headers" :key="header.id">
                          <FlexRender v-if="!header.isPlaceholder" :render="header.column.columnDef.header" :props="header.getContext()" />
                        </TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      <template v-if="table.getRowModel().rows?.length">
                        <TableRow
                          v-for="row in table.getRowModel().rows"
                          :key="row.id"
                          :data-state="row.getIsSelected() && 'selected'"
                        >
                          <TableCell v-for="cell in row.getVisibleCells()" :key="cell.id">
                            <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                          </TableCell>
                        </TableRow>
                      </template>
                      <TableRow v-else>
                        <TableCell :colspan="columns.length" class="h-24 text-center">
                          No roles found.
                        </TableCell>
                      </TableRow>
                    </TableBody>
                  </Table>
                </div>

                <div class="flex items-center justify-end space-x-2 py-4">
                  <div class="flex-1 text-sm text-muted-foreground">
                    {{ table.getFilteredSelectedRowModel().rows.length }} of
                    {{ table.getFilteredRowModel().rows.length }} row(s) selected.
                  </div>
                  <div class="space-x-2">
                    <Button
                      variant="outline"
                      size="sm"
                      :disabled="!table.getCanPreviousPage()"
                      @click="table.previousPage()"
                    >
                      Previous
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      :disabled="!table.getCanNextPage()"
                      @click="table.nextPage()"
                    >
                      Next
                    </Button>
                  </div>
                </div>
              </TabsContent>

              <TabsContent value="permissions">
                <div class="flex justify-between items-center mb-4">
                  <h3 class="text-lg font-semibold">System Permissions</h3>
                  <Button @click="createPermissionModalOpen = true">
                    <PlusCircle class="h-4 w-4 mr-2" />
                    New Permission
                  </Button>
                </div>

                <div v-for="(permissionList, category) in permissions" :key="category" class="mb-6">
                  <h4 class="text-md font-medium mb-2">{{ category }}</h4>
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                    <div v-for="permission in permissionList" :key="permission.id"
                      class="border rounded-md p-3 flex justify-between items-center">
                      <span>{{ permission.name }}</span>
                      <div class="flex space-x-1">
                        <Button variant="ghost" size="sm" @click="viewPermission(permission)">
                          <Eye class="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="sm" @click="openEditPermissionModal(permission)">
                          <Edit class="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="sm" class="text-red-600" @click="openDeletePermissionModal(permission)">
                          <Trash2 class="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
                  </div>
                </div>
              </TabsContent>
            </Tabs>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Role Modal -->
    <Dialog v-model:open="createRoleModalOpen">
      <DialogContent class="sm:max-w-[600px]">
        <DialogHeader>
          <DialogTitle>Create New Role</DialogTitle>
          <DialogDescription>
            Create a new role with specific permissions. Roles can be assigned to users to control their access to different parts of the system.
          </DialogDescription>
        </DialogHeader>
        <form @submit.prevent="submitCreateRoleForm">
          <div class="space-y-4 py-4">
            <div class="space-y-2">
              <Label for="name">Role Name</Label>
              <Input
                id="name"
                v-model="createRoleForm.name"
                placeholder="Enter role name"
              />
              <p v-if="createRoleForm.errors.name" class="text-sm text-red-500">{{ createRoleForm.errors.name }}</p>
              <p class="text-sm text-muted-foreground">
                The name should be descriptive of the role's purpose, e.g., "Content Editor" or "Support Agent".
              </p>
            </div>

            <div class="space-y-2 mt-6">
              <Label>Permissions</Label>
              <p v-if="createRoleForm.errors.permissions" class="text-sm text-red-500">{{ createRoleForm.errors.permissions }}</p>

              <div class="max-h-[300px] overflow-y-auto pr-2">
                <div v-for="(permissionList, category) in permissions" :key="category" class="mt-4">
                  <div class="flex items-center space-x-2 mb-2">
                    <input
                      type="checkbox"
                      :id="`category-create-${category}`"
                      :checked="isCategorySelectedCreateRole(category)"
                      @change="toggleCategoryCreateRole(category, $event.target.checked)"
                      class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                    />
                    <Label :for="`category-create-${category}`" class="font-medium">{{ category }}</Label>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-2 ml-6">
                    <div v-for="permission in permissionList" :key="permission.id" class="flex items-center space-x-2">
                      <input
                        type="checkbox"
                        :id="`permission-create-${permission.id}`"
                        :value="permission.name"
                        :checked="createRoleForm.permissions.includes(permission.name)"
                        @change="togglePermission(createRoleForm.permissions, permission.name, $event.target.checked)"
                        class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                      />
                      <Label :for="`permission-create-${permission.id}`" class="text-sm">{{ permission.name }}</Label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" type="button" @click="createRoleModalOpen = false">
              Cancel
            </Button>
            <Button type="submit" :disabled="createRoleForm.processing">
              {{ createRoleForm.processing ? 'Creating...' : 'Create Role' }}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- View Role Modal -->
    <Dialog v-model:open="viewRoleModalOpen">
      <DialogContent class="sm:max-w-[600px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Role Details: {{ selectedRole?.name }}</DialogTitle>
          <DialogDescription>
            View role information, assigned permissions, and users with this role.
          </DialogDescription>
        </DialogHeader>
        <div v-if="selectedRoleDetails" class="py-4">
          <div class="space-y-4">
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Name</h3>
              <p>{{ selectedRoleDetails.role.name }}</p>
            </div>
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Status</h3>
              <Badge :variant="selectedRoleDetails.role.is_protected ? 'default' : 'outline'" :class="selectedRoleDetails.role.is_protected ? 'bg-amber-500' : ''">
                {{ selectedRoleDetails.role.is_protected ? 'Protected' : 'Custom' }}
              </Badge>
            </div>
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Users with this role</h3>
              <p>{{ selectedRoleDetails.role.users_count }}</p>
            </div>

            <div>
              <h3 class="text-sm font-medium text-muted-foreground mb-2">Permissions</h3>
              <div class="max-h-[200px] overflow-y-auto border rounded-md p-2">
                <div v-for="(permissionList, category) in selectedRoleDetails.allPermissions" :key="category" class="mb-4">
                  <h4 class="font-medium mb-1">{{ category }}</h4>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-1">
                    <div v-for="permission in permissionList" :key="permission.id"
                      class="text-sm p-1 rounded"
                      :class="{ 'bg-primary/10': permission.assigned }">
                      {{ permission.name }}
                      <Badge v-if="permission.assigned" variant="outline" class="ml-1 text-xs border-primary text-primary">
                        Assigned
                      </Badge>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="selectedRoleDetails.users.length > 0">
              <h3 class="text-sm font-medium text-muted-foreground mb-2">Users with this role</h3>
              <div class="max-h-[200px] overflow-y-auto border rounded-md p-2">
                <div v-for="user in selectedRoleDetails.users" :key="user.id" class="flex justify-between items-center p-2 border-b last:border-0">
                  <div>
                    <p class="font-medium">{{ user.name }}</p>
                    <p class="text-sm text-muted-foreground">{{ user.email }}</p>
                  </div>
                  <Button variant="ghost" size="sm" @click="router.visit(route('admin.customers.show', user.id))">
                    View
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="viewRoleModalOpen = false">
            Close
          </Button>
          <Button
            v-if="selectedRole && !selectedRole.is_protected"
            variant="default"
            @click="() => { viewRoleModalOpen = false; openEditRoleModal(selectedRole); }"
          >
            <Edit class="h-4 w-4 mr-2" />
            Edit Role
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Edit Role Modal -->
    <Dialog v-model:open="editRoleModalOpen">
      <DialogContent class="sm:max-w-[600px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Edit Role: {{ selectedRole?.name }}</DialogTitle>
          <DialogDescription>
            Update the role name and permissions. Changes will affect all users with this role.
          </DialogDescription>
        </DialogHeader>
        <form @submit.prevent="submitEditRoleForm">
          <div class="space-y-4 py-4">
            <div class="space-y-2">
              <Label for="edit-name">Role Name</Label>
              <Input
                id="edit-name"
                v-model="editRoleForm.name"
                placeholder="Enter role name"
              />
              <p v-if="editRoleForm.errors.name" class="text-sm text-red-500">{{ editRoleForm.errors.name }}</p>
            </div>

            <div class="space-y-2 mt-6">
              <Label>Permissions</Label>
              <p v-if="editRoleForm.errors.permissions" class="text-sm text-red-500">{{ editRoleForm.errors.permissions }}</p>

              <div class="max-h-[300px] overflow-y-auto pr-2">
                <div v-for="(permissionList, category) in permissions" :key="category" class="mt-4">
                  <div class="flex items-center space-x-2 mb-2">
                    <input
                      type="checkbox"
                      :id="`category-edit-${category}`"
                      :checked="isCategorySelectedEditRole(category)"
                      @change="toggleCategoryEditRole(category, $event.target.checked)"
                      class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                    />
                    <Label :for="`category-edit-${category}`" class="font-medium">{{ category }}</Label>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-2 ml-6">
                    <div v-for="permission in permissionList" :key="permission.id" class="flex items-center space-x-2">
                      <input
                        type="checkbox"
                        :id="`permission-edit-${permission.id}`"
                        :value="permission.name"
                        :checked="editRoleForm.permissions.includes(permission.name)"
                        @change="togglePermission(editRoleForm.permissions, permission.name, $event.target.checked)"
                        class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                      />
                      <Label :for="`permission-edit-${permission.id}`" class="text-sm">{{ permission.name }}</Label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" type="button" @click="editRoleModalOpen = false">
              Cancel
            </Button>
            <Button type="submit" :disabled="editRoleForm.processing">
              {{ editRoleForm.processing ? 'Saving...' : 'Save Changes' }}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- Delete Role Modal -->
    <Dialog v-model:open="deleteRoleModalOpen">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Delete Role</DialogTitle>
          <DialogDescription>
            Are you sure you want to delete the role "{{ selectedRole?.name }}"? This action cannot be undone.
          </DialogDescription>
        </DialogHeader>
        <div class="py-4">
          <p class="text-sm text-muted-foreground">
            Deleting this role will remove it from the system. If there are users assigned to this role, you will not be able to delete it.
          </p>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="deleteRoleModalOpen = false">
            Cancel
          </Button>
          <Button variant="destructive" @click="deleteRole">
            Delete Role
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Create Permission Modal -->
    <Dialog v-model:open="createPermissionModalOpen">
      <DialogContent class="sm:max-w-[600px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Create New Permission</DialogTitle>
          <DialogDescription>
            Create a new permission that can be assigned to roles. Permissions control what actions users can perform.
          </DialogDescription>
        </DialogHeader>
        <form @submit.prevent="submitCreatePermissionForm">
          <div class="space-y-4 py-4">
            <div class="space-y-2">
              <Label for="category">Permission Category</Label>
              <Select v-model="selectedCategory">
                <SelectTrigger class="w-full">
                  <SelectValue placeholder="Select a category" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="category in Object.keys(permissions)" :key="category" :value="category">
                    {{ category }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p class="text-sm text-muted-foreground">
                Select a category to help organize permissions and generate a consistent naming pattern.
              </p>
            </div>

            <div class="space-y-2">
              <Label>Quick Actions</Label>
              <div class="flex flex-wrap gap-2">
                <Button type="button" variant="outline" size="sm" @click="generatePermissionName('view')">
                  view_{{ selectedCategory.toLowerCase() }}
                </Button>
                <Button type="button" variant="outline" size="sm" @click="generatePermissionName('create')">
                  create_{{ selectedCategory.toLowerCase() }}
                </Button>
                <Button type="button" variant="outline" size="sm" @click="generatePermissionName('edit')">
                  edit_{{ selectedCategory.toLowerCase() }}
                </Button>
                <Button type="button" variant="outline" size="sm" @click="generatePermissionName('delete')">
                  delete_{{ selectedCategory.toLowerCase() }}
                </Button>
                <Button type="button" variant="outline" size="sm" @click="generatePermissionName('manage')">
                  manage_{{ selectedCategory.toLowerCase() }}
                </Button>
              </div>
            </div>

            <div class="space-y-2">
              <Label for="name">Permission Name</Label>
              <Input
                id="name"
                v-model="createPermissionForm.name"
                placeholder="Enter permission name"
              />
              <p v-if="createPermissionForm.errors.name" class="text-sm text-red-500">{{ createPermissionForm.errors.name }}</p>
              <p class="text-sm text-muted-foreground">
                Use a consistent naming pattern like 'action_resource' (e.g., 'edit_users', 'view_dashboard').
              </p>
            </div>

            <div class="space-y-2 mt-6">
              <Label>Assign to Roles</Label>
              <p v-if="createPermissionForm.errors.roles" class="text-sm text-red-500">{{ createPermissionForm.errors.roles }}</p>

              <div class="max-h-[200px] overflow-y-auto pr-2">
                <div v-for="role in allRoles" :key="role.id" class="flex items-center space-x-2 py-1">
                  <input
                    type="checkbox"
                    :id="`role-create-${role.id}`"
                    :value="role.name"
                    :checked="createPermissionForm.roles.includes(role.name)"
                    @change="togglePermission(createPermissionForm.roles, role.name, $event.target.checked)"
                    class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                  />
                  <Label :for="`role-create-${role.id}`" class="flex items-center">
                    {{ role.name }}
                    <Badge v-if="role.is_protected" variant="outline" class="ml-2 text-xs bg-amber-500 text-white">
                      Protected
                    </Badge>
                  </Label>
                </div>
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" type="button" @click="createPermissionModalOpen = false">
              Cancel
            </Button>
            <Button type="submit" :disabled="createPermissionForm.processing">
              {{ createPermissionForm.processing ? 'Creating...' : 'Create Permission' }}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- View Permission Modal -->
    <Dialog v-model:open="viewPermissionModalOpen">
      <DialogContent class="sm:max-w-[600px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Permission Details: {{ selectedPermission?.name }}</DialogTitle>
          <DialogDescription>
            View permission information and assigned roles.
          </DialogDescription>
        </DialogHeader>
        <div v-if="selectedPermissionDetails" class="py-4">
          <div class="space-y-4">
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Name</h3>
              <p>{{ selectedPermissionDetails.permission.name }}</p>
            </div>
            <div>
              <h3 class="text-sm font-medium text-muted-foreground">Category</h3>
              <p>{{ selectedPermissionDetails.permission.category }}</p>
            </div>

            <div>
              <h3 class="text-sm font-medium text-muted-foreground mb-2">Assigned Roles</h3>
              <div v-if="selectedPermissionDetails.permission.roles.length > 0" class="border rounded-md p-2">
                <div v-for="role in selectedPermissionDetails.permission.roles" :key="role.id"
                  class="flex justify-between items-center p-2 border-b last:border-0">
                  <div class="flex items-center">
                    <span>{{ role.name }}</span>
                    <Badge v-if="role.is_protected" variant="outline" class="ml-2 text-xs bg-amber-500 text-white">
                      Protected
                    </Badge>
                  </div>
                  <Button variant="ghost" size="sm" @click="() => { viewPermissionModalOpen = false; viewRole(roles.find(r => r.id === role.id)); }">
                    View Role
                  </Button>
                </div>
              </div>
              <div v-else class="text-muted-foreground text-sm">
                This permission is not assigned to any roles.
              </div>
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="viewPermissionModalOpen = false">
            Close
          </Button>
          <Button
            variant="default"
            @click="() => { viewPermissionModalOpen = false; openEditPermissionModal(selectedPermission); }"
          >
            <Edit class="h-4 w-4 mr-2" />
            Edit Permission
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Edit Permission Modal -->
    <Dialog v-model:open="editPermissionModalOpen">
      <DialogContent class="sm:max-w-[600px] max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Edit Permission: {{ selectedPermission?.name }}</DialogTitle>
          <DialogDescription>
            Update the permission name and assigned roles.
          </DialogDescription>
        </DialogHeader>
        <form @submit.prevent="submitEditPermissionForm">
          <div class="space-y-4 py-4">
            <div class="space-y-2">
              <Label for="edit-name">Permission Name</Label>
              <Input
                id="edit-name"
                v-model="editPermissionForm.name"
                placeholder="Enter permission name"
              />
              <p v-if="editPermissionForm.errors.name" class="text-sm text-red-500">{{ editPermissionForm.errors.name }}</p>
              <p class="text-sm text-muted-foreground">
                Use a consistent naming pattern like 'action_resource' (e.g., 'edit_users', 'view_dashboard').
              </p>
            </div>

            <div class="space-y-2 mt-6">
              <Label>Assign to Roles</Label>
              <p v-if="editPermissionForm.errors.roles" class="text-sm text-red-500">{{ editPermissionForm.errors.roles }}</p>

              <div class="max-h-[200px] overflow-y-auto pr-2">
                <div v-for="role in allRoles" :key="role.id" class="flex items-center space-x-2 py-1">
                  <input
                    type="checkbox"
                    :id="`role-edit-${role.id}`"
                    :value="role.name"
                    :checked="editPermissionForm.roles.includes(role.name)"
                    @change="togglePermission(editPermissionForm.roles, role.name, $event.target.checked)"
                    class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                  />
                  <Label :for="`role-edit-${role.id}`" class="flex items-center">
                    {{ role.name }}
                    <Badge v-if="role.is_protected" variant="outline" class="ml-2 text-xs bg-amber-500 text-white">
                      Protected
                    </Badge>
                  </Label>
                </div>
              </div>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" type="button" @click="editPermissionModalOpen = false">
              Cancel
            </Button>
            <Button type="submit" :disabled="editPermissionForm.processing">
              {{ editPermissionForm.processing ? 'Saving...' : 'Save Changes' }}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- Delete Permission Modal -->
    <Dialog v-model:open="deletePermissionModalOpen">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Delete Permission</DialogTitle>
          <DialogDescription>
            Are you sure you want to delete the permission "{{ selectedPermission?.name }}"? This action cannot be undone.
          </DialogDescription>
        </DialogHeader>
        <div class="py-4">
          <p class="text-sm text-muted-foreground">
            Deleting this permission will remove it from all roles that currently have it assigned.
          </p>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="deletePermissionModalOpen = false">
            Cancel
          </Button>
          <Button variant="destructive" @click="deletePermission">
            Delete Permission
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AdminLayout>
</template>
