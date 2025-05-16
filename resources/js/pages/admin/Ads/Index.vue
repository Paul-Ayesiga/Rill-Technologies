<script setup lang="ts">
import { ref, computed, reactive } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCaption, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { toast } from 'vue-sonner';
import {
  Plus,
  MoreVertical,
  Pencil,
  Trash2,
  Eye,
  BarChart3,
  ImageIcon,
  ToggleLeft,
  ToggleRight,
  Calendar,
  Link as LinkIcon,
  Settings,
  Upload,
  X
} from 'lucide-vue-next';
import { type BreadcrumbItem } from '@/types';

// Define props
const props = defineProps<{
  ads: Array<{
    id: number;
    name: string;
    type: string;
    position: string | null;
    is_active: boolean;
    impressions: number;
    clicks: number;
    ctr: number;
    start_date: string | null;
    end_date: string | null;
    created_at: string;
    image_url?: string | null;
    title?: string | null;
    description?: string | null;
    url?: string | null;
    ad_id?: string;
    pages?: string[] | null;
    settings?: Record<string, any> | null;
  }>;
}>();

// State for dialogs
const showDeleteDialog = ref(false);
const showCreateDialog = ref(false);
const showEditDialog = ref(false);
const showViewDialog = ref(false);
const adToDelete = ref<number | null>(null);
const selectedAd = ref<any>(null);
const imagePreview = ref<string | null>(null);

// Available pages for targeting
const availablePages = [
  { value: 'all', label: 'All Pages' },
  { value: 'welcome', label: 'Welcome Page' },
  { value: 'pricing', label: 'Pricing Page' },
  { value: 'dashboard', label: 'Dashboard' },
  { value: 'agents', label: 'Agents Page' },
  { value: 'billing', label: 'Billing Page' },
];

// Available ad types
const adTypes = [
  { value: 'banner', label: 'Side Banner', description: 'Displays on the left or right side of the page' },
  { value: 'floating', label: 'Floating Ad', description: 'Appears in one of the corners of the page' },
  { value: 'top', label: 'Promotional Banner', description: 'Slim banner at the top of the page for promotions' },
];

// Available positions based on ad type
const positionsForType = computed(() => {
  const type = form.type;

  if (type === 'banner') {
    return [
      { value: 'left', label: 'Left' },
      { value: 'right', label: 'Right' },
    ];
  } else if (type === 'floating') {
    return [
      { value: 'bottom-right', label: 'Bottom Right' },
      { value: 'bottom-left', label: 'Bottom Left' },
      { value: 'top-right', label: 'Top Right' },
      { value: 'top-left', label: 'Top Left' },
    ];
  } else if (type === 'top') {
    return [
      { value: 'top', label: 'Top' },
    ];
  }

  return [];
});

// Create form
const form = useForm({
  name: '',
  type: 'banner',
  position: '',
  image: null as File | null,
  title: '',
  description: '',
  url: '',
  is_active: true,
  start_date: '',
  end_date: '',
  pages: ['all'],
  settings: {},
});

// Edit form
const editForm = useForm({
  id: 0,
  name: '',
  type: 'banner',
  position: '',
  image: null as File | null,
  title: '',
  description: '',
  url: '',
  is_active: true,
  start_date: '',
  end_date: '',
  pages: ['all'],
  settings: {},
  _method: 'PUT',
});

// Function to open create dialog
const openCreateDialog = () => {
  form.reset();
  imagePreview.value = null;
  showCreateDialog.value = true;
};

// Function to open edit dialog
const openEditDialog = (ad: any) => {
  selectedAd.value = ad;
  editForm.id = ad.id;
  editForm.name = ad.name;
  editForm.type = ad.type;
  editForm.position = ad.position || '';
  editForm.image = null;
  editForm.title = ad.title || '';
  editForm.description = ad.description || '';
  editForm.url = ad.url || '';
  editForm.is_active = ad.is_active;
  editForm.start_date = ad.start_date || '';
  editForm.end_date = ad.end_date || '';
  editForm.pages = ad.pages || ['all'];
  editForm.settings = ad.settings || {};

  imagePreview.value = ad.image_url;
  showEditDialog.value = true;
};

// Function to open view dialog
const openViewDialog = (ad: any) => {
  selectedAd.value = ad;
  showViewDialog.value = true;
};

// Function to confirm ad deletion
const confirmDelete = (id: number) => {
  adToDelete.value = id;
  showDeleteDialog.value = true;
};

// Function to delete ad
const deleteAd = () => {
  if (adToDelete.value) {
    router.delete(route('admin.ads.destroy', adToDelete.value), {
      onSuccess: () => {
        toast.success('Ad deleted successfully');
        showDeleteDialog.value = false;
        adToDelete.value = null;
      },
      onError: (errors) => {
        toast.error('Failed to delete ad');
        console.error(errors);
      }
    });
  }
};

// Function to toggle ad status
const toggleAdStatus = (id: number) => {
  router.put(route('admin.ads.toggle-status', id), {}, {
    onSuccess: () => {
      toast.success('Ad status updated successfully');
    },
    onError: (errors) => {
      toast.error('Failed to update ad status');
      console.error(errors);
    }
  });
};

// Function to handle image selection
const handleImageSelect = (event: Event, formType: 'create' | 'edit') => {
  const input = event.target as HTMLInputElement;
  if (input.files && input.files.length > 0) {
    const file = input.files[0];

    // Update the form
    if (formType === 'create') {
      form.image = file;
    } else {
      editForm.image = file;
    }

    // Create preview
    const reader = new FileReader();
    reader.onload = (e) => {
      imagePreview.value = e.target?.result as string;
    };
    reader.readAsDataURL(file);
  }
};

// Function to clear image
const clearImage = (formType: 'create' | 'edit') => {
  if (formType === 'create') {
    form.image = null;
  } else {
    editForm.image = null;
  }
  imagePreview.value = null;
};

// Function to submit create form
const createAd = () => {
  form.post(route('admin.ads.store'), {
    onSuccess: () => {
      toast.success('Ad created successfully');
      showCreateDialog.value = false;
      form.reset();
      imagePreview.value = null;
    },
    onError: (errors) => {
      toast.error('Failed to create ad');
      console.error(errors);
    }
  });
};

// Function to submit edit form
const updateAd = () => {
  editForm.post(route('admin.ads.update', editForm.id), {
    onSuccess: () => {
      toast.success('Ad updated successfully');
      showEditDialog.value = false;
      editForm.reset();
      imagePreview.value = null;
    },
    onError: (errors) => {
      toast.error('Failed to update ad');
      console.error(errors);
    }
  });
};

// Format ad type for display
const formatAdType = (type: string) => {
  switch (type) {
    case 'banner':
      return 'Side Banner';
    case 'floating':
      return 'Floating Ad';
    case 'top':
      return 'Promotional Banner';
    default:
      return type;
  }
};

// Format ad position for display
const formatAdPosition = (position: string | null) => {
  if (!position) return 'N/A';

  switch (position) {
    case 'left':
      return 'Left';
    case 'right':
      return 'Right';
    case 'top':
      return 'Top';
    case 'bottom':
      return 'Bottom';
    case 'bottom-right':
      return 'Bottom Right';
    case 'bottom-left':
      return 'Bottom Left';
    case 'top-right':
      return 'Top Right';
    case 'top-left':
      return 'Top Left';
    default:
      return position;
  }
};

// Define breadcrumbs for the admin layout
const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Admin Dashboard',
    href: route('admin.dashboard'),
  },
  {
    title: 'Advertisements',
    href: route('admin.ads.index'),
  },
];
</script>

<template>
  <Head title="Ad Management" />

  <AdminLayout :breadcrumbs="breadcrumbs">
    <div class="p-6 space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold">Ad Management</h1>
          <p class="text-muted-foreground">Manage advertisements across the platform</p>
        </div>
        <Button @click="openCreateDialog">
          <Plus class="h-4 w-4 mr-2" />
          Create Ad
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Advertisements</CardTitle>
          <CardDescription>
            View and manage all advertisements on the platform
          </CardDescription>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>Type</TableHead>
                <TableHead>Position</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Impressions</TableHead>
                <TableHead>Clicks</TableHead>
                <TableHead>CTR</TableHead>
                <TableHead>Created</TableHead>
                <TableHead class="text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              <TableRow v-for="ad in props.ads" :key="ad.id">
                <TableCell class="font-medium">{{ ad.name }}</TableCell>
                <TableCell>{{ formatAdType(ad.type) }}</TableCell>
                <TableCell>{{ formatAdPosition(ad.position) }}</TableCell>
                <TableCell>
                  <Badge :variant="ad.is_active ? 'default' : 'outline'">
                    {{ ad.is_active ? 'Active' : 'Inactive' }}
                  </Badge>
                </TableCell>
                <TableCell>{{ ad.impressions }}</TableCell>
                <TableCell>{{ ad.clicks }}</TableCell>
                <TableCell>{{ ad.ctr }}%</TableCell>
                <TableCell>{{ ad.created_at }}</TableCell>
                <TableCell class="text-right">
                  <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                      <Button variant="ghost" size="icon">
                        <MoreVertical class="h-4 w-4" />
                        <span class="sr-only">Open menu</span>
                      </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                      <DropdownMenuLabel>Actions</DropdownMenuLabel>
                      <DropdownMenuItem @click="openViewDialog(ad)">
                        <Eye class="h-4 w-4 mr-2" />
                        View
                      </DropdownMenuItem>
                      <DropdownMenuItem @click="openEditDialog(ad)">
                        <Pencil class="h-4 w-4 mr-2" />
                        Edit
                      </DropdownMenuItem>
                      <DropdownMenuItem @click="toggleAdStatus(ad.id)">
                        <component :is="ad.is_active ? ToggleRight : ToggleLeft" class="h-4 w-4 mr-2" />
                        {{ ad.is_active ? 'Deactivate' : 'Activate' }}
                      </DropdownMenuItem>
                      <DropdownMenuSeparator />
                      <DropdownMenuItem @click="confirmDelete(ad.id)" class="text-destructive focus:text-destructive">
                        <Trash2 class="h-4 w-4 mr-2" />
                        Delete
                      </DropdownMenuItem>
                    </DropdownMenuContent>
                  </DropdownMenu>
                </TableCell>
              </TableRow>
              <TableRow v-if="props.ads.length === 0">
                <TableCell colspan="9" class="text-center py-6">
                  <div class="flex flex-col items-center justify-center text-muted-foreground">
                    <ImageIcon class="h-12 w-12 mb-2" />
                    <h3 class="text-lg font-medium">No ads found</h3>
                    <p>Create your first ad to get started</p>
                    <Button @click="openCreateDialog" class="mt-4">
                      <Plus class="h-4 w-4 mr-2" />
                      Create Ad
                    </Button>
                  </div>
                </TableCell>
              </TableRow>
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <!-- Create Ad Dialog -->
      <Dialog v-model:open="showCreateDialog">
        <DialogContent class="sm:max-w-[500px] max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Create New Ad</DialogTitle>
            <DialogDescription>
              Fill in the details to create a new advertisement.
            </DialogDescription>
          </DialogHeader>

          <div class="grid gap-2 py-2">
            <div class="grid grid-cols-2 gap-2">
              <div class="space-y-1">
                <Label for="name" class="text-xs">Ad Name</Label>
                <Input id="name" v-model="form.name" placeholder="Enter ad name" class="h-8" />
                <p v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</p>
              </div>

            <div class="space-y-1">
              <Label for="type" class="text-xs">Ad Type</Label>
              <Select v-model="form.type">
                <SelectTrigger class="h-8">
                  <SelectValue placeholder="Select ad type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="type in adTypes" :key="type.value" :value="type.value">
                    <div>
                      <div>{{ type.label }}</div>
                      <div class="text-xs text-muted-foreground">{{ type.description }}</div>
                    </div>
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.type" class="text-xs text-destructive">{{ form.errors.type }}</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div class="space-y-1">
              <Label for="position" class="text-xs">Position</Label>
              <Select v-model="form.position">
                <SelectTrigger class="h-8">
                  <SelectValue placeholder="Select position" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="position in positionsForType" :key="position.value" :value="position.value">
                    {{ position.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="form.errors.position" class="text-xs text-destructive">{{ form.errors.position }}</p>
            </div>

            <div class="space-y-1">
              <Label for="url" class="text-xs">URL</Label>
              <Input id="url" v-model="form.url" placeholder="https://example.com" class="h-8" />
              <p v-if="form.errors.url" class="text-xs text-destructive">{{ form.errors.url }}</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div class="space-y-1">
              <Label for="title" class="text-xs">Title</Label>
              <Input id="title" v-model="form.title" placeholder="Ad title" class="h-8" />
              <p v-if="form.errors.title" class="text-xs text-destructive">{{ form.errors.title }}</p>
            </div>

            <div class="space-y-1">
              <Label for="is_active" class="flex items-center justify-between text-xs">
                Active Status
                <Switch id="is_active" v-model="form.is_active" />
              </Label>
              <p v-if="form.errors.is_active" class="text-xs text-destructive">{{ form.errors.is_active }}</p>
            </div>
          </div>

          <div class="space-y-1">
            <Label for="description" class="text-xs">Description</Label>
            <Textarea id="description" v-model="form.description" placeholder="Ad description" class="min-h-[60px]" />
            <p v-if="form.errors.description" class="text-xs text-destructive">{{ form.errors.description }}</p>
          </div>

          <div class="space-y-1">
            <Label for="image" class="text-xs">Image</Label>
            <div class="flex items-center gap-3">
              <div v-if="imagePreview" class="relative w-20 h-20 border rounded-md overflow-hidden">
                <img :src="imagePreview" alt="Ad preview" class="w-full h-full object-cover" />
                <Button
                  variant="destructive"
                  size="icon"
                  class="absolute top-1 right-1 h-5 w-5 rounded-full"
                  @click="clearImage('create')"
                >
                  <X class="h-2.5 w-2.5" />
                </Button>
              </div>
              <div v-else class="border border-dashed rounded-md p-3 w-full">
                <label for="image-upload" class="flex flex-col items-center justify-center cursor-pointer">
                  <Upload class="h-6 w-6 text-muted-foreground mb-1" />
                  <span class="text-xs text-muted-foreground">Click to upload image</span>
                </label>
                <input
                  id="image-upload"
                  type="file"
                  accept="image/*"
                  class="hidden"
                  @change="(e) => handleImageSelect(e, 'create')"
                />
              </div>
            </div>
            <p v-if="form.errors.image" class="text-xs text-destructive">{{ form.errors.image }}</p>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div class="space-y-1">
              <Label for="start_date" class="text-xs">Start Date</Label>
              <Input id="start_date" v-model="form.start_date" type="date" class="h-8" />
              <p v-if="form.errors.start_date" class="text-xs text-destructive">{{ form.errors.start_date }}</p>
            </div>

            <div class="space-y-1">
              <Label for="end_date" class="text-xs">End Date</Label>
              <Input id="end_date" v-model="form.end_date" type="date" class="h-8" />
              <p v-if="form.errors.end_date" class="text-xs text-destructive">{{ form.errors.end_date }}</p>
            </div>
          </div>

          <div class="space-y-1">
            <Label for="pages" class="text-xs">Target Pages</Label>
            <div class="grid grid-cols-3 gap-2">
              <div v-for="page in availablePages" :key="page.value" class="flex items-center space-x-2">
                <input
                  type="checkbox"
                  :id="'page-' + page.value"
                  :value="page.value"
                  v-model="form.pages"
                  class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                />
                <label :for="'page-' + page.value" class="text-xs">{{ page.label }}</label>
              </div>
            </div>
            <p class="text-xs text-muted-foreground">Select which pages this ad should appear on</p>
            <p class="text-xs text-amber-500 font-medium">
              <span class="inline-flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Ads will appear on all selected pages in the position specified above
              </span>
            </p>
            <p v-if="form.errors.pages" class="text-xs text-destructive">{{ form.errors.pages }}</p>
          </div>
        </div>

          <DialogFooter>
            <Button variant="outline" @click="showCreateDialog = false">Cancel</Button>
            <Button type="submit" @click="createAd" :disabled="form.processing">
              <span v-if="form.processing">Creating...</span>
              <span v-else>Create Ad</span>
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <!-- Edit Ad Dialog -->
      <Dialog v-model:open="showEditDialog">
        <DialogContent class="sm:max-w-[500px] max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Edit Ad</DialogTitle>
            <DialogDescription>
              Update the details of this advertisement.
            </DialogDescription>
          </DialogHeader>

          <div class="grid gap-2 py-2">
            <div class="grid grid-cols-2 gap-2">
              <div class="space-y-1">
                <Label for="edit-name" class="text-xs">Ad Name</Label>
                <Input id="edit-name" v-model="editForm.name" placeholder="Enter ad name" class="h-8" />
                <p v-if="editForm.errors.name" class="text-xs text-destructive">{{ editForm.errors.name }}</p>
              </div>

            <div class="space-y-1">
              <Label for="edit-type" class="text-xs">Ad Type</Label>
              <Select v-model="editForm.type">
                <SelectTrigger class="h-8">
                  <SelectValue placeholder="Select ad type" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="type in adTypes" :key="type.value" :value="type.value">
                    <div>
                      <div>{{ type.label }}</div>
                      <div class="text-xs text-muted-foreground">{{ type.description }}</div>
                    </div>
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="editForm.errors.type" class="text-xs text-destructive">{{ editForm.errors.type }}</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div class="space-y-1">
              <Label for="edit-position" class="text-xs">Position</Label>
              <Select v-model="editForm.position">
                <SelectTrigger class="h-8">
                  <SelectValue placeholder="Select position" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem v-for="position in positionsForType" :key="position.value" :value="position.value">
                    {{ position.label }}
                  </SelectItem>
                </SelectContent>
              </Select>
              <p v-if="editForm.errors.position" class="text-xs text-destructive">{{ editForm.errors.position }}</p>
            </div>

            <div class="space-y-1">
              <Label for="edit-url" class="text-xs">URL</Label>
              <Input id="edit-url" v-model="editForm.url" placeholder="https://example.com" class="h-8" />
              <p v-if="editForm.errors.url" class="text-xs text-destructive">{{ editForm.errors.url }}</p>
            </div>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div class="space-y-1">
              <Label for="edit-title" class="text-xs">Title</Label>
              <Input id="edit-title" v-model="editForm.title" placeholder="Ad title" class="h-8" />
              <p v-if="editForm.errors.title" class="text-xs text-destructive">{{ editForm.errors.title }}</p>
            </div>

            <div class="space-y-1">
              <Label for="edit-is_active" class="flex items-center justify-between text-xs">
                Active Status
                <Switch id="edit-is_active" v-model="editForm.is_active" />
              </Label>
              <p v-if="editForm.errors.is_active" class="text-xs text-destructive">{{ editForm.errors.is_active }}</p>
            </div>
          </div>

          <div class="space-y-1">
            <Label for="edit-description" class="text-xs">Description</Label>
            <Textarea id="edit-description" v-model="editForm.description" placeholder="Ad description" class="min-h-[60px]" />
            <p v-if="editForm.errors.description" class="text-xs text-destructive">{{ editForm.errors.description }}</p>
          </div>

          <div class="space-y-1">
            <Label for="edit-image" class="text-xs">Image</Label>
            <div class="flex items-center gap-3">
              <div v-if="imagePreview" class="relative w-20 h-20 border rounded-md overflow-hidden">
                <img :src="imagePreview" alt="Ad preview" class="w-full h-full object-cover" />
                <Button
                  variant="destructive"
                  size="icon"
                  class="absolute top-1 right-1 h-5 w-5 rounded-full"
                  @click="clearImage('edit')"
                >
                  <X class="h-2.5 w-2.5" />
                </Button>
              </div>
              <div v-else class="border border-dashed rounded-md p-3 w-full">
                <label for="edit-image-upload" class="flex flex-col items-center justify-center cursor-pointer">
                  <Upload class="h-6 w-6 text-muted-foreground mb-1" />
                  <span class="text-xs text-muted-foreground">Click to upload image</span>
                </label>
                <input
                  id="edit-image-upload"
                  type="file"
                  accept="image/*"
                  class="hidden"
                  @change="(e) => handleImageSelect(e, 'edit')"
                />
              </div>
            </div>
            <p v-if="editForm.errors.image" class="text-xs text-destructive">{{ editForm.errors.image }}</p>
          </div>

          <div class="grid grid-cols-2 gap-2">
            <div class="space-y-1">
              <Label for="edit-start_date" class="text-xs">Start Date</Label>
              <Input id="edit-start_date" v-model="editForm.start_date" type="date" class="h-8" />
              <p v-if="editForm.errors.start_date" class="text-xs text-destructive">{{ editForm.errors.start_date }}</p>
            </div>

            <div class="space-y-1">
              <Label for="edit-end_date" class="text-xs">End Date</Label>
              <Input id="edit-end_date" v-model="editForm.end_date" type="date" class="h-8" />
              <p v-if="editForm.errors.end_date" class="text-xs text-destructive">{{ editForm.errors.end_date }}</p>
            </div>
          </div>

          <div class="space-y-1">
            <Label for="edit-pages" class="text-xs">Target Pages</Label>
            <div class="grid grid-cols-3 gap-2">
              <div v-for="page in availablePages" :key="page.value" class="flex items-center space-x-2">
                <input
                  type="checkbox"
                  :id="'edit-page-' + page.value"
                  :value="page.value"
                  v-model="editForm.pages"
                  class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                />
                <label :for="'edit-page-' + page.value" class="text-xs">{{ page.label }}</label>
              </div>
            </div>
            <p class="text-xs text-muted-foreground">Select which pages this ad should appear on</p>
            <p class="text-xs text-amber-500 font-medium">
              <span class="inline-flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Ads will appear on all selected pages in the position specified above
              </span>
            </p>
            <p v-if="editForm.errors.pages" class="text-xs text-destructive">{{ editForm.errors.pages }}</p>
          </div>
        </div>

          <DialogFooter>
            <Button variant="outline" @click="showEditDialog = false">Cancel</Button>
            <Button type="submit" @click="updateAd" :disabled="editForm.processing">
              <span v-if="editForm.processing">Updating...</span>
              <span v-else>Update Ad</span>
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <!-- View Ad Dialog -->
      <Dialog v-model:open="showViewDialog">
        <DialogContent class="sm:max-w-[500px] max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Ad Details</DialogTitle>
            <DialogDescription>
              Viewing details for {{ selectedAd?.name }}
            </DialogDescription>
          </DialogHeader>

          <div v-if="selectedAd" class="space-y-4">
            <!-- Ad Image or Promotional Banner Preview -->
            <div v-if="selectedAd.type === 'top' && selectedAd.position === 'top'" class="mb-4">
              <h3 class="text-sm font-medium text-muted-foreground mb-2">Promotional Banner Preview</h3>
              <div class="bg-primary text-primary-foreground py-2 px-4 flex items-center justify-center gap-2 rounded">
                <span class="text-sm font-medium">{{ selectedAd.title || 'Special Offer' }}</span>
                <span v-if="selectedAd.description" class="text-sm">{{ selectedAd.description }}</span>
                <span class="text-xs font-semibold bg-white text-primary px-2 py-0.5 rounded">
                  Learn More →
                </span>
              </div>
            </div>

            <!-- Ad Image for other types -->
            <div v-else-if="selectedAd.image_url" class="flex justify-center">
              <div class="border rounded-md overflow-hidden max-w-xs">
                <img :src="selectedAd.image_url" :alt="selectedAd.title || 'Ad image'" class="w-full h-auto" />
              </div>
            </div>

            <!-- Ad Details -->
            <div class="grid grid-cols-2 gap-2">
              <div>
                <h3 class="text-sm font-medium text-muted-foreground">Name</h3>
                <p>{{ selectedAd.name }}</p>
              </div>

              <div>
                <h3 class="text-sm font-medium text-muted-foreground">Type</h3>
                <p>{{ formatAdType(selectedAd.type) }}</p>
              </div>

              <div>
                <h3 class="text-sm font-medium text-muted-foreground">Position</h3>
                <p>{{ formatAdPosition(selectedAd.position) }}</p>
              </div>

              <div>
                <h3 class="text-sm font-medium text-muted-foreground">Status</h3>
                <Badge :variant="selectedAd.is_active ? 'default' : 'outline'">
                  {{ selectedAd.is_active ? 'Active' : 'Inactive' }}
                </Badge>
              </div>

              <div>
                <h3 class="text-sm font-medium text-muted-foreground">Title</h3>
                <p>{{ selectedAd.title || 'N/A' }}</p>
              </div>

              <div>
                <h3 class="text-sm font-medium text-muted-foreground">URL</h3>
                <p class="truncate">{{ selectedAd.url || 'N/A' }}</p>
              </div>
            </div>

            <div v-if="selectedAd.description">
              <h3 class="text-sm font-medium text-muted-foreground">Description</h3>
              <p>{{ selectedAd.description }}</p>
            </div>

            <div class="grid grid-cols-2 gap-2">
              <div>
                <h3 class="text-sm font-medium text-muted-foreground">Start Date</h3>
                <p>{{ selectedAd.start_date || 'N/A' }}</p>
              </div>

              <div>
                <h3 class="text-sm font-medium text-muted-foreground">End Date</h3>
                <p>{{ selectedAd.end_date || 'N/A' }}</p>
              </div>
            </div>

            <!-- Target Pages -->
            <div v-if="selectedAd.pages && selectedAd.pages.length > 0" class="mb-4">
              <h3 class="text-sm font-medium text-muted-foreground mb-2">Target Pages</h3>
              <div class="flex flex-wrap gap-2">
                <Badge v-for="page in selectedAd.pages" :key="page" variant="outline" class="text-xs">
                  {{ availablePages.find(p => p.value === page)?.label || page }}
                </Badge>
              </div>
            </div>

            <!-- Performance Metrics -->
            <div class="bg-muted/50 p-2 rounded-md">
              <h3 class="text-sm font-medium mb-2">Performance Metrics</h3>
              <div class="grid grid-cols-3 gap-2">
                <div>
                  <p class="text-xs text-muted-foreground">Impressions</p>
                  <p class="text-lg font-semibold">{{ selectedAd.impressions }}</p>
                </div>

                <div>
                  <p class="text-xs text-muted-foreground">Clicks</p>
                  <p class="text-lg font-semibold">{{ selectedAd.clicks }}</p>
                </div>

                <div>
                  <p class="text-xs text-muted-foreground">CTR</p>
                  <p class="text-lg font-semibold">{{ selectedAd.ctr }}%</p>
                </div>
              </div>
            </div>
          </div>

          <DialogFooter>
            <Button variant="outline" @click="showViewDialog = false">Close</Button>
            <Button @click="() => { showViewDialog = false; openEditDialog(selectedAd); }">Edit</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <!-- Delete Confirmation Dialog -->
      <Dialog v-model:open="showDeleteDialog">
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Delete Ad</DialogTitle>
            <DialogDescription>
              Are you sure you want to delete this ad? This action cannot be undone.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" @click="showDeleteDialog = false">Cancel</Button>
            <Button variant="destructive" @click="deleteAd">Delete</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  </AdminLayout>
</template>
