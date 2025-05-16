<script setup lang="ts">
import { Head, Deferred } from '@inertiajs/vue3';
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
  CreditCard, CheckCircle, AlertTriangle, Ban, RefreshCw
} from 'lucide-vue-next';
import { h } from 'vue';
import { router } from '@inertiajs/vue3';
import { valueUpdater } from '@/components/ui/table/utils';
import { toast } from 'vue-sonner';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

// Define the Subscription type
interface Subscription {
  id: number;
  user: {
    id: number;
    name: string;
    email: string;
  };
  name: string;
  stripe_id: string;
  stripe_status: string;
  stripe_price: string;
  quantity: number;
  trial_ends_at: string | null;
  ends_at: string | null;
  created_at: string;
}

// Define the Stats type
interface Stats {
  total: number;
  active: number;
  trialing: number;
  canceled: number;
  past_due: number;
  incomplete: number;
  incomplete_expired: number;
}

// Define the Plan type
interface Plan {
  id: string;
  name: string;
  price: number;
  interval: string;
  currency: string;
}

// Get props
const props = defineProps<{
  subscriptions: Subscription[];
  stats: Stats;
  plans: Plan[];
}>();

// Create a ref to hold the subscription data
const subscriptionsData = ref<Subscription[]>([]);

// Table state
const sorting = ref<SortingState>([]);
const columnFilters = ref<ColumnFiltersState>([]);
const columnVisibility = ref<VisibilityState>({});
const rowSelection = ref({});

// Define table columns
const columns: ColumnDef<Subscription>[] = [
  {
    id: 'userName',
    accessorFn: (row: Subscription) => row.user.name,
    header: ({ column }: any) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      }, () => ['Customer', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })])
    },
    cell: ({ row }: any) => h('div', { class: 'font-medium' }, row.getValue('userName')),
  },
  {
    id: 'userEmail',
    accessorFn: (row: Subscription) => row.user.email,
    header: ({ column }: any) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      }, () => ['Email', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })])
    },
    cell: ({ row }: any) => h('div', { class: 'lowercase' }, row.getValue('userEmail')),
  },
  {
    id: 'planName',
    accessorFn: (row: Subscription) => {
      // Debug the row data to see what's available
      console.log('Row data for plan:', row);
      return row.name || 'Unknown Plan';
    },
    header: ({ column }: any) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      }, () => ['Plan', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })])
    },
    cell: ({ row }: any) => {
      const planName = row.getValue('planName');
      console.log('Plan name value:', planName);

      // Check if the plan name contains "(Archived)"
      const isArchived = typeof planName === 'string' && planName.includes('(Archived)');

      if (isArchived) {
        return h('div', { class: 'flex items-center gap-2' }, [
          h('span', {}, planName.replace(' (Archived)', '')),
          h(Badge, { variant: 'outline', class: 'text-xs bg-gray-100 text-gray-700' }, () => 'Archived')
        ]);
      }

      return h('div', {}, planName);
    },
  },
  {
    accessorKey: 'stripe_status',
    header: 'Status',
    cell: ({ row }: any) => {
      const status = row.getValue('stripe_status') as string;
      return h(Badge, {
        variant: 'outline',
        class: {
          'border-green-500 text-green-500': status === 'active',
          'border-amber-500 text-amber-500': status === 'trialing',
          'border-red-500 text-red-500': status === 'canceled' || status === 'incomplete_expired',
          'border-blue-500 text-blue-500': status === 'incomplete',
          'border-purple-500 text-purple-500': status === 'past_due',
        }
      }, () => status.charAt(0).toUpperCase() + status.slice(1));
    },
  },
  {
    accessorKey: 'created_at',
    header: ({ column }: any) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      }, () => ['Created', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })])
    },
    cell: ({ row }: any) => h('div', {}, row.getValue('created_at')),
  },
  {
    id: 'actions',
    enableHiding: false,
    cell: ({ row }: any) => {
      const subscription = row.original;

      return h(DropdownMenu, {}, {
        default: () => [
          h(DropdownMenuTrigger, { asChild: true }, () =>
            h(Button, { variant: 'ghost', class: 'h-8 w-8 p-0' }, () =>
              h(MoreHorizontal, { class: 'h-4 w-4' })
            )
          ),
          h(DropdownMenuContent, { align: 'end' }, () => [
            h(DropdownMenuItem, {
              onClick: () => router.visit(route('admin.subscriptions.show', subscription.id))
            }, () => [
              h(Eye, { class: 'mr-2 h-4 w-4' }),
              'View Details'
            ]),
            h(DropdownMenuSeparator),
            h(DropdownMenuItem, {
              onClick: () => syncSubscription(subscription.id)
            }, () => [
              h(RefreshCw, { class: 'mr-2 h-4 w-4' }),
              'Sync with Stripe'
            ]),
          ])
        ]
      });
    },
  },
];

// Initialize table with empty data first
const table = useVueTable({
  get data() {
    return subscriptionsData.value;
  },
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

// Watch for changes in the subscriptions prop and update our local data
watch(() => props.subscriptions, (newSubscriptions: Subscription[] | undefined) => {
  if (newSubscriptions) {
    // Log the first subscription to see its structure
    if (newSubscriptions.length > 0) {
      console.log('First subscription data:', newSubscriptions[0]);
    }
    subscriptionsData.value = newSubscriptions;
  }
}, { deep: true });

// Function to sync subscription with Stripe
function syncSubscription(subscriptionId: number) {
  router.post(route('admin.subscriptions.sync', subscriptionId), {}, {
    onSuccess: () => {
      toast.success('Subscription synced with Stripe');
    },
    onError: () => {
      toast.error('Failed to sync subscription with Stripe');
    }
  });
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Admin Dashboard',
    href: route('admin.dashboard'),
  },
  {
    title: 'Subscriptions',
    href: route('admin.subscriptions.index'),
  },
];
</script>

<template>
  <Head title="Subscriptions" />

  <AdminLayout :breadcrumbs="breadcrumbs">
    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
          <div class="p-6 text-gray-900 dark:text-gray-100">
            <p class="mb-4 text-muted-foreground">
              Manage all customer subscriptions, view their status, and sync with Stripe.
              Use the search box to filter by customer name or click on column headers to sort.
            </p>

            <!-- Stats Cards -->
            <div class="grid gap-4 md:grid-cols-4 mb-6">
              <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle class="text-sm font-medium">Total Subscriptions</CardTitle>
                  <CreditCard class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                  <div class="text-2xl font-bold">{{ stats.total }}</div>
                </CardContent>
              </Card>
              <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle class="text-sm font-medium">Active</CardTitle>
                  <CheckCircle class="h-4 w-4 text-green-500" />
                </CardHeader>
                <CardContent>
                  <div class="text-2xl font-bold">{{ stats.active }}</div>
                </CardContent>
              </Card>
              <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle class="text-sm font-medium">Trialing</CardTitle>
                  <AlertTriangle class="h-4 w-4 text-amber-500" />
                </CardHeader>
                <CardContent>
                  <div class="text-2xl font-bold">{{ stats.trialing }}</div>
                </CardContent>
              </Card>
              <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                  <CardTitle class="text-sm font-medium">Canceled</CardTitle>
                  <Ban class="h-4 w-4 text-red-500" />
                </CardHeader>
                <CardContent>
                  <div class="text-2xl font-bold">{{ stats.canceled }}</div>
                </CardContent>
              </Card>
            </div>

            <div class="flex items-center py-4">
              <Input
                class="max-w-sm"
                placeholder="Filter by customer name..."
                :model-value="table.getColumn('userName')?.getFilterValue()?.toString() || ''"
                @update:model-value="table.getColumn('userName')?.setFilterValue($event)"
              >
                <template #prefix>
                  <Search class="h-4 w-4 text-gray-400" />
                </template>
              </Input>
            </div>

            <div class="rounded-md border">
              <Deferred data="subscriptions">
                <template #fallback>
                  <div class="animate-pulse">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead v-for="column in columns.slice(0, columns.length - 1)" :key="column.id">
                            <div class="h-6 bg-muted rounded w-24 mx-auto my-2"></div>
                          </TableHead>
                          <TableHead></TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        <TableRow v-for="i in 5" :key="i">
                          <TableCell>
                            <div class="h-5 bg-muted rounded w-32"></div>
                          </TableCell>
                          <TableCell>
                            <div class="h-5 bg-muted rounded w-40"></div>
                          </TableCell>
                          <TableCell>
                            <div class="h-5 bg-muted rounded w-24"></div>
                          </TableCell>
                          <TableCell>
                            <div class="h-6 bg-muted rounded w-20"></div>
                          </TableCell>
                          <TableCell>
                            <div class="h-5 bg-muted rounded w-32"></div>
                          </TableCell>
                          <TableCell>
                            <div class="h-8 bg-muted rounded w-8 mx-auto"></div>
                          </TableCell>
                        </TableRow>
                      </TableBody>
                    </Table>
                  </div>
                </template>

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
                        No subscriptions found.
                      </TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </Deferred>
            </div>

            <Deferred data="subscriptions">
              <template #fallback>
                <div class="flex items-center justify-end space-x-2 py-4">
                  <div class="flex-1 text-sm text-muted-foreground animate-pulse">
                    <div class="h-4 bg-muted rounded w-48"></div>
                  </div>
                  <div class="space-x-2">
                    <Button variant="outline" size="sm" disabled>
                      Previous
                    </Button>
                    <Button variant="outline" size="sm" disabled>
                      Next
                    </Button>
                  </div>
                </div>
              </template>

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
            </Deferred>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
