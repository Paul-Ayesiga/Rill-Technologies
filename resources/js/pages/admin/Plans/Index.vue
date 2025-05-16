<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
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
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader,
  DialogTitle, DialogTrigger
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
  Package, MoreHorizontal, Edit, Trash2, Plus, Check, X, ArrowUpDown, DollarSign
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import { useForm } from '@inertiajs/vue3';

// Define the Plan type
interface Feature {
  name: string;
  included: boolean;
}

interface Plan {
  id: string;
  product_id: string;
  name: string;
  description: string;
  price: number;
  interval: string;
  currency: string;
  features: Feature[];
  trial_days: number | null;
  created: string;
  active?: boolean;
  archived?: boolean;
}

// Get props
const props = defineProps<{
  plans: Plan[];
}>();

// Dialog states
const createDialogOpen = ref(false);
const editDialogOpen = ref(false);
const deleteDialogOpen = ref(false);

// Selected plan for editing or deleting
const selectedPlan = ref<Plan | null>(null);

// Create plan form
const createForm = useForm({
  name: '',
  description: '',
  price: 0,
  interval: 'month',
  trial_days: 0,
  features: [{ name: '', included: true }] as Feature[]
});

// Edit plan form
const editForm = useForm({
  name: '',
  description: '',
  trial_days: 0,
  features: [] as Feature[]
});

// Function to add a new feature to the create form
function addFeature() {
  createForm.features.push({ name: '', included: true });
}

// Function to remove a feature from the create form
function removeFeature(index: number) {
  createForm.features.splice(index, 1);
}

// Function to add a new feature to the edit form
function addEditFeature() {
  editForm.features.push({ name: '', included: true });
}

// Function to remove a feature from the edit form
function removeEditFeature(index: number) {
  editForm.features.splice(index, 1);
}

// Function to create a new plan
function createPlan() {
  createForm.post(route('admin.plans.store'), {
    onSuccess: () => {
      createDialogOpen.value = false;
      createForm.reset();
      toast.success('Plan created successfully');
    },
    onError: (errors: any) => {
      toast.error('Failed to create plan', {
        description: Object.values(errors).flat().join(', ')
      });
    }
  });
}

// Function to edit a plan
function editPlan() {
  if (!selectedPlan.value) return;

  editForm.put(route('admin.plans.update', selectedPlan.value.id), {
    onSuccess: () => {
      editDialogOpen.value = false;
      selectedPlan.value = null;
      toast.success('Plan updated successfully');
    },
    onError: (errors: any) => {
      toast.error('Failed to update plan', {
        description: Object.values(errors).flat().join(', ')
      });
    }
  });
}

// Function to delete a plan
function deletePlan() {
  if (!selectedPlan.value) return;

  console.log('Archiving plan with ID:', selectedPlan.value.id);

  router.delete(route('admin.plans.destroy', selectedPlan.value.id), {
    onSuccess: () => {
      deleteDialogOpen.value = false;
      selectedPlan.value = null;
      toast.success('Plan archived successfully');
    },
    onError: (errors: any) => {
      console.error('Archive plan error:', errors);
      toast.error('Failed to archive plan: ' + (errors.error || 'Unknown error'));
    }
  });
}

// Function to open the edit dialog
function openEditDialog(plan: Plan) {
  selectedPlan.value = plan;
  editForm.name = plan.name;
  editForm.description = plan.description || '';
  editForm.trial_days = plan.trial_days || 0;
  editForm.features = [...plan.features];
  editDialogOpen.value = true;
}

// Function to open the delete dialog
function openDeleteDialog(plan: Plan) {
  selectedPlan.value = plan;
  deleteDialogOpen.value = true;
}

// Function to unarchive a plan
function unarchivePlan(plan: Plan) {
  console.log('Unarchiving plan with ID:', plan.id);

  router.post(route('admin.plans.unarchive', plan.id), {}, {
    onSuccess: () => {
      toast.success('Plan unarchived successfully');
    },
    onError: (errors: any) => {
      console.error('Unarchive plan error:', errors);
      toast.error('Failed to unarchive plan: ' + (errors.error || 'Unknown error'));
    }
  });
}

// Function to format currency
function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(amount);
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Admin Dashboard',
    href: route('admin.dashboard'),
  },
  {
    title: 'Plans',
    href: route('admin.plans.index'),
  },
];
</script>

<template>
  <Head title="Plans Management" />

  <AdminLayout :breadcrumbs="breadcrumbs">
    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
          <div class="p-6 text-gray-900 dark:text-gray-100">
            <div class="flex items-center justify-between mb-6">
              <div>
                <h2 class="text-2xl font-bold">Subscription Plans</h2>
                <p class="text-muted-foreground">
                  Manage your subscription plans and their features
                </p>
              </div>
              <Button @click="createDialogOpen = true">
                <Plus class="h-4 w-4 mr-2" />
                Create Plan
              </Button>
            </div>

            <!-- Active Plans Section -->
            <div class="mb-8">
              <h3 class="text-xl font-semibold mb-4">Active Plans</h3>
              <div v-if="plans.filter(p => !p.archived).length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <Card v-for="plan in plans.filter(p => !p.archived)" :key="plan.id" class="flex flex-col">
                  <CardHeader>
                    <div class="flex items-center justify-between">
                      <CardTitle>{{ plan.name }}</CardTitle>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon">
                            <MoreHorizontal class="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem @click="openEditDialog(plan)">
                            <Edit class="h-4 w-4 mr-2" />
                            Edit Plan
                          </DropdownMenuItem>
                          <DropdownMenuSeparator />
                          <DropdownMenuItem @click="openDeleteDialog(plan)" class="text-red-500">
                            <Trash2 class="h-4 w-4 mr-2" />
                            Archive Plan
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                    <CardDescription>{{ plan.description }}</CardDescription>
                  </CardHeader>
                  <CardContent class="flex-grow">
                    <div class="mb-4">
                      <div class="flex items-end">
                        <span class="text-2xl font-bold">{{ formatCurrency(plan.price) }}</span>
                        <span class="text-muted-foreground ml-1">/ {{ plan.interval }}</span>
                      </div>
                      <div v-if="plan.trial_days" class="mt-1 text-sm text-green-600">
                        {{ plan.trial_days }} day{{ plan.trial_days !== 1 ? 's' : '' }} free trial
                      </div>
                    </div>
                    <div class="space-y-2">
                      <h3 class="text-sm font-medium">Features:</h3>
                      <ul class="space-y-2">
                        <li v-for="(feature, index) in plan.features" :key="index" class="flex items-start">
                          <div v-if="feature.included" class="rounded-full p-0.5 mr-2 mt-0.5 text-green-500 flex-shrink-0">
                            <Check class="h-4 w-4" />
                          </div>
                          <div v-else class="rounded-full p-0.5 mr-2 mt-0.5 text-gray-400 flex-shrink-0">
                            <X class="h-4 w-4" />
                          </div>
                          <span :class="{ 'text-muted-foreground': !feature.included }">
                            {{ feature.name }}
                          </span>
                        </li>
                      </ul>
                    </div>
                  </CardContent>
                  <CardFooter>
                    <div class="text-sm text-muted-foreground">
                      Created: {{ plan.created }}
                    </div>
                  </CardFooter>
                </Card>
              </div>
              <div v-else class="text-center py-6 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <p class="text-muted-foreground">No active plans available</p>
              </div>
            </div>

            <!-- Archived Plans Section -->
            <div v-if="plans.filter(p => p.archived).length > 0">
              <h3 class="text-xl font-semibold mb-4">Archived Plans</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <Card v-for="plan in plans.filter(p => p.archived)" :key="plan.id" class="flex flex-col border-gray-200 bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                  <CardHeader>
                    <div class="flex items-center justify-between">
                      <div class="flex items-center gap-2">
                        <CardTitle>{{ plan.name.replace(' (Archived)', '') }}</CardTitle>
                        <Badge variant="outline" class="text-xs bg-gray-100 text-gray-700">Archived</Badge>
                      </div>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon">
                            <MoreHorizontal class="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem @click="unarchivePlan(plan)">
                            <Edit class="h-4 w-4 mr-2" />
                            Unarchive Plan
                          </DropdownMenuItem>
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </div>
                    <CardDescription>{{ plan.description }}</CardDescription>
                  </CardHeader>
                  <CardContent class="flex-grow">
                    <div class="mb-4">
                      <div class="flex items-end">
                        <span class="text-2xl font-bold">{{ formatCurrency(plan.price) }}</span>
                        <span class="text-muted-foreground ml-1">/ {{ plan.interval }}</span>
                      </div>
                      <div v-if="plan.trial_days" class="mt-1 text-sm text-green-600">
                        {{ plan.trial_days }} day{{ plan.trial_days !== 1 ? 's' : '' }} free trial
                      </div>
                    </div>
                    <div class="space-y-2">
                      <h3 class="text-sm font-medium">Features:</h3>
                      <ul class="space-y-2">
                        <li v-for="(feature, index) in plan.features" :key="index" class="flex items-start">
                          <div v-if="feature.included" class="rounded-full p-0.5 mr-2 mt-0.5 text-green-500 flex-shrink-0">
                            <Check class="h-4 w-4" />
                          </div>
                          <div v-else class="rounded-full p-0.5 mr-2 mt-0.5 text-gray-400 flex-shrink-0">
                            <X class="h-4 w-4" />
                          </div>
                          <span :class="{ 'text-muted-foreground': !feature.included }">
                            {{ feature.name }}
                          </span>
                        </li>
                      </ul>
                    </div>
                  </CardContent>
                  <CardFooter>
                    <div class="text-sm text-muted-foreground">
                      Created: {{ plan.created }}
                    </div>
                  </CardFooter>
                </Card>
              </div>
            </div>

            <!-- No Plans Message - Only show if there are no plans at all -->
            <div v-if="plans.length === 0" class="text-center py-12">
              <Package class="h-12 w-12 mx-auto text-muted-foreground mb-4" />
              <h3 class="text-lg font-medium mb-2">No Plans Available</h3>
              <p class="text-muted-foreground mb-6 max-w-md mx-auto">
                You haven't created any subscription plans yet. Click the button below to create your first plan.
              </p>
              <Button @click="createDialogOpen = true">
                <Plus class="h-4 w-4 mr-2" />
                Create Plan
              </Button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Plan Dialog -->
    <Dialog v-model:open="createDialogOpen">
      <DialogContent class="sm:max-w-[600px]">
        <DialogHeader>
          <DialogTitle>Create New Plan</DialogTitle>
          <DialogDescription>
            Create a new subscription plan with features. This will be created in Stripe.
          </DialogDescription>
        </DialogHeader>
        <div class="grid gap-4 py-4">
          <div class="grid grid-cols-4 items-center gap-4">
            <Label class="text-right" for="name">Name</Label>
            <Input id="name" v-model="createForm.name" class="col-span-3" placeholder="e.g. Professional" />
          </div>
          <div class="grid grid-cols-4 items-center gap-4">
            <Label class="text-right" for="description">Description</Label>
            <Textarea id="description" v-model="createForm.description" class="col-span-3" placeholder="e.g. For growing businesses and teams" />
          </div>
          <div class="grid grid-cols-4 items-center gap-4">
            <Label class="text-right" for="price">Price</Label>
            <div class="col-span-3 relative">
              <DollarSign class="absolute left-2 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input id="price" v-model="createForm.price" type="number" min="0" step="0.01" class="pl-8" placeholder="e.g. 79.99" />
            </div>
          </div>
          <div class="grid grid-cols-4 items-center gap-4">
            <Label class="text-right" for="interval">Billing Interval</Label>
            <Select v-model="createForm.interval" class="col-span-3">
              <SelectTrigger>
                <SelectValue placeholder="Select billing interval" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="month">Monthly</SelectItem>
                <SelectItem value="year">Yearly</SelectItem>
              </SelectContent>
            </Select>
          </div>

          <div class="grid grid-cols-4 items-center gap-4">
            <Label class="text-right" for="trial_days">Free Trial (Days)</Label>
            <Input id="trial_days" v-model="createForm.trial_days" type="number" min="0" class="col-span-3" placeholder="e.g. 14 (0 for no trial)" />
          </div>

          <div class="grid grid-cols-4 gap-4">
            <Label class="text-right pt-2">Features</Label>
            <div class="col-span-3 space-y-3">
              <div v-for="(feature, index) in createForm.features" :key="index" class="flex items-center gap-2">
                <Input v-model="feature.name" placeholder="e.g. 5 AI Agents" class="flex-grow" />
                <div class="flex items-center gap-2">
                  <Label class="text-sm">Included:</Label>
                  <input type="checkbox" v-model="feature.included" class="rounded" />
                </div>
                <Button v-if="index > 0" variant="ghost" size="icon" @click="removeFeature(index)" class="text-red-500">
                  <Trash2 class="h-4 w-4" />
                </Button>
              </div>
              <Button variant="outline" size="sm" @click="addFeature" class="mt-2">
                <Plus class="h-4 w-4 mr-2" />
                Add Feature
              </Button>
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="createDialogOpen = false">Cancel</Button>
          <Button @click="createPlan" :disabled="createForm.processing">
            <span v-if="createForm.processing">Creating...</span>
            <span v-else>Create Plan</span>
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Edit Plan Dialog -->
    <Dialog v-model:open="editDialogOpen">
      <DialogContent class="sm:max-w-[600px]">
        <DialogHeader>
          <DialogTitle>Edit Plan</DialogTitle>
          <DialogDescription>
            Update the plan details and features. Note: Price and interval cannot be changed after creation.
          </DialogDescription>
        </DialogHeader>
        <div class="grid gap-4 py-4">
          <div class="grid grid-cols-4 items-center gap-4">
            <Label class="text-right" for="edit-name">Name</Label>
            <Input id="edit-name" v-model="editForm.name" class="col-span-3" />
          </div>
          <div class="grid grid-cols-4 items-center gap-4">
            <Label class="text-right" for="edit-description">Description</Label>
            <Textarea id="edit-description" v-model="editForm.description" class="col-span-3" />
          </div>

          <div class="grid grid-cols-4 items-center gap-4">
            <Label class="text-right" for="edit-trial-days">Free Trial (Days)</Label>
            <Input id="edit-trial-days" v-model="editForm.trial_days" type="number" min="0" class="col-span-3" placeholder="e.g. 14 (0 for no trial)" />
          </div>

          <div class="grid grid-cols-4 gap-4">
            <Label class="text-right pt-2">Features</Label>
            <div class="col-span-3 space-y-3">
              <div v-for="(feature, index) in editForm.features" :key="index" class="flex items-center gap-2">
                <Input v-model="feature.name" class="flex-grow" />
                <div class="flex items-center gap-2">
                  <Label class="text-sm">Included:</Label>
                  <input type="checkbox" v-model="feature.included" class="rounded" />
                </div>
                <Button v-if="index > 0" variant="ghost" size="icon" @click="removeEditFeature(index)" class="text-red-500">
                  <Trash2 class="h-4 w-4" />
                </Button>
              </div>
              <Button variant="outline" size="sm" @click="addEditFeature" class="mt-2">
                <Plus class="h-4 w-4 mr-2" />
                Add Feature
              </Button>
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="editDialogOpen = false">Cancel</Button>
          <Button @click="editPlan" :disabled="editForm.processing">
            <span v-if="editForm.processing">Updating...</span>
            <span v-else>Update Plan</span>
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Delete Plan Dialog -->
    <Dialog v-model:open="deleteDialogOpen">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Archive Plan</DialogTitle>
          <DialogDescription>
            Are you sure you want to archive this plan? This will make it unavailable for new subscriptions.
            Existing subscriptions will not be affected.
          </DialogDescription>
          <div class="mt-2 text-sm text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/30 p-2 rounded">
            <strong>Note:</strong> The plan will be archived in Stripe. All features and settings will be preserved.
          </div>
        </DialogHeader>
        <div class="py-4">
          <p class="font-medium">{{ selectedPlan?.name }}</p>
          <p class="text-muted-foreground">{{ formatCurrency(selectedPlan?.price || 0) }} / {{ selectedPlan?.interval }}</p>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="deleteDialogOpen = false">Cancel</Button>
          <Button variant="destructive" @click="deletePlan">
            Archive Plan
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AdminLayout>
</template>
