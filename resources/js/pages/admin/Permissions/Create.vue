<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ArrowLeft, ShieldCheck } from 'lucide-vue-next';
import { ref } from 'vue';

// Define the Role type
interface Role {
  id: number;
  name: string;
  is_protected: boolean;
}

// Get props
const props = defineProps<{
  roles: Role[];
  categories: string[];
}>();

// Form state
const form = useForm({
  name: '',
  roles: [] as string[],
});

// Selected category for naming convention
const selectedCategory = ref(props.categories[0]);

// Function to generate permission name based on category
function generatePermissionName(action: string) {
  const category = selectedCategory.value.toLowerCase();
  form.name = `${action}_${category}`;
}

// Function to submit the form
function submit() {
  form.post(route('admin.permissions.store'), {
    onSuccess: () => {
      // Reset form after successful submission
      form.reset();
      selectedCategory.value = props.categories[0];
    },
  });
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
  {
    title: 'Create Permission',
    href: route('admin.permissions.create'),
  },
];
</script>

<template>
  <Head title="Create Permission" />

  <AdminLayout :breadcrumbs="breadcrumbs">
    <template #header>
      <div class="flex flex-col space-y-2 w-full md:flex-row md:items-center md:justify-between md:space-y-0">
        <div class="flex items-center">
          <Button variant="ghost" class="mr-4 hover:bg-primary/10" @click="router.visit(route('admin.permissions.index'))">
            <ArrowLeft class="h-4 w-4 mr-2" />
            Back to Permissions
          </Button>
          <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            Create New Permission
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
                    Permission Information
                  </CardTitle>
                  <CardDescription>
                    Create a new permission that can be assigned to roles. Permissions control what actions users can perform.
                  </CardDescription>
                </CardHeader>
                <CardContent>
                  <div class="space-y-4">
                    <div class="space-y-2">
                      <Label for="category">Permission Category</Label>
                      <Select v-model="selectedCategory">
                        <SelectTrigger class="w-full">
                          <SelectValue placeholder="Select a category" />
                        </SelectTrigger>
                        <SelectContent>
                          <SelectItem v-for="category in categories" :key="category" :value="category">
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
                        v-model="form.name" 
                        placeholder="Enter permission name" 
                        :error="form.errors.name"
                      />
                      <p v-if="form.errors.name" class="text-sm text-red-500">{{ form.errors.name }}</p>
                      <p class="text-sm text-muted-foreground">
                        Use a consistent naming pattern like 'action_resource' (e.g., 'edit_users', 'view_dashboard').
                      </p>
                    </div>

                    <div class="space-y-2 mt-6">
                      <Label>Assign to Roles</Label>
                      <p v-if="form.errors.roles" class="text-sm text-red-500">{{ form.errors.roles }}</p>
                      
                      <div class="space-y-2">
                        <div v-for="role in roles" :key="role.id" class="flex items-center space-x-2">
                          <Checkbox 
                            :id="`role-${role.id}`" 
                            v-model:checked="form.roles" 
                            :value="role.name"
                          />
                          <Label :for="`role-${role.id}`" class="flex items-center">
                            {{ role.name }}
                            <Badge v-if="role.is_protected" variant="outline" class="ml-2 text-xs bg-amber-500 text-white">
                              Protected
                            </Badge>
                          </Label>
                        </div>
                      </div>
                    </div>
                  </div>
                </CardContent>
                <CardFooter class="flex justify-between">
                  <Button variant="outline" type="button" @click="router.visit(route('admin.permissions.index'))">
                    Cancel
                  </Button>
                  <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Creating...' : 'Create Permission' }}
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
