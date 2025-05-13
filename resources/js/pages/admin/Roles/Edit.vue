<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { ArrowLeft, ShieldCheck } from 'lucide-vue-next';
import { ref, computed } from 'vue';

// Define the Role type
interface Role {
  id: number;
  name: string;
  permissions: string[];
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
  permissions: Record<string, Permission[]>;
}>();

// Form state
const form = useForm({
  name: props.role.name,
  permissions: [...props.role.permissions],
});

// Function to toggle all permissions in a category
function toggleCategory(category: string, checked: boolean) {
  const categoryPermissions = props.permissions[category].map(p => p.name);
  
  if (checked) {
    // Add all permissions from this category that aren't already selected
    categoryPermissions.forEach(permission => {
      if (!form.permissions.includes(permission)) {
        form.permissions.push(permission);
      }
    });
  } else {
    // Remove all permissions from this category
    form.permissions = form.permissions.filter(
      permission => !categoryPermissions.includes(permission)
    );
  }
}

// Computed property to check if all permissions in a category are selected
function isCategorySelected(category: string) {
  const categoryPermissions = props.permissions[category].map(p => p.name);
  return categoryPermissions.every(permission => form.permissions.includes(permission));
}

// Function to submit the form
function submit() {
  form.put(route('admin.roles.update', props.role.id), {
    onSuccess: () => {
      // Redirect to role details page after successful update
      router.visit(route('admin.roles.show', props.role.id));
    },
  });
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
  {
    title: 'Edit',
    href: route('admin.roles.edit', props.role.id),
  },
];
</script>

<template>
  <Head title="Edit Role" />

  <AdminLayout :breadcrumbs="breadcrumbs">
    <template #header>
      <div class="flex flex-col space-y-2 w-full md:flex-row md:items-center md:justify-between md:space-y-0">
        <div class="flex items-center">
          <Button variant="ghost" class="mr-4 hover:bg-primary/10" @click="router.visit(route('admin.roles.show', role.id))">
            <ArrowLeft class="h-4 w-4 mr-2" />
            Back to Role Details
          </Button>
          <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            Edit Role: {{ role.name }}
          </h2>
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
          <div class="p-6 text-gray-900 dark:text-gray-100">
            <form @submit.prevent="submit">
              <Card>
                <CardHeader>
                  <CardTitle class="flex items-center">
                    <ShieldCheck class="h-5 w-5 mr-2" />
                    Edit Role
                  </CardTitle>
                  <CardDescription>
                    Update the role name and permissions. Changes will affect all users with this role.
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <div class="space-y-4">
                    <div class="space-y-2">
                      <Label for="name">Role Name</Label>
                      <Input 
                        id="name" 
                        v-model="form.name" 
                        placeholder="Enter role name" 
                        :error="form.errors.name"
                      />
                      <p v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</p>
                      <p class="text-sm text-muted-foreground">
                        The name should be descriptive of the role's purpose, e.g., "Content Editor" or "Support Agent".
                      </p>
                    </div>

                    <div class="space-y-2 mt-6">
                      <Label>Permissions</Label>
                      <p v-if="form.errors.permissions" class="text-sm text-red-500">{{ form.errors.permissions }}</p>
                      
                      <div v-for="(permissionList, category) in permissions" :key="category" class="mt-4">
                        <div class="flex items-center space-x-2 mb-2">
                          <Checkbox 
                            :id="`category-${category}`" 
                            :checked="isCategorySelected(category)"
                            @update:checked="toggleCategory(category, $event)"
                          />
                          <Label :for="`category-${category}`" class="font-medium">{{ category }}</Label>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2 ml-6">
                          <div v-for="permission in permissionList" :key="permission.id" class="flex items-center space-x-2">
                            <Checkbox 
                              :id="`permission-${permission.id}`" 
                              v-model:checked="form.permissions" 
                              :value="permission.name"
                            />
                            <Label :for="`permission-${permission.id}`" class="text-sm">{{ permission.name }}</Label>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </CardContent>
                <CardFooter class="flex justify-between">
                  <Button variant="outline" type="button" @click="router.visit(route('admin.roles.show', role.id))">
                    Cancel
                  </Button>
                  <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                  </Button>
                </CardFooter>
              </Card>
            </form>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
