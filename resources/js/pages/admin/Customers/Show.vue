<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Table, TableBody, TableCaption, TableCell, TableHead,
  TableHeader, TableRow
} from '@/components/ui/table';
import {
  ArrowLeft, User, CreditCard, FileText,
  CheckCircle, AlertTriangle, Ban, Zap
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';

// Define the Customer type
interface Customer {
  id: number;
  name: string;
  email: string;
  created_at: string;
  status: 'active' | 'suspended' | 'banned';
  agents: Array<{
    id: number;
    name: string;
    type: string;
    status: string;
    interactions: number;
    performance: number;
    last_active: string;
  }>;
  subscription: {
    name: string;
    stripe_status: string;
    stripe_price: string;
    quantity: number;
    trial_ends_at: string | null;
    ends_at: string | null;
  } | null;
  payment_method: {
    brand: string;
    last4: string;
    exp_month: number;
    exp_year: number;
  } | null;
  invoices: Array<{
    id: string;
    total: number;
    date: string;
    status: string;
  }>;
}

// Get customer from props
const props = defineProps<{
  customer: Customer;
}>();

// Function to update customer status
function updateCustomerStatus(status: string) {
  router.put(route('admin.customers.update-status', props.customer.id), { status }, {
    onSuccess: () => {
      toast.success(`Customer status updated to ${status}`);
    },
    onError: () => {
      toast.error('Failed to update customer status');
    }
  });
}

// Function to format currency
function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(amount / 100);
}

// Get card icon based on brand
function getCardIcon(brand: string): string {
  const brands: Record<string, string> = {
    visa: '/card-icons/visa.svg',
    mastercard: '/card-icons/mastercard.svg',
    amex: '/card-icons/amex.svg',
    discover: '/card-icons/discover.svg',
    jcb: '/card-icons/jcb.svg',
    diners: '/card-icons/diners.svg',
    unionpay: '/card-icons/unionpay.svg',
  };

  return brands[brand.toLowerCase()] || '/card-icons/generic.svg';
}

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Admin Dashboard',
    href: '/admin/dashboard',
  },
  {
    title: 'Customers',
    href: '/admin/customers',
  },
  {
    title: 'Customer Details',
    href: '#',
  }
];
</script>

<template>
  <Head :title="`Customer: ${customer.name}`" />

  <AdminLayout :breadcrumbs="breadcrumbs">

      <div class="px-5 py-3 flex flex-col space-y-2 w-full md:flex-row md:items-center md:justify-between md:space-y-0">
        <div class="flex items-center">
          <Button variant="ghost" class="mr-4 hover:bg-primary/10" @click="router.visit(route('admin.customers.index'))">
            <ArrowLeft class="h-4 w-4 mr-2" />
            Back to Customers
          </Button>

        </div>
        <div class="text-sm text-muted-foreground bg-muted/30 px-3 py-1 rounded-md">
          Member since {{ customer.created_at.split('(')[0] }}
        </div>
      </div>


    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-3">
          <!-- Customer Info Card -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center">
                <User class="h-5 w-5 mr-2" />
                Customer Information
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div class="space-y-4">
                <div>
                  <h3 class="text-sm font-medium text-muted-foreground">Name</h3>
                  <p>{{ customer.name }}</p>
                </div>
                <div>
                  <h3 class="text-sm font-medium text-muted-foreground">Email</h3>
                  <p>{{ customer.email }}</p>
                </div>
                <div>
                  <h3 class="text-sm font-medium text-muted-foreground">Joined</h3>
                  <p>{{ customer.created_at }}</p>
                </div>
                <div>
                  <h3 class="text-sm font-medium text-muted-foreground">Status</h3>
                  <Badge :class="{
                    'bg-green-500': customer.status === 'active',
                    'bg-amber-500': customer.status === 'suspended',
                    'bg-red-500': customer.status === 'banned',
                  }">
                    {{ customer.status.charAt(0).toUpperCase() + customer.status.slice(1) }}
                  </Badge>
                </div>
              </div>
            </CardContent>
            <CardFooter class="flex flex-col space-y-2">
              <div v-if="customer.status === 'active'" class="flex space-x-2 w-full">
                <Button variant="outline" class="flex-1" @click="updateCustomerStatus('suspended')">
                  <AlertTriangle class="h-4 w-4 mr-2" />
                  Suspend
                </Button>
                <Button variant="destructive" class="flex-1" @click="updateCustomerStatus('banned')">
                  <Ban class="h-4 w-4 mr-2" />
                  Ban
                </Button>
              </div>
              <div v-else-if="customer.status === 'suspended'" class="flex space-x-2 w-full">
                <Button variant="outline" class="flex-1" @click="updateCustomerStatus('active')">
                  <CheckCircle class="h-4 w-4 mr-2" />
                  Activate
                </Button>
                <Button variant="destructive" class="flex-1" @click="updateCustomerStatus('banned')">
                  <Ban class="h-4 w-4 mr-2" />
                  Ban
                </Button>
              </div>
              <div v-else class="w-full">
                <Button variant="outline" class="w-full" @click="updateCustomerStatus('active')">
                  <CheckCircle class="h-4 w-4 mr-2" />
                  Unban Account
                </Button>
              </div>
            </CardFooter>
          </Card>

          <!-- Subscription Info Card -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center">
                <Zap class="h-5 w-5 mr-2" />
                Subscription
              </CardTitle>
            </CardHeader>
            <CardContent>
              <Deferred data="subscription">
                <template #fallback>
                  <div class="space-y-4 animate-pulse">
                    <div>
                      <h3 class="text-sm font-medium text-muted-foreground">Plan</h3>
                      <div class="h-5 w-32 bg-muted rounded mt-1"></div>
                    </div>
                    <div>
                      <h3 class="text-sm font-medium text-muted-foreground">Status</h3>
                      <div class="h-6 w-24 bg-muted rounded mt-1"></div>
                    </div>
                    <div>
                      <h3 class="text-sm font-medium text-muted-foreground">Trial Ends</h3>
                      <div class="h-5 w-48 bg-muted rounded mt-1"></div>
                    </div>
                  </div>
                </template>

                <div v-if="subscription" class="space-y-4">
                  <div>
                    <h3 class="text-sm font-medium text-muted-foreground">Plan</h3>
                    <p>{{ subscription.name }}</p>
                  </div>
                  <div>
                    <h3 class="text-sm font-medium text-muted-foreground">Status</h3>
                    <Badge variant="outline" :class="{
                      'border-green-500 text-green-500': subscription.stripe_status === 'active',
                      'border-amber-500 text-amber-500': subscription.stripe_status === 'trialing',
                      'border-red-500 text-red-500': subscription.stripe_status === 'canceled' || subscription.stripe_status === 'incomplete_expired',
                      'border-blue-500 text-blue-500': subscription.stripe_status === 'incomplete',
                      'border-purple-500 text-purple-500': subscription.stripe_status === 'past_due',
                    }">
                      {{ subscription.stripe_status.charAt(0).toUpperCase() + subscription.stripe_status.slice(1) }}
                    </Badge>
                  </div>
                  <div v-if="subscription.trial_ends_at">
                    <h3 class="text-sm font-medium text-muted-foreground">Trial Ends</h3>
                    <p>{{ subscription.trial_ends_at }}</p>
                  </div>
                  <div v-if="subscription.ends_at">
                    <h3 class="text-sm font-medium text-muted-foreground">Subscription Ends</h3>
                    <p>{{ subscription.ends_at }}</p>
                  </div>
                </div>
                <div v-else class="py-8 text-center">
                  <p class="text-muted-foreground">No active subscription</p>
                </div>
              </Deferred>
            </CardContent>
          </Card>

          <!-- Payment Method Card -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center">
                <CreditCard class="h-5 w-5 mr-2" />
                Payment Method
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div v-if="customer.payment_method" class="space-y-4">
                <div class="flex items-center space-x-3">
                  <img
                    :src="getCardIcon(customer.payment_method.brand)"
                    :alt="customer.payment_method.brand"
                    class="h-8"
                  />
                  <div>
                    <p class="font-medium">•••• {{ customer.payment_method.last4 }}</p>
                    <p class="text-sm text-muted-foreground">
                      Expires {{ customer.payment_method.exp_month }}/{{ customer.payment_method.exp_year }}
                    </p>
                  </div>
                </div>
              </div>
              <div v-else class="py-8 text-center">
                <p class="text-muted-foreground">No payment method on file</p>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Tabs for Agents and Invoices -->
        <Tabs default-value="agents" class="w-full">
          <TabsList class="grid w-full grid-cols-2">
            <TabsTrigger value="agents">
              <Deferred data="agents">
                <template #fallback>Agents (Loading...)</template>
                Agents ({{ agents ? agents.length : 0 }})
              </Deferred>
            </TabsTrigger>
            <TabsTrigger value="invoices">
              <Deferred data="invoices">
                <template #fallback>Invoices (Loading...)</template>
                Invoices ({{ invoices ? invoices.length : 0 }})
              </Deferred>
            </TabsTrigger>
          </TabsList>

          <TabsContent value="agents">
            <Card>
              <CardHeader>
                <CardTitle>Customer Agents</CardTitle>
                <CardDescription>
                  All agents created by this customer. Monitor usage, performance metrics, and status.
                </CardDescription>
              </CardHeader>
              <CardContent>
                <Deferred data="agents">
                  <template #fallback>
                    <div class="animate-pulse">
                      <Table>
                        <TableHeader>
                          <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Type</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Interactions</TableHead>
                            <TableHead>Performance</TableHead>
                            <TableHead>Last Active</TableHead>
                          </TableRow>
                        </TableHeader>
                        <TableBody>
                          <TableRow v-for="i in 3" :key="i">
                            <TableCell><div class="h-5 w-24 bg-muted rounded"></div></TableCell>
                            <TableCell><div class="h-5 w-16 bg-muted rounded"></div></TableCell>
                            <TableCell><div class="h-6 w-20 bg-muted rounded"></div></TableCell>
                            <TableCell><div class="h-5 w-8 bg-muted rounded"></div></TableCell>
                            <TableCell><div class="h-5 w-12 bg-muted rounded"></div></TableCell>
                            <TableCell><div class="h-5 w-32 bg-muted rounded"></div></TableCell>
                          </TableRow>
                        </TableBody>
                      </Table>
                    </div>
                  </template>

                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Name</TableHead>
                        <TableHead>Type</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Interactions</TableHead>
                        <TableHead>Performance</TableHead>
                        <TableHead>Last Active</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      <TableRow v-for="agent in agents" :key="agent.id">
                        <TableCell class="font-medium">{{ agent.name }}</TableCell>
                        <TableCell>{{ agent.type }}</TableCell>
                        <TableCell>
                          <Badge :class="{
                            'bg-green-500': agent.status === 'active',
                            'bg-amber-500': agent.status === 'training',
                            'bg-gray-500': agent.status === 'paused',
                          }">
                            {{ agent.status }}
                          </Badge>
                        </TableCell>
                        <TableCell>{{ agent.interactions }}</TableCell>
                        <TableCell>{{ agent.performance }}%</TableCell>
                        <TableCell>{{ agent.last_active }}</TableCell>
                      </TableRow>
                      <TableRow v-if="!agents || agents.length === 0">
                        <TableCell colspan="6" class="text-center py-4">
                          No agents found for this customer
                        </TableCell>
                      </TableRow>
                    </TableBody>
                  </Table>
                </Deferred>
              </CardContent>
            </Card>
          </TabsContent>

          <TabsContent value="invoices">
            <Card>
              <CardHeader>
                <CardTitle>Invoices</CardTitle>
                <CardDescription>
                  Customer billing history with payment status and transaction details. All amounts are in USD.
                </CardDescription>
              </CardHeader>
              <CardContent>
                <Deferred data="invoices">
                  <template #fallback>
                    <div class="animate-pulse">
                      <Table>
                        <TableHeader>
                          <TableRow>
                            <TableHead>Invoice ID</TableHead>
                            <TableHead>Date</TableHead>
                            <TableHead>Amount</TableHead>
                            <TableHead>Status</TableHead>
                          </TableRow>
                        </TableHeader>
                        <TableBody>
                          <TableRow v-for="i in 3" :key="i">
                            <TableCell><div class="h-5 w-32 bg-muted rounded"></div></TableCell>
                            <TableCell><div class="h-5 w-40 bg-muted rounded"></div></TableCell>
                            <TableCell><div class="h-5 w-20 bg-muted rounded"></div></TableCell>
                            <TableCell><div class="h-6 w-16 bg-muted rounded"></div></TableCell>
                          </TableRow>
                        </TableBody>
                      </Table>
                    </div>
                  </template>

                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Invoice ID</TableHead>
                        <TableHead>Date</TableHead>
                        <TableHead>Amount</TableHead>
                        <TableHead>Status</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      <TableRow v-for="invoice in invoices" :key="invoice.id">
                        <TableCell class="font-medium">{{ invoice.id }}</TableCell>
                        <TableCell>{{ invoice.date }}</TableCell>
                        <TableCell>{{ formatCurrency(invoice.total) }}</TableCell>
                        <TableCell>
                          <Badge :variant="invoice.status === 'paid' ? 'default' : 'outline'">
                            {{ invoice.status }}
                          </Badge>
                        </TableCell>
                      </TableRow>
                      <TableRow v-if="!invoices || invoices.length === 0">
                        <TableCell colspan="4" class="text-center py-4">
                          No invoices found for this customer
                        </TableCell>
                      </TableRow>
                    </TableBody>
                  </Table>
                </Deferred>
              </CardContent>
            </Card>
          </TabsContent>
        </Tabs>
      </div>
    </div>
  </AdminLayout>
</template>
