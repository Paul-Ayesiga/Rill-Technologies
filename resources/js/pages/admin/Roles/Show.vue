<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow
} from '@/components/ui/table';
import {
  ArrowLeft, ShieldCheck, Users, Edit, Trash2
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';

// Define the Role type
interface Role {
  id: number;
  name: string;
  permissions: string[];
  users_count: number;
  is_protected: boolean;
}

// Define the User type
interface User {
  id: number;
  name: string;
  email: string;
  created_at: string;
}

// Define the Permission type
interface Permission {
  id: number;
  name: string;
  category: string;
  assigned: boolean;
}

// Get props
const props = defineProps<{
  role: Role;
  users: User[];
  allPermissions: Record<string, Permission[]>;
}>();

// Function to delete a role
function deleteRole() {
  if (confirm('Are you sure you want to delete this role? This action cannot be undone.')) {
    router.delete(route('admin.roles.destroy', props.role.id), {
      onSuccess: () => {
        toast.success('Role deleted successfully');
        router.visit(route('admin.roles.index'));
      },
      onError: (errors) => {
        toast.error('Failed to delete role', {
          description: errors.message || 'There was an error deleting the role.'
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
    title: 'Roles & Permissions',
    href: route('admin.roles.index'),
  },
  {
    title: props.role.name,
    href: route('admin.roles.show', props.role.id),
  },
];
</script>

<template>
  <Head :title="`Role: ${role.name}`" />

  <AdminLayout :breadcrumbs="breadcrumbs">
    <template #header>
      <div class="flex flex-col space-y-2 w-full md:flex-row md:items-center md:justify-between md:space-y-0">
        <div class="flex items-center">
          <Button variant="ghost" class="mr-4 hover:bg-primary/10" @click="router.visit(route('admin.roles.index'))">
            <ArrowLeft class="h-4 w-4 mr-2" />
            Back to Roles
          </Button>
          <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            Role: {{ role.name }}
          </h2>
        </div>
        <div class="flex space-x-2">
          <Button v-if="!role.is_protected" variant="outline" @click="router.visit(route('admin.roles.edit', role.id))">
            <Edit class="h-4 w-4 mr-2" />
            Edit Role
          </Button>
          <Button v-if="!role.is_protected" variant="destructive" @click="deleteRole">
            <Trash2 class="h-4 w-4 mr-2" />
            Delete Role
          </Button>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
          <div class="p-6 text-gray-900 dark:text-gray-100">
            <div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2">
              <!-- Role Info Card -->
              <Card>
                <CardHeader>
                  <CardTitle class="flex items-center">
                    <ShieldCheck class="h-5 w-5 mr-2" />
                    Role Information
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div class="space-y-4">
                    <div>
                      <h3 class="text-sm font-medium text-muted-foreground">Name</h3>
                      <p>{{ role.name }}</p>
                    </div>
                    <div>
                      <h3 class="text-sm font-medium text-muted-foreground">Status</h3>
                      <Badge :variant="role.is_protected ? 'default' : 'outline'" :class="role.is_protected ? 'bg-amber-500' : ''">
                        {{ role.is_protected ? 'Protected' : 'Custom' }}
                      </Badge>
                    </div>
                    <div>
                      <h3 class="text-sm font-medium text-muted-foreground">Users with this role</h3>
                      <p>{{ role.users_count }}</p>
                    </div>
                    <div>
                      <h3 class="text-sm font-medium text-muted-foreground">Permissions count</h3>
                      <p>{{ role.permissions.length }}</p>
                    </div>
                  </div>
                </CardContent>
              </Card>

              <!-- Users with this role -->
              <Card>
                <CardHeader>
                  <CardTitle class="flex items-center">
                    <Users class="h-5 w-5 mr-2" />
                    Users with this Role
                  </CardTitle>
                  <CardDescription v-if="users.length === 10">
                    Showing 10 most recent users with this role
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <div v-if="users.length > 0" class="space-y-2">
                    <div v-for="user in users" :key="user.id" class="flex justify-between items-center p-2 border-b">
                      <div>
                        <p class="font-medium">{{ user.name }}</p>
                        <p class="text-sm text-muted-foreground">{{ user.email }}</p>
                      </div>
                      <Button variant="ghost" size="sm" @click="router.visit(route('admin.customers.show', user.id))">
                        View
                      </Button>
                    </div>
                  </div>
                  <div v-else class="py-8 text-center">
                    <p class="text-muted-foreground">No users have this role</p>
                  </div>
                </CardContent>
                <CardFooter v-if="role.users_count > 10">
                  <Button variant="outline" class="w-full" @click="router.visit(route('admin.customers.index'))">
                    View All Users
                  </Button>
                </CardFooter>
              </Card>
            </div>

            <!-- Permissions -->
            <Card>
              <CardHeader>
                <CardTitle>Permissions</CardTitle>
                <CardDescription>
                  Permissions assigned to this role determine what actions users with this role can perform.
                </CardDescription>
              </CardHeader>
              <CardContent>
                <div v-for="(permissionList, category) in allPermissions" :key="category" class="mb-6">
                  <h4 class="text-md font-medium mb-2">{{ category }}</h4>
                  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                    <div v-for="permission in permissionList" :key="permission.id" 
                      class="border rounded-md p-3 flex justify-between items-center"
                      :class="{ 'bg-primary/10 border-primary': permission.assigned }">
                      <span>{{ permission.name }}</span>
                      <Badge v-if="permission.assigned" variant="outline" class="border-primary text-primary">
                        Assigned
                      </Badge>
                    </div>
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
