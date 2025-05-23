<script setup lang="ts">
import { Head, router, Deferred } from '@inertiajs/vue3';
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
  ArrowLeft, User, CreditCard, FileText, FileDown,
  CheckCircle, AlertTriangle, Ban, Zap, RefreshCw, Eye
} from 'lucide-vue-next';
import { toast } from 'vue-sonner';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader,
  DialogTitle, DialogTrigger
} from '@/components/ui/dialog';
import { ref } from 'vue';
import CardBrandIcon from '@/components/CardBrandIcon.vue';

// Define the Subscription type
interface Subscription {
  id: number;
  user: {
    id: number;
    name: string;
    email: string;
    created_at: string;
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

// Define the PaymentMethod type
interface PaymentMethod {
  brand: string;
  last4: string;
  exp_month: number;
  exp_year: number;
}

// Define the Invoice type
interface Invoice {
  id: string;
  total: number;
  date: string;
  status: string;
}

// Get props
const props = defineProps<{
  subscription: Subscription;
  paymentMethod: PaymentMethod | null;
  invoices: Invoice[];
}>();

// Dialog state for cancellation
const cancelDialogOpen = ref(false);
const cancelType = ref('end_of_period');
const isCancelling = ref(false);
const isGeneratingInvoice = ref<Record<string, boolean>>({});
const isGeneratingBatch = ref(false);
const selectedInvoices = ref<string[]>([]);

// Function to format currency
function formatCurrency(amount: number | string | null | undefined): string {
  // If amount is already a formatted string with a currency symbol (e.g., '$20')
  if (typeof amount === 'string' && amount.startsWith('$')) {
    return amount;
  }

  // Check if amount is a valid number or can be converted to one
  if (amount === null || amount === undefined) {
    return '$0.00';
  }

  try {
    // If amount is a string that doesn't start with '$', try to convert it to a number
    let numericAmount: number;
    if (typeof amount === 'string') {
      // Remove any commas and convert to number
      numericAmount = parseFloat(amount.replace(/,/g, ''));
    } else {
      numericAmount = amount;
    }

    // Check if conversion resulted in a valid number
    if (isNaN(numericAmount)) {
      return '$0.00';
    }

    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency: 'USD',
    }).format(numericAmount / 100);
  } catch (error) {
    console.error('Error formatting currency:', error, 'Amount:', amount);
    return '$0.00';
  }
}

// Function to cancel subscription
function cancelSubscription() {
  isCancelling.value = true;

  router.post(route('admin.subscriptions.cancel', props.subscription.id), {
    cancel_type: cancelType.value
  }, {
    onSuccess: () => {
      toast.success('Subscription cancelled successfully');
      cancelDialogOpen.value = false;
      isCancelling.value = false;
    },
    onError: (errors: any) => {
      toast.error('Failed to cancel subscription', {
        description: errors.message || 'There was an error cancelling the subscription.'
      });
      isCancelling.value = false;
    }
  });
}

// Function to resume subscription
function resumeSubscription() {
  router.post(route('admin.subscriptions.resume', props.subscription.id), {}, {
    onSuccess: () => {
      toast.success('Subscription resumed successfully');
    },
    onError: (errors: any) => {
      toast.error('Failed to resume subscription', {
        description: errors.message || 'There was an error resuming the subscription.'
      });
    }
  });
}

// Function to sync subscription with Stripe
function syncSubscription() {
  router.post(route('admin.subscriptions.sync', props.subscription.id), {}, {
    onSuccess: () => {
      toast.success('Subscription synced with Stripe');
    },
    onError: (errors: any) => {
      toast.error('Failed to sync subscription with Stripe', {
        description: errors.message || 'There was an error syncing the subscription.'
      });
    }
  });
}

// Function to generate invoice PDF
function generateInvoicePdf(invoiceId: string) {
  // Set the loading state for this invoice
  isGeneratingInvoice.value[invoiceId] = true;

  // Navigate to the admin invoice generation route
  router.visit(route('admin.invoice.generate', [props.subscription.user.id, invoiceId]), {
    preserveState: true,
    onSuccess: () => {
      toast.success('Invoice generation started', {
        description: 'The invoice is being generated. You will be notified when it is ready for download.'
      });
      isGeneratingInvoice.value[invoiceId] = false;
    },
    onError: (errors: any) => {
      toast.error('Failed to generate invoice', {
        description: errors.message || 'There was an error generating the invoice.'
      });
      isGeneratingInvoice.value[invoiceId] = false;
    }
  });
}

// Function to toggle invoice selection for batch processing
function toggleInvoiceSelection(invoiceId: string) {
  const index = selectedInvoices.value.indexOf(invoiceId);
  if (index === -1) {
    selectedInvoices.value.push(invoiceId);
  } else {
    selectedInvoices.value.splice(index, 1);
  }
}

// Function to select all invoices
function selectAllInvoices() {
  if (selectedInvoices.value.length === props.invoices.length) {
    // If all are selected, deselect all
    selectedInvoices.value = [];
  } else {
    // Otherwise, select all
    selectedInvoices.value = props.invoices.map((invoice: Invoice) => invoice.id);
  }
}

// Function to generate batch of invoices
function generateBatchInvoices() {
  if (selectedInvoices.value.length === 0) {
    toast.error('No invoices selected', {
      description: 'Please select at least one invoice to generate.'
    });
    return;
  }

  isGeneratingBatch.value = true;

  router.post(route('admin.invoice.generate-batch', props.subscription.user.id), {
    invoice_ids: selectedInvoices.value
  }, {
    preserveState: true,
    onSuccess: () => {
      toast.success('Batch invoice generation started', {
        description: `Generating ${selectedInvoices.value.length} invoices. You will be notified of progress and completion.`
      });
      isGeneratingBatch.value = false;
      // Clear selections after successful submission
      selectedInvoices.value = [];
    },
    onError: (errors: any) => {
      toast.error('Failed to generate batch invoices', {
        description: errors.message || 'There was an error generating the invoices.'
      });
      isGeneratingBatch.value = false;
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
  {
    title: 'Subscription Details',
    href: route('admin.subscriptions.show', props.subscription.id),
  },
];
</script>

<template>
  <Head title="Subscription Details" />

  <AdminLayout :breadcrumbs="breadcrumbs">
    <template #header>
      <div class="flex flex-col space-y-2 w-full md:flex-row md:items-center md:justify-between md:space-y-0">
        <div class="flex items-center">
          <Button variant="ghost" class="mr-4 hover:bg-primary/10" @click="router.visit(route('admin.subscriptions.index'))">
            <ArrowLeft class="h-4 w-4 mr-2" />
            Back to Subscriptions
          </Button>
          <h2 class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
            Subscription Details: {{ subscription.name }}
          </h2>
        </div>
        <div class="text-sm text-muted-foreground bg-muted/30 px-3 py-1 rounded-md">
          Created {{ subscription.created_at.split('(')[0] }}
        </div>
      </div>
    </template>

    <div class="py-12">
      <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
          <div class="p-6 text-gray-900 dark:text-gray-100">
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
                      <p>{{ subscription.user.name }}</p>
                    </div>
                    <div>
                      <h3 class="text-sm font-medium text-muted-foreground">Email</h3>
                      <p>{{ subscription.user.email }}</p>
                    </div>
                    <div>
                      <h3 class="text-sm font-medium text-muted-foreground">Joined</h3>
                      <p>{{ subscription.user.created_at }}</p>
                    </div>
                  </div>
                </CardContent>
                <CardFooter>
                  <Button variant="outline" class="w-full" @click="router.visit(route('admin.customers.show', subscription.user.id))">
                    <Eye class="h-4 w-4 mr-2" />
                    View Customer
                  </Button>
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
                  <div class="space-y-4">
                    <div>
                      <h3 class="text-sm font-medium text-muted-foreground">Plan</h3>
                      <p>{{ subscription.name || 'Unknown Plan' }}</p>
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
                    <div>
                      <h3 class="text-sm font-medium text-muted-foreground">Stripe ID</h3>
                      <p class="text-xs font-mono">{{ subscription.stripe_id }}</p>
                    </div>
                  </div>
                </CardContent>
                <CardFooter class="flex flex-col space-y-2">
                  <div class="flex space-x-2 w-full">
                    <Button variant="outline" class="flex-1" @click="syncSubscription">
                      <RefreshCw class="h-4 w-4 mr-2" />
                      Sync with Stripe
                    </Button>
                  </div>
                  <div v-if="subscription.stripe_status === 'active' || subscription.stripe_status === 'trialing'" class="w-full">
                    <Button variant="destructive" class="w-full" @click="cancelDialogOpen = true">
                      <Ban class="h-4 w-4 mr-2" />
                      Cancel Subscription
                    </Button>
                  </div>
                  <div v-if="subscription.on_grace_period" class="w-full">
                    <Button variant="outline" class="w-full" @click="resumeSubscription">
                      <CheckCircle class="h-4 w-4 mr-2" />
                      Resume Subscription
                    </Button>
                  </div>
                </CardFooter>
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
                  <div v-if="paymentMethod" class="space-y-4">
                    <div class="flex items-center space-x-3">
                      <CardBrandIcon :brand="paymentMethod.brand" />
                      <div>
                        <p class="font-medium">•••• {{ paymentMethod.last4 }}</p>
                        <p class="text-sm text-muted-foreground">
                          Expires {{ paymentMethod.exp_month }}/{{ paymentMethod.exp_year }}
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

            <!-- Invoices Table -->
            <Card>
              <CardHeader>
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                  <div>
                    <CardTitle>Invoices</CardTitle>
                    <CardDescription>
                      Customer billing history with payment status and transaction details. All amounts are in USD.
                    </CardDescription>
                  </div>
                  <div class="flex items-center gap-2">
                    <Button
                      variant="outline"
                      size="sm"
                      @click="selectAllInvoices"
                      v-if="invoices.length > 0"
                    >
                      {{ selectedInvoices.length === invoices.length ? 'Deselect All' : 'Select All' }}
                    </Button>
                    <Button
                      variant="default"
                      size="sm"
                      @click="generateBatchInvoices"
                      :disabled="isGeneratingBatch || selectedInvoices.length === 0"
                    >
                      <FileDown class="h-4 w-4 mr-1" />
                      <span v-if="isGeneratingBatch">Processing...</span>
                      <span v-else>Generate Selected</span>
                    </Button>
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead class="w-[50px]">
                        <span class="sr-only">Select</span>
                      </TableHead>
                      <TableHead>Invoice ID</TableHead>
                      <TableHead>Date</TableHead>
                      <TableHead>Amount</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>Actions</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow v-for="invoice in invoices" :key="invoice.id">
                      <TableCell>
                        <input
                          type="checkbox"
                          :checked="selectedInvoices.includes(invoice.id)"
                          @change="toggleInvoiceSelection(invoice.id)"
                          class="rounded"
                        />
                      </TableCell>
                      <TableCell class="font-medium">{{ invoice.id }}</TableCell>
                      <TableCell>{{ invoice.date }}</TableCell>
                      <TableCell>{{ formatCurrency(invoice.total) }}</TableCell>
                      <TableCell>
                        <Badge :variant="invoice.status === 'paid' ? 'default' : 'outline'">
                          {{ invoice.status }}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <div class="flex space-x-2">
                          <Button
                            variant="outline"
                            size="sm"
                            @click="generateInvoicePdf(invoice.id)"
                            :disabled="isGeneratingInvoice[invoice.id]"
                          >
                            <FileDown class="h-4 w-4 mr-1" />
                            <span v-if="isGeneratingInvoice[invoice.id]">Processing...</span>
                            <span v-else>Generate PDF</span>
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                    <TableRow v-if="invoices.length === 0">
                      <TableCell colspan="6" class="text-center py-4">
                        No invoices found for this subscription
                      </TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </div>

    <!-- Cancel Subscription Dialog -->
    <Dialog v-model:open="cancelDialogOpen">
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Cancel Subscription</DialogTitle>
          <DialogDescription>
            Are you sure you want to cancel this subscription? This action cannot be undone.
          </DialogDescription>
        </DialogHeader>
        <div class="space-y-4 py-4">
          <div class="space-y-2">
            <h4 class="text-sm font-medium">Cancellation Type</h4>
            <div class="flex flex-col space-y-2">
              <label class="flex items-center space-x-2">
                <input type="radio" v-model="cancelType" value="end_of_period" class="rounded-full" />
                <span>At End of Billing Period</span>
              </label>
              <label class="flex items-center space-x-2">
                <input type="radio" v-model="cancelType" value="immediately" class="rounded-full" />
                <span>Immediately</span>
              </label>
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="cancelDialogOpen = false">Cancel</Button>
          <Button variant="destructive" @click="cancelSubscription" :disabled="isCancelling">
            <span v-if="isCancelling">Cancelling...</span>
            <span v-else>Confirm Cancellation</span>
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AdminLayout>
</template>
