<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { ref, computed } from 'vue';
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
  ChevronDown, MoreHorizontal, UserCog, Eye, Ban,
  AlertTriangle, CheckCircle, Search, ArrowUpDown
} from 'lucide-vue-next';
import { h } from 'vue';
import { router } from '@inertiajs/vue3';
import { valueUpdater } from '@/components/ui/table/utils';
import { toast } from 'vue-sonner';

// Define the Customer type
interface Customer {
  id: number;
  name: string;
  email: string;
  created_at: string;
  status: 'active' | 'suspended' | 'banned';
  agents_count: number;
  subscription_status: string;
  last_login: string;
}

// Get customers from props
const props = defineProps<{
  customers: Customer[];
}>();

// Table state
const sorting = ref<SortingState>([]);
const columnFilters = ref<ColumnFiltersState>([]);
const columnVisibility = ref<VisibilityState>({});
const rowSelection = ref({});

// Define table columns
const columns: ColumnDef<Customer>[] = [
  {
    accessorKey: 'name',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      }, () => ['Name', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })])
    },
    cell: ({ row }) => h('div', { class: 'font-medium' }, row.getValue('name')),
  },
  {
    accessorKey: 'email',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      }, () => ['Email', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })])
    },
    cell: ({ row }) => h('div', { class: 'lowercase' }, row.getValue('email')),
  },
  {
    accessorKey: 'status',
    header: 'Status',
    cell: ({ row }) => {
      const status = row.getValue('status') as string;
      return h(Badge, {
        class: {
          'bg-green-500': status === 'active',
          'bg-amber-500': status === 'suspended',
          'bg-red-500': status === 'banned',
        }
      }, () => status.charAt(0).toUpperCase() + status.slice(1));
    },
  },
  {
    accessorKey: 'subscription_status',
    header: 'Subscription',
    cell: ({ row }) => {
      const status = row.getValue('subscription_status') as string;
      return h(Badge, {
        variant: 'outline',
        class: {
          'border-green-500 text-green-500': status === 'active',
          'border-amber-500 text-amber-500': status === 'trialing',
          'border-red-500 text-red-500': status === 'canceled' || status === 'incomplete_expired',
          'border-blue-500 text-blue-500': status === 'incomplete',
          'border-purple-500 text-purple-500': status === 'past_due',
          'border-gray-500 text-gray-500': status === 'none',
        }
      }, () => status === 'none' ? 'No Subscription' : status.charAt(0).toUpperCase() + status.slice(1));
    },
  },
  {
    accessorKey: 'agents_count',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      }, () => ['Agents', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })])
    },
    cell: ({ row }) => h('div', { class: 'text-center' }, row.getValue('agents_count')),
  },
  {
    accessorKey: 'created_at',
    header: ({ column }) => {
      return h(Button, {
        variant: 'ghost',
        onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
      }, () => ['Joined', h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })])
    },
    cell: ({ row }) => h('div', {}, row.getValue('created_at')),
  },
  {
    id: 'actions',
    enableHiding: false,
    cell: ({ row }) => {
      const customer = row.original;

      return h(DropdownMenu, {}, {
        default: () => [
          h(DropdownMenuTrigger, { asChild: true }, () =>
            h(Button, { variant: 'ghost', class: 'h-8 w-8 p-0' }, () =>
              h(MoreHorizontal, { class: 'h-4 w-4' })
            )
          ),
          h(DropdownMenuContent, { align: 'end' }, () => [
            h(DropdownMenuItem, {
              onClick: () => router.visit(route('admin.customers.show', customer.id))
            }, () => [
              h(Eye, { class: 'mr-2 h-4 w-4' }),
              'View Details'
            ]),
            h(DropdownMenuSeparator),
            customer.status === 'active' ? h(DropdownMenuItem, {
              onClick: () => updateCustomerStatus(customer.id, 'suspended')
            }, () => [
              h(AlertTriangle, { class: 'mr-2 h-4 w-4' }),
              'Suspend Account'
            ]) : null,
            customer.status === 'suspended' ? h(DropdownMenuItem, {
              onClick: () => updateCustomerStatus(customer.id, 'active')
            }, () => [
              h(CheckCircle, { class: 'mr-2 h-4 w-4' }),
              'Activate Account'
            ]) : null,
            customer.status !== 'banned' ? h(DropdownMenuItem, {
              onClick: () => updateCustomerStatus(customer.id, 'banned'),
              class: 'text-red-600'
            }, () => [
              h(Ban, { class: 'mr-2 h-4 w-4' }),
              'Ban Account'
            ]) : null,
            customer.status === 'banned' ? h(DropdownMenuItem, {
              onClick: () => updateCustomerStatus(customer.id, 'active')
            }, () => [
              h(CheckCircle, { class: 'mr-2 h-4 w-4' }),
              'Unban Account'
            ]) : null,
          ])
        ]
      });
    },
  },
];

// Initialize table
const table = useVueTable({
  data: props.customers,
  columns,
  getCoreRowModel: getCoreRowModel(),
  getPaginationRowModel: getPaginationRowModel(),
  getSortedRowModel: getSortedRowModel(),
  getFilteredRowModel: getFilteredRowModel(),
  onSortingChange: updaterOrValue => valueUpdater(updaterOrValue, sorting),
  onColumnFiltersChange: updaterOrValue => valueUpdater(updaterOrValue, columnFilters),
  onColumnVisibilityChange: updaterOrValue => valueUpdater(updaterOrValue, columnVisibility),
  onRowSelectionChange: updaterOrValue => valueUpdater(updaterOrValue, rowSelection),
  state: {
    get sorting() { return sorting.value },
    get columnFilters() { return columnFilters.value },
    get columnVisibility() { return columnVisibility.value },
    get rowSelection() { return rowSelection.value },
  },
});

// Function to update customer status
function updateCustomerStatus(customerId: number, status: string) {
  router.put(route('admin.customers.update-status', customerId), { status }, {
    onSuccess: () => {
      toast.success(`Customer status updated to ${status}`);
    },
    onError: () => {
      toast.error('Failed to update customer status');
    }
  });
}


const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Admin Dashboard',
    href: route('admin.dashboard'),
  },
  {
    title: 'Customers',
    href: route('admin.customers.index'),
  },
];
</script>

<template>
  <Head title="Manage Customers" />

  <AdminLayout :breadcrumbs="breadcrumbs">
    <template #header>
      <div class="flex items-center justify-between w-full">
        <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
          Manage Customers
        </h2>
        <div class="text-sm text-muted-foreground">
          Total Customers: {{ props.customers.length }}
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
          <div class="p-6 text-gray-900 dark:text-gray-100">
            <p class="mb-4 text-muted-foreground">
              Manage all customer accounts, view their subscription status, and monitor their agent usage.
              Use the search box to filter by name or click on column headers to sort.
            </p>
            <div class="flex items-center py-4">
              <Input
                class="max-w-sm"
                placeholder="Filter by name..."
                :model-value="table.getColumn('name')?.getFilterValue() as string"
                @update:model-value="table.getColumn('name')?.setFilterValue($event)"
              >
                <template #prefix>
                  <Search class="h-4 w-4 text-gray-400" />
                </template>
              </Input>
              <DropdownMenu>
                <DropdownMenuTrigger as-child>
                  <Button variant="outline" class="ml-auto">
                    Columns <ChevronDown class="ml-2 h-4 w-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem
                    v-for="column in table.getAllColumns().filter((column) => column.getCanHide())"
                    :key="column.id"
                    class="capitalize"
                    :selected="column.getIsVisible()"
                    @click="column.toggleVisibility(!column.getIsVisible())"
                  >
                    {{ column.id }}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
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
                      No customers found.
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
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
