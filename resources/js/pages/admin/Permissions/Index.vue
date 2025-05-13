<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { ShieldCheck, PlusCircle, Eye, Edit, Trash2, Search } from 'lucide-vue-next';
import { ref } from 'vue';
import { toast } from 'vue-sonner';

// Define the Permission type
interface Permission {
  id: number;
  name: string;
  roles: string[];
  category: string;
}

// Get props
const props = defineProps<{
  permissions: Record<string, Permission[]>;
}>();

// Search state
const searchQuery = ref('');

// Computed property for filtered permissions
const filteredPermissions = computed(() => {
  if (!searchQuery.value) {
    return props.permissions;
  }

  const query = searchQuery.value.toLowerCase();
  const result: Record<string, Permission[]> = {};

  Object.entries(props.permissions).forEach(([category, perms]) => {
    const filtered = perms.filter(p => 
      p.name.toLowerCase().includes(query) || 
      p.roles.some(r => r.toLowerCase().includes(query))
    );
    
    if (filtered.length > 0) {
      result[category] = filtered;
    }
  });

  return result;
});

// Function to delete a permission
function deletePermission(id: number) {
  if (confirm('Are you sure you want to delete this permission? This action cannot be undone.')) {
    router.delete(route('admin.permissions.destroy', id), {
      onSuccess: () => {
        toast.success('Permission deleted successfully');
      },
      onError: (errors) => {
        toast.error('Failed to delete permission', {
          description: errors.message || 'There was an error deleting the permission.'
        });
      }
    });
  }
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Admin Dashboard',
    href: route('admin.dashboard'),
  },
  {
    title: 'Permissions',
    href: route('admin.permissions.index'),
  },
];
</script>

<template>
  <Head title="Permissions" />

  <AdminLayout :breadcrumbs="breadcrumbs">
    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
          <div class="p-6 text-gray-900 dark:text-gray-100">
            <div class="flex justify-between items-center mb-6">
              <div>
                <h2 class="text-xl font-semibold">System Permissions</h2>
                <p class="text-muted-foreground">
                  Manage permissions that can be assigned to roles. Permissions control what actions users can perform.
                </p>
              </div>
              <Button @click="router.visit(route('admin.permissions.create'))">
                <PlusCircle class="h-4 w-4 mr-2" />
                New Permission
              </Button>
            </div>

            <div class="mb-6">
              <Input
                v-model="searchQuery"
                placeholder="Search permissions..."
                class="max-w-md"
              >
                <template #prefix>
                  <Search class="h-4 w-4 text-gray-400" />
                </template>
              </Input>
            </div>

            <div v-if="Object.keys(filteredPermissions).length === 0" class="text-center py-8">
              <p class="text-muted-foreground">No permissions found matching your search.</p>
            </div>

            <div v-for="(permissionList, category) in filteredPermissions" :key="category" class="mb-8">
              <Card>
                <CardHeader>
                  <CardTitle class="flex items-center">
                    <ShieldCheck class="h-5 w-5 mr-2" />
                    {{ category }}
                  </CardTitle>
                  <CardDescription>
                    Permissions related to {{ category.toLowerCase() }} functionality
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <div class="space-y-4">
                    <div v-for="permission in permissionList" :key="permission.id" 
                      class="border rounded-md p-4 flex flex-col md:flex-row md:items-center md:justify-between">
                      <div class="space-y-1 mb-4 md:mb-0">
                        <h3 class="font-medium">{{ permission.name }}</h3>
                        <div class="flex flex-wrap gap-1">
                          <Badge v-for="role in permission.roles" :key="role" variant="outline" class="text-xs">
                            {{ role }}
                          </Badge>
                          <span v-if="permission.roles.length === 0" class="text-xs text-muted-foreground">
                            Not assigned to any roles
                          </span>
                        </div>
                      </div>
                      <div class="flex space-x-2">
                        <Button variant="ghost" size="sm" @click="router.visit(route('admin.permissions.show', permission.id))">
                          <Eye class="h-4 w-4 mr-2" />
                          View
                        </Button>
                        <Button variant="ghost" size="sm" @click="router.visit(route('admin.permissions.edit', permission.id))">
                          <Edit class="h-4 w-4 mr-2" />
                          Edit
                        </Button>
                        <Button variant="ghost" size="sm" class="text-red-600" @click="deletePermission(permission.id)">
                          <Trash2 class="h-4 w-4 mr-2" />
                          Delete
                        </Button>
                      </div>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
