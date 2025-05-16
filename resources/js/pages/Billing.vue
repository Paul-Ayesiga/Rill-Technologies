<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import { toast } from 'vue-sonner';

// Declare Stripe variables
declare global {
  interface Window {
    Stripe?: any;
  }
}

// Import shadcn-vue components
import {
  Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader,
  DialogTitle, DialogTrigger
} from '@/components/ui/dialog';
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator,
  DropdownMenuTrigger
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

// Import Lucide icons
import {
  CreditCard, Check, X, CircleAlert, Shield, FileDown, RefreshCcw, Zap, Ban, ChevronDown
} from 'lucide-vue-next';
import CardBrandIcon from '@/components/CardBrandIcon.vue';

// Explicitly declare components to fix TypeScript "unused import" warnings
// This object is intentionally unused but prevents TypeScript from flagging the imports as unused
// eslint-disable-next-line @typescript-eslint/no-unused-vars
const components = {
  Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle,
  Button, Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader,
  DialogTitle, DialogTrigger, Input, Label, Badge, Skeleton,
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger,
  Tabs, TabsContent, TabsList, TabsTrigger,
  CreditCard, Check, X, AlertCircle, Shield, FileDown, RefreshCw, Zap, Ban, ChevronDown,
  CardBrandIcon
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Billing',
        href: '/billing',
    },
];

// State for loading
const isLoading = ref(true);
const isSyncing = ref(false);
const isGeneratingInvoice = ref<Record<string, boolean>>({});

// State for invoice selection
const selectedInvoices = ref<string[]>([]);
const selectAllInvoices = ref(false);
const selectAllPaidInvoices = ref(false);
const selectAllPendingInvoices = ref(false);
const isBulkGenerating = ref(false);

// State for batch progress tracking (kept for future use but not displayed)
const batchProgress = ref<{
  batchId: string | null;
  totalInvoices: number;
  processedInvoices: number;
  progress: number;
  isComplete: boolean;
}>({
  batchId: null,
  totalInvoices: 0,
  processedInvoices: 0,
  progress: 0,
  isComplete: false
});

// Get subscription data from props
const props = defineProps<{
  subscription?: any;
  plans?: any[];
  connectionError?: boolean;
  flash?: {
    success?: string;
    error?: string;
    warning?: string;
  };
}>();

// Use the subscription directly from props
// We'll reload the page when needed to update the data

// Dialog states for subscription management
const showChangePlanDialog = ref(false);
const showCancelDialog = ref(false);
const showUpdatePaymentDialog = ref(false);
const showPlanSelection = ref(false); // Flag to show plan selection UI in the dialog
const selectedPlan = ref(props.subscription?.stripe_price || route().params.price_id || null);
const cancelReason = ref('');

// Create Inertia forms for subscription actions
const subscriptionForm = useForm({
  plan: route().params.price_id || '',
  payment_method: ''
});

// If a price_id was passed in the URL, open the change plan dialog
if (route().params.price_id && !props.subscription) {
  showChangePlanDialog.value = true;
}

const updatePaymentForm = useForm({
  payment_method: ''
});

const cancelForm = useForm({
  reason: '',
  cancel_type: 'end_of_period'
});

// Use the plans from props without fallback
const availablePlans = computed(() => props.plans || []);

// Check if subscription is cancelled but not on grace period
const isCancelledWithoutGracePeriod = computed(() => {
  if (!props.subscription) return false;
  return props.subscription.stripe_status === 'canceled' && !props.subscription.on_grace_period;
});

// Stripe Elements variables
const stripe = ref<any>(null);
const cardElement = ref<any>(null);
const cardErrors = ref<string>('');
const stripeLoading = ref(true);
const cardComplete = ref(false);

// Initialize Stripe Elements using our utility functions
const initializeStripeElements = () => {
  try {
    // Only initialize if the dialog is open
    if (!showChangePlanDialog.value) return;

    // Check if Stripe is already loaded
    if (window.Stripe) {
      stripeLoading.value = false;

      // Initialize Stripe with your publishable key from environment variables
      const stripeKey = import.meta.env.VITE_STRIPE_KEY;

      // Use our utility function to initialize Stripe
      initializeStripe(stripeKey, (errorMessage) => {
        cardErrors.value = errorMessage;
      })
        .then((stripeInstance) => {
          stripe.value = stripeInstance;

          // Wait for the DOM to be updated
          setTimeout(() => {
            try {
              // Create Stripe Elements
              const elements = stripe.value.elements();

              // Create the card element
              cardElement.value = elements.create('card', {
                style: {
                  base: {
                    color: '#32325d',
                    fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                    fontSmoothing: 'antialiased',
                    fontSize: '16px',
                    '::placeholder': {
                      color: '#aab7c4'
                    }
                  },
                  invalid: {
                    color: '#fa755a',
                    iconColor: '#fa755a'
                  }
                }
              });

              // Mount the card element to the DOM
              const cardElementContainer = document.getElementById('card-element');
              if (cardElementContainer) {
                try {
                  cardElement.value.mount('#card-element');

                  // Listen for changes in the card element
                  cardElement.value.on('change', (event: any) => {
                    cardComplete.value = event.complete;
                    cardErrors.value = event.error ? event.error.message : '';
                  });
                } catch (mountError) {
                  console.error('Error mounting card element:', mountError);
                  cardErrors.value = 'Unable to display payment form. Please try again later.';
                }
              } else {
                console.warn('Card element container not found');
                cardErrors.value = 'Payment form could not be displayed. Please try again.';
              }
            } catch (error) {
              console.error('Error initializing Stripe elements:', error);
              cardErrors.value = 'Error initializing payment form. Please check your internet connection and try again.';
            }
          }, 100);
        })
        .catch((error) => {
          console.error('Error initializing Stripe:', error);
          cardErrors.value = 'Error initializing payment system. Please check your internet connection and try again.';
        });
    } else {
      // If Stripe.js is not loaded yet, try again in 100ms
      setTimeout(initializeStripeElements, 100);
    }
  } catch (error) {
    console.error('Unexpected error in initializeStripe:', error);
    cardErrors.value = 'An unexpected error occurred. Please check your internet connection and try again.';
  }
};

// Import Stripe connectivity utilities
import { loadStripeScript, initializeStripe, showStripeConnectivityError, isConnectivityError, getStripeErrorMessage, isOnline } from '@/utils/stripe-connectivity';

// Load Stripe.js script with error handling
const loadStripeScriptWithErrorHandling = () => {
  loadStripeScript((errorMessage) => {
    cardErrors.value = errorMessage;
  }).catch((error) => {
    console.error('Failed to load Stripe script:', error);
  });
};

// Watch for dialog open/close to initialize/cleanup Stripe
watch(() => showChangePlanDialog.value, (isOpen: boolean) => {
  try {
    if (isOpen) {
      // Load Stripe.js if not already loaded
      if (typeof window !== 'undefined' && !window.Stripe) {
        loadStripeScriptWithErrorHandling();
      }
      // Initialize Stripe Elements
      initializeStripeElements();

      // If no plan is selected, show the plan selection UI
      if (!selectedPlan.value) {
        showPlanSelection.value = true;
      } else {
        showPlanSelection.value = false;
      }
    } else {
      // Cleanup when dialog is closed
      if (cardElement.value) {
        try {
          cardElement.value.unmount();
          cardElement.value = null;
        } catch (error) {
          console.error('Error unmounting card element:', error);
        }
      }

      // Reset the plan selection UI state
      showPlanSelection.value = false;
    }
  } catch (error) {
    console.error('Error in dialog watch handler:', error);
  }
});

// Watch for update payment dialog open/close
watch(() => showUpdatePaymentDialog.value, (isOpen: boolean) => {
  try {
    if (isOpen) {
      // Load Stripe.js if not already loaded
      if (typeof window !== 'undefined' && !window.Stripe) {
        loadStripeScriptWithErrorHandling();
      }

      // Wait for Stripe to be loaded
      const checkStripe = () => {
        if (window.Stripe) {
          stripeLoading.value = false;

          // Initialize Stripe with your publishable key
          const stripeKey = import.meta.env.VITE_STRIPE_KEY;

          // Use our utility function to initialize Stripe
          initializeStripe(stripeKey, (errorMessage) => {
            cardErrors.value = errorMessage;
          })
            .then((stripeInstance) => {
              stripe.value = stripeInstance;

              // Wait for the DOM to be updated
              setTimeout(() => {
                try {
                  const elements = stripe.value.elements();

                  // Create the card element
                  cardElement.value = elements.create('card', {
                    style: {
                      base: {
                        color: '#32325d',
                        fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                        fontSmoothing: 'antialiased',
                        fontSize: '16px',
                        '::placeholder': {
                          color: '#aab7c4'
                        }
                      },
                      invalid: {
                        color: '#fa755a',
                        iconColor: '#fa755a'
                      }
                    }
                  });

                  // Mount the card element to the DOM
                  const updateCardElementContainer = document.getElementById('update-card-element');
                  if (updateCardElementContainer) {
                    cardElement.value.mount('#update-card-element');

                    // Listen for changes in the card element
                    cardElement.value.on('change', (event: any) => {
                      cardComplete.value = event.complete;
                      cardErrors.value = event.error ? event.error.message : '';
                    });
                  } else {
                    console.warn('Update card element container not found');
                    cardErrors.value = 'Payment form could not be displayed. Please try again.';
                  }
                } catch (error) {
                  console.error('Error initializing Stripe elements for update payment:', error);
                  cardErrors.value = 'Error initializing payment form. Please check your internet connection and try again.';
                }
              }, 100);
            })
            .catch((error) => {
              console.error('Error initializing Stripe for update payment:', error);
              cardErrors.value = 'Error initializing payment system. Please check your internet connection and try again.';
            });
        } else {
          // If Stripe.js is not loaded yet, try again in 100ms
          setTimeout(checkStripe, 100);
        }
      };

      checkStripe();
    } else {
      // Cleanup when dialog is closed
      if (cardElement.value) {
        try {
          cardElement.value.unmount();
          cardElement.value = null;
        } catch (error) {
          console.error('Error unmounting card element:', error);
        }
      }
    }
  } catch (error) {
    console.error('Error in update payment dialog watch handler:', error);
    cardErrors.value = 'An unexpected error occurred. Please try again later.';
  }
});

// Helper function to safely get plan name
const getPlanName = (planId: string | null): string => {
  if (!planId) return 'Selected';

  try {
    // In the script section, we need to use .value for computed properties
    const plans = availablePlans.value;
    if (!plans || !Array.isArray(plans) || plans.length === 0) {
      // If no plans are available, show a generic name
      return 'Plan';
    }

    const plan = plans.find((p: any) => p.id === planId);
    return plan?.name || 'Selected Plan';
  } catch (error) {
    console.error('Error getting plan name:', error);
    return 'Selected Plan';
  }
};

// Helper function to safely get plan price
const getPlanPrice = (planId: string | null): string => {
  if (!planId) return '0.00';

  try {
    const plans = availablePlans.value;
    if (!plans || !Array.isArray(plans) || plans.length === 0) {
      return '0.00';
    }

    const plan = plans.find((p: any) => p.id === planId);
    return plan?.price || '0.00';
  } catch (error) {
    console.error('Error getting plan price:', error);
    return '0.00';
  }
};

// Helper function to safely get plan interval
const getPlanInterval = (planId: string | null): string => {
  if (!planId) return 'month';

  try {
    const plans = availablePlans.value;
    if (!plans || !Array.isArray(plans) || plans.length === 0) {
      return 'month';
    }

    const plan = plans.find((p: any) => p.id === planId);
    return plan?.interval || 'month';
  } catch (error) {
    console.error('Error getting plan interval:', error);
    return 'month';
  }
};

// Helper function to check if a plan is an enterprise plan
const isEnterprisePlan = (planId: string | null): boolean => {
  if (!planId) return false;

  try {
    const plans = availablePlans.value;
    if (!plans || !Array.isArray(plans) || plans.length === 0) {
      return false;
    }

    const plan = plans.find((p: any) => p.id === planId);
    if (!plan) return false;

    // Check if the plan name contains "enterprise" (case-insensitive)
    return plan.name.toLowerCase().includes('enterprise');
  } catch (error) {
    console.error('Error checking if plan is enterprise:', error);
    return false;
  }
};

// Function to show enterprise plan toast notification
const showEnterpriseToast = () => {
  toast('Enterprise Plan', {
    description: "Please contact our sales team for Enterprise plans. We'll create a custom solution for your business needs.",
    duration: 5000,
    action: {
      label: 'Contact Sales',
      onClick: () => window.location.href = 'mailto:sales@example.com'
    }
  });
};

// Helper function to safely format dates
const formatDate = (dateString: string | null): string => {
  if (!dateString) return 'N/A';

  try {
    // Try to parse the ISO date string
    const date = new Date(dateString);

    // Check if the date is valid
    if (isNaN(date.getTime())) {
      return 'N/A';
    }

    // Format the date using toLocaleDateString with month names
    const options: Intl.DateTimeFormatOptions = {
      year: 'numeric',
      month: 'long',  // Use 'long' for full month name
      day: 'numeric'
    };

    return date.toLocaleDateString(undefined, options);
  } catch (error) {
    console.error('Error formatting date:', error, dateString);
    return 'N/A';
  }
};

// Simulate loading and check for flash messages
onMounted(() => {
  setTimeout(() => {
    isLoading.value = false;

    // Check for flash messages
    if (props.flash?.success) {
      toast.success('Success', {
        description: props.flash.success
      });
    }

    if (props.flash?.error) {
      toast.error('Error', {
        description: props.flash.error
      });
    }

    if (props.flash?.warning) {
      toast.warning('Warning', {
        description: props.flash.warning,
        duration: 10000 // Show for 10 seconds
      });
    }

    // Check for connection error
    if (props.connectionError) {
      toast.error('Connection Error', {
        description: 'Unable to connect to payment service. Some subscription details may be limited until connection is restored.',
        duration: 10000 // Show for 10 seconds
      });
    }
  }, 1000);
});

// Function to sync subscription status with Stripe
const syncSubscription = () => {
  isSyncing.value = true;

  // Use Inertia to post to the sync route
  router.post(route('subscription.sync'), {}, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Subscription synced', {
        description: 'Your subscription status has been updated from Stripe.'
      });

      // Reload the page data using Inertia
      router.reload({ only: ['subscription'] });

      isSyncing.value = false;
    },
    onError: (errors: any) => {
      toast.error('Error syncing subscription', {
        description: errors.message || 'There was an error syncing your subscription with Stripe.'
      });
      isSyncing.value = false;
    }
  });
};

// Function to resume a canceled subscription
const resumeSubscription = () => {
  router.post(route('subscription.resume'), {}, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success('Subscription resumed', {
        description: 'Your subscription has been successfully resumed.'
      });

      // Reload the page data using Inertia
      router.reload({ only: ['subscription'] });
    },
    onError: (errors: any) => {
      toast.error('Failed to resume subscription', {
        description: errors.message || 'There was an error resuming your subscription.'
      });
    }
  });
};

// Function to generate invoice PDF
const generateInvoicePdf = (invoiceId: string) => {
  // Set the loading state for this invoice
  isGeneratingInvoice.value[invoiceId] = true;

  // Navigate to the invoice generation route
  router.visit(route('invoice.generate', invoiceId), {
    preserveState: true,
    onSuccess: () => {
      toast.success('Invoice generation started', {
        description: 'Your invoice is being generated. You will be notified when it is ready for download.'
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
};

// Function to toggle selection of a single invoice
const toggleInvoiceSelection = (invoiceId: string) => {
  if (selectedInvoices.value.includes(invoiceId)) {
    selectedInvoices.value = selectedInvoices.value.filter((id: string) => id !== invoiceId);
  } else {
    selectedInvoices.value.push(invoiceId);
  }
};

// Function to toggle selection of all invoices
const toggleSelectAllInvoices = () => {
  selectAllInvoices.value = !selectAllInvoices.value;

  if (selectAllInvoices.value) {
    // Select all invoices
    selectedInvoices.value = props.subscription?.invoices.map((invoice: any) => invoice.id) || [];
  } else {
    // Deselect all invoices
    selectedInvoices.value = [];
  }
};

// Function to toggle selection of all paid invoices
const toggleSelectAllPaidInvoices = () => {
  selectAllPaidInvoices.value = !selectAllPaidInvoices.value;

  if (selectAllPaidInvoices.value) {
    // Get all paid invoice IDs
    const paidInvoiceIds = props.subscription?.grouped_invoices?.paid.map((invoice: any) => invoice.id) || [];

    // Add all paid invoice IDs to the selection (avoiding duplicates)
    paidInvoiceIds.forEach((id: string) => {
      if (!selectedInvoices.value.includes(id)) {
        selectedInvoices.value.push(id);
      }
    });
  } else {
    // Remove all paid invoice IDs from the selection
    const paidInvoiceIds = props.subscription?.grouped_invoices?.paid.map((invoice: any) => invoice.id) || [];
    selectedInvoices.value = selectedInvoices.value.filter((id: string) => !paidInvoiceIds.includes(id));
  }
};

// Function to toggle selection of all pending invoices
const toggleSelectAllPendingInvoices = () => {
  selectAllPendingInvoices.value = !selectAllPendingInvoices.value;

  if (selectAllPendingInvoices.value) {
    // Get all pending invoice IDs
    const pendingInvoiceIds = props.subscription?.grouped_invoices?.pending.map((invoice: any) => invoice.id) || [];

    // Add all pending invoice IDs to the selection (avoiding duplicates)
    pendingInvoiceIds.forEach((id: string) => {
      if (!selectedInvoices.value.includes(id)) {
        selectedInvoices.value.push(id);
      }
    });
  } else {
    // Remove all pending invoice IDs from the selection
    const pendingInvoiceIds = props.subscription?.grouped_invoices?.pending.map((invoice: any) => invoice.id) || [];
    selectedInvoices.value = selectedInvoices.value.filter((id: string) => !pendingInvoiceIds.includes(id));
  }
};

// Function to handle batch progress notifications
const handleBatchProgressNotification = (notification: Notification) => {
  console.log('Handling batch notification in Billing.vue:', notification);

  // Check if this is a Laravel Echo notification (which has a different structure)
  if (notification.type && notification.type.startsWith('App\\Notifications\\')) {
    // This is a Laravel Echo notification
    if (notification.type === 'App\\Notifications\\InvoiceBatchProgress') {
      // Update the batch progress
      batchProgress.value = {
        batchId: notification.batch_id,
        totalInvoices: notification.total_invoices,
        processedInvoices: notification.processed_invoices,
        progress: notification.progress,
        isComplete: false
      };

      // Only show toast for 50% and 100% progress
      const significantProgress = [50, 100];
      if (notification.progress && significantProgress.includes(notification.progress)) {
        toast.info('Invoice Generation Progress', {
          description: `${notification.processed_invoices} of ${notification.total_invoices} invoices processed (${notification.progress}%)`
        });
      }
    } else if (notification.type === 'App\\Notifications\\InvoiceBatchComplete') {
      // Update the batch progress to complete
      batchProgress.value = {
        batchId: notification.batch_id,
        totalInvoices: notification.total_invoices,
        processedInvoices: notification.successful_invoices || notification.total_invoices,
        progress: 100,
        isComplete: true
      };

      // Show a toast notification for completion
      if (notification.success) {
        toast.success('Invoice Generation Complete', {
          description: `All ${notification.total_invoices} invoices have been generated successfully.`
        });
      } else {
        toast.warning('Invoice Generation Completed with Issues', {
          description: `${notification.successful_invoices || 0} of ${notification.total_invoices} invoices were generated successfully.`
        });
      }
    }
  } else {
    // This is a direct notification (not from Laravel Echo)
    if (notification.type === 'invoice_batch_progress') {
      // Update the batch progress
      batchProgress.value = {
        batchId: notification.batch_id,
        totalInvoices: notification.total_invoices,
        processedInvoices: notification.processed_invoices,
        progress: notification.progress,
        isComplete: false
      };

      // Show a toast notification for significant progress (25%, 50%, 75%)
      const progress = notification.progress || 0;
      if ([25, 50, 75].includes(progress)) {
        const processedInvoices = notification.processed_invoices || 0;
        const totalInvoices = notification.total_invoices || 0;
        toast.info('Invoice Generation Progress', {
          description: `${processedInvoices} of ${totalInvoices} invoices processed (${progress}%)`
        });
      }
    } else if (notification.type === 'invoice_batch_complete') {
      // Update the batch progress to complete
      batchProgress.value = {
        batchId: notification.batch_id,
        totalInvoices: notification.total_invoices,
        processedInvoices: notification.successful_invoices,
        progress: 100,
        isComplete: true
      };

      // Show a toast notification for completion
      if (notification.success) {
        toast.success('Invoice Generation Complete', {
          description: `All ${notification.total_invoices} invoices have been generated successfully.`
        });
      } else {
        toast.warning('Invoice Generation Completed with Issues', {
          description: `${notification.successful_invoices} of ${notification.total_invoices} invoices were generated successfully.`
        });
      }
    }
  }
};

// Function to generate multiple invoices
const generateSelectedInvoices = async () => {
  if (selectedInvoices.value.length === 0) {
    toast.error('No invoices selected', {
      description: 'Please select at least one invoice to generate.'
    });
    return;
  }

  isBulkGenerating.value = true;

  try {
    // Reset batch progress
    batchProgress.value = {
      batchId: null,
      totalInvoices: selectedInvoices.value.length,
      processedInvoices: 0,
      progress: 0,
      isComplete: false
    };

    // Send all selected invoice IDs in a single request
    const response = await fetch(route('invoice.generate-batch'), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        invoice_ids: selectedInvoices.value
      })
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    // Parse the response but we don't need to use the result
    await response.json();

    // Show success message
    toast.success('Bulk invoice generation started', {
      description: `${selectedInvoices.value.length} invoice(s) are being processed in the background. You will receive progress updates.`
    });

    // Clear selection after successful operation
    selectedInvoices.value = [];
    selectAllInvoices.value = false;
  } catch (error) {
    console.error('Error generating invoices in batch:', error);
    toast.error('Error starting batch generation', {
      description: 'There was an error processing your request. Please try again later.'
    });

    // Reset batch progress on error
    batchProgress.value = {
      batchId: null,
      totalInvoices: 0,
      processedInvoices: 0,
      progress: 0,
      isComplete: false
    };
  } finally {
    isBulkGenerating.value = false;
  }
};

// Function to handle plan change or subscription
const handlePlanChangeOrSubscribe = async () => {
  // Set the plan in the form
  subscriptionForm.plan = selectedPlan.value;

  // Check if the selected plan is an enterprise plan
  if (isEnterprisePlan(selectedPlan.value)) {
    // Close the dialog
    showChangePlanDialog.value = false;

    // Show a toast notification for enterprise plans
    showEnterpriseToast();
    return;
  }

  // If we already have a payment method on file, just update the plan
  if (props.subscription && props.subscription.card_brand) {
    // Get the plan name safely
    const planName = getPlanName(selectedPlan.value);

    // Submit the form using Inertia
    subscriptionForm.post(route('subscription.subscribe'), {
      onSuccess: () => {
        // Close the dialog
        showChangePlanDialog.value = false;

        // Show success message
        toast.success('Plan updated', {
          description: `Your subscription has been updated to the ${planName} plan.`
        });

        // Reload only the subscription data
        router.reload({ only: ['subscription'] });
      },
      onError: (errors: any) => {
        console.error('Plan update error:', errors);
        let errorMessage = 'There was an error processing your request.';

        // Check for different error formats
        if (errors.message) {
          errorMessage = errors.message;
        } else if (errors.error) {
          errorMessage = errors.error;
        } else if (typeof errors === 'string') {
          errorMessage = errors;
        } else if (errors.errors && Object.keys(errors.errors).length > 0) {
          // Get the first validation error
          const firstKey = Object.keys(errors.errors)[0];
          errorMessage = errors.errors[firstKey][0];
        }

        toast.error('Error updating plan', {
          description: errorMessage
        });
      }
    });
    return;
  }

  // Otherwise, create a new payment method
  const paymentMethodId = await createPaymentMethod();
  if (!paymentMethodId) return;

  // Set the payment method in the form
  subscriptionForm.payment_method = paymentMethodId;

  // Get the plan name safely
  const planName = getPlanName(selectedPlan.value);

  // Submit the form using Inertia
  subscriptionForm.post(route('subscription.subscribe'), {
    onSuccess: () => {
      // Close the dialog
      showChangePlanDialog.value = false;

      // Show success message
      toast.success('Subscription created', {
        description: `Your ${planName} plan subscription has been processed successfully.`
      });

      // Reload only the subscription data
      router.reload({ only: ['subscription'] });
    },
    onError: (errors: any) => {
      console.error('Subscription creation error:', errors);
      let errorMessage = 'There was an error processing your request.';

      // Check for different error formats
      if (errors.message) {
        errorMessage = errors.message;
      } else if (errors.error) {
        errorMessage = errors.error;
      } else if (typeof errors === 'string') {
        errorMessage = errors;
      } else if (errors.errors && Object.keys(errors.errors).length > 0) {
        // Get the first validation error
        const firstKey = Object.keys(errors.errors)[0];
        errorMessage = errors.errors[firstKey][0];
      }

      toast.error('Error creating subscription', {
        description: errorMessage
      });
    }
  });
};

// Function to handle payment method update
const handleUpdatePaymentMethod = async () => {
  const paymentMethodId = await createPaymentMethod();
  if (!paymentMethodId) return;

  // Set the payment method in the form
  updatePaymentForm.payment_method = paymentMethodId;

  // Submit the form using Inertia
  updatePaymentForm.put(route('subscription.update-payment'), {
    onSuccess: () => {
      showUpdatePaymentDialog.value = false;
      toast.success('Payment method updated', {
        description: 'Your payment method has been updated successfully.'
      });
    },
    onError: (errors: any) => {
      toast.error('Error updating payment method', {
        description: errors.message || 'There was an error processing your request.'
      });
    }
  });
};

// Function to handle subscription cancellation
const handleCancelSubscription = () => {
  // Set the reason in the form
  cancelForm.reason = cancelReason.value;

  // Get the cancellation message based on the selected type
  const successMessage = cancelForm.cancel_type === 'end_of_period'
    ? 'Your subscription will be canceled at the end of your current billing period.'
    : 'Your subscription has been canceled immediately.';

  // Submit the form using Inertia
  cancelForm.post(route('subscription.cancel'), {
    onSuccess: () => {
      showCancelDialog.value = false;
      toast.success('Subscription canceled', {
        description: successMessage
      });
    },
    onError: (errors: any) => {
      toast.error('Error canceling subscription', {
        description: errors.message || 'There was an error processing your request.'
      });
    }
  });
};

// Create a payment method with Stripe using our utility functions
const createPaymentMethod = async (): Promise<string | null> => {
  try {
    // Check for internet connectivity using our utility
    if (!isOnline()) {
      showStripeConnectivityError('Please check your internet connection and try again.');
      return null;
    }

    if (!stripe.value || !cardElement.value) {
      toast.error('Payment processing error', {
        description: 'Payment system is not available. Please try again later.'
      });
      return null;
    }

    try {
      // Try to create a payment method with Stripe
      let paymentMethodResult;

      try {
        paymentMethodResult = await stripe.value.createPaymentMethod({
          type: 'card',
          card: cardElement.value,
        });
      } catch (stripeError: any) {
        // Use our utility to check if this is a connectivity error
        if (isConnectivityError(stripeError)) {
          showStripeConnectivityError('Unable to connect to payment service. Please check your internet connection and try again.');
        } else {
          toast.error('Payment processing error', {
            description: getStripeErrorMessage(stripeError)
          });
        }
        console.error('Stripe API error:', stripeError);
        return null;
      }

      const { paymentMethod, error } = paymentMethodResult;

      if (error) {
        // Use our utility to handle different types of Stripe errors
        if (isConnectivityError(error)) {
          showStripeConnectivityError('Unable to connect to payment service. Please check your internet connection and try again.');
        } else {
          toast.error('Payment error', {
            description: getStripeErrorMessage(error)
          });
        }
        return null;
      }

      if (!paymentMethod || !paymentMethod.id) {
        toast.error('Payment error', {
          description: 'No payment method was created. Please try again later.'
        });
        return null;
      }

      return paymentMethod.id;
    } catch (error: any) {
      // Use our utility to check if this is a connectivity error
      if (isConnectivityError(error)) {
        showStripeConnectivityError('Unable to connect to payment service. Please check your internet connection and try again.');
      } else {
        toast.error('Payment processing error', {
          description: getStripeErrorMessage(error)
        });
      }
      console.error('Error creating payment method:', error);
      return null;
    }
  } catch (error: any) {
    toast.error('Unexpected error', {
      description: 'An unexpected error occurred. Please check your internet connection and try again later.'
    });
    console.error('Unexpected error in createPaymentMethod:', error);
    return null;
  }
};

// Define notification type
interface Notification {
  id: string;
  type: string;
  data?: any;
  batch_id?: string;
  total_invoices?: number;
  processed_invoices?: number;
  successful_invoices?: number;
  progress?: number;
  success?: boolean;
  time?: string;
  created_at?: string;
  read_at?: string | null;
}

// Function to set up Echo listeners for batch progress
const setupEchoListeners = () => {
  if (window.Echo) {
    const userId = props.subscription?.user_id;

    if (userId) {
      // Listen for notifications on the user's private channel
      window.Echo.private(`App.Models.User.${userId}`)
        .notification((notification: Notification) => {
          console.log('Received notification in Billing.vue:', notification);

          // Pass the notification directly to the handler
          handleBatchProgressNotification(notification);
        });

      console.log(`Listening for batch notifications on App.Models.User.${userId}`);
    }
  } else {
    console.warn('Echo is not available. Real-time notifications will not work.');
  }
};

// Simulate loading
onMounted(() => {
  setTimeout(() => {
    isLoading.value = false;

    // Check if there's a plan parameter in the URL
    const urlParams = new URLSearchParams(window.location.search);
    const planParam = urlParams.get('plan');

    // If there's a plan parameter, find the matching plan
    if (planParam) {
      // Find the plan by name (case-insensitive)
      const matchingPlan = availablePlans.value.find((p: any) =>
        p.name.toLowerCase() === planParam.toLowerCase()
      );

      if (matchingPlan) {
        // Set the selected plan
        selectedPlan.value = matchingPlan.id;

        // Open the dialog
        showChangePlanDialog.value = true;

        // Remove the plan parameter from the URL without refreshing the page
        let newUrl = window.location.pathname + window.location.search;
        newUrl = newUrl.replace(/[?&]plan=[^&]+(&|$)/, '$1');
        window.history.replaceState({}, document.title, newUrl);
      }
    }

    // Set up Echo listeners for batch progress
    setupEchoListeners();
  }, 1000);
});
</script>

<template>
  <Head title="Billing" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 p-4 w-full max-w-full">
      <!-- Billing Header -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
        <div>
          <h1 class="text-2xl sm:text-3xl font-bold">Billing & Subscription</h1>
          <p class="text-sm sm:text-base text-muted-foreground">Manage your subscription and payment methods</p>
        </div>
      </div>

      <!-- Connection Error Alert -->
      <div v-if="connectionError || (subscription && subscription.connection_error)" class="bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-800 rounded-md p-4 mb-4">
        <div class="flex">
          <div class="flex-shrink-0">
            <CircleAlert class="h-5 w-5 text-amber-400" />
          </div>
          <div class="ml-3">
            <h3 class="text-sm font-medium text-amber-800 dark:text-amber-300">Connection Issue</h3>
            <div class="mt-2 text-sm text-amber-700 dark:text-amber-400">
              <p>{{ subscription?.connection_error_message || 'Unable to connect to payment service. Some subscription details may be limited until connection is restored.' }}</p>
            </div>
          </div>
        </div>
      </div>

      <div class="grid gap-6 w-full max-w-full">
        <!-- Current Subscription -->
        <div class="w-full max-w-full">
          <Card class="w-full max-w-full">
            <CardHeader>
              <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                  <CardTitle>Subscription Details</CardTitle>
                  <CardDescription>Manage your plan and billing information</CardDescription>
                </div>
                <Button
                  size="sm"
                  variant="outline"
                  @click="syncSubscription"
                  :disabled="isSyncing"
                  class="whitespace-nowrap"
                >
                  <template v-if="isSyncing">
                    <div class="animate-spin mr-2 h-4 w-4 border-2 border-current border-t-transparent rounded-full"></div>
                    Syncing...
                  </template>
                  <template v-else>
                    <RefreshCcw class="h-4 w-4 mr-1.5" />
                    Refresh Status
                  </template>
                </Button>
              </div>
            </CardHeader>
            <CardContent>
                <div v-if="isLoading" class="space-y-3">
                <Skeleton class="h-[100px] w-full" />
                <Skeleton class="h-[100px] w-full" />
                </div>
                <div v-else-if="subscription">
                <!-- Subscription and Features in one row -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 sm:gap-6">
                    <!-- Current Plan -->
                    <div class="border rounded-lg p-3 sm:p-4">
                    <div class="flex flex-col h-full">
                        <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="font-medium text-lg">{{ subscription.name || 'Active' }} Plan</h3>
                            <Badge :class="{
                            'bg-green-500': subscription.stripe_status === 'active',
                            'bg-amber-500': subscription.stripe_status === 'trialing',
                            'bg-red-500': subscription.stripe_status === 'canceled' || subscription.stripe_status === 'incomplete_expired',
                            'bg-blue-500': subscription.stripe_status === 'incomplete',
                            'bg-purple-500': subscription.stripe_status === 'past_due'
                            }">
                            {{ subscription.stripe_status === 'trialing' ? 'Trial' :
                                subscription.stripe_status === 'active' ? 'Active' :
                                subscription.stripe_status === 'canceled' ? 'Canceled' :
                                subscription.stripe_status === 'incomplete' ? 'Incomplete' :
                                subscription.stripe_status === 'incomplete_expired' ? 'Expired' :
                                subscription.stripe_status === 'past_due' ? 'Past Due' :
                                subscription.stripe_status }}
                            </Badge>
                            <Badge v-if="subscription.offline_mode" variant="outline" class="ml-2 border-amber-300 text-amber-700 dark:border-amber-600 dark:text-amber-400">
                              Offline Mode
                            </Badge>
                        </div>
                        <p class="text-sm text-muted-foreground mt-1">
                            <template v-if="subscription.price !== null">
                              ${{ subscription.price || '0.00' }}/{{ subscription.interval || 'month' }} •
                            </template>
                            <template v-else>
                              <span class="italic">Price unavailable in offline mode</span> •
                            </template>
                            {{ subscription.ends_at ? 'Ends on ' + formatDate(subscription.ends_at) :
                            subscription.trial_ends_at ? 'Trial ends on ' + formatDate(subscription.trial_ends_at) :
                            'Next billing on ' + formatDate(subscription.next_billing_date) }}
                        </p>

                        <!-- Payment Method -->
                        <div v-if="subscription.card_brand" class="flex items-center mt-3 text-sm text-muted-foreground">
                            <CreditCard class="h-4 w-4 mr-2" />
                            <span class="capitalize">{{ subscription.card_brand }}</span> ending in {{ subscription.card_last_four }}
                        </div>
                        </div>

                        <div class="flex flex-wrap gap-2 mt-4">
                        <Button
                            v-if="subscription.stripe_status === 'active'"
                            variant="outline"
                            size="sm"
                            @click="() => {
                              // Set the current plan as selected
                              selectedPlan = subscription.stripe_price;

                              // Don't show plan selection initially
                              showPlanSelection = false;
                              // Open the dialog
                              showChangePlanDialog = true;
                            }"
                        >
                            <Zap class="h-3.5 w-3.5 mr-1" />
                            Change Plan
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            @click="showUpdatePaymentDialog = true"
                        >
                            <CreditCard class="h-3.5 w-3.5 mr-1" />
                            Update Payment
                        </Button>
                        <Button
                            v-if="subscription.stripe_status === 'active'"
                            variant="outline"
                            size="sm"
                            class="text-red-500 hover:bg-red-50"
                            @click="showCancelDialog = true"
                        >
                            <Ban class="h-3.5 w-3.5 mr-1" />
                            Cancel
                        </Button>
                        <Button
                            v-if="subscription.stripe_status === 'canceled' && subscription.on_grace_period"
                            variant="default"
                            size="sm"
                            @click="resumeSubscription"
                        >
                            <RefreshCw class="h-3.5 w-3.5 mr-1" />
                            Resume
                        </Button>
                        </div>
                    </div>
                    </div>

                    <!-- Plan Features -->
                    <div v-if="subscription.features" class="border rounded-lg p-3 sm:p-4">
                    <h3 class="font-medium mb-3 sm:mb-4">Plan Features</h3>
                    <div class="max-h-[250px] overflow-y-auto pr-1">
                        <ul class="space-y-2">
                        <li v-for="(feature, index) in subscription.features" :key="index" class="flex items-center">
                            <div v-if="feature.included" class="h-5 w-5 rounded-full bg-primary/10 flex items-center justify-center mr-2 flex-shrink-0">
                            <Check class="h-3 w-3 text-primary" />
                            </div>
                            <div v-else class="h-5 w-5 rounded-full bg-gray-100 flex items-center justify-center mr-2 flex-shrink-0">
                            <X class="h-3 w-3 text-gray-400" />
                            </div>
                            <span class="text-sm" :class="{ 'text-muted-foreground': !feature.included }">
                            {{ feature.name }}
                            </span>
                        </li>
                        </ul>
                    </div>
                    </div>
                </div>
                </div>
                <div v-else>
                <div class="text-center py-6 border rounded-lg mb-6">
                    <div class="mx-auto w-12 h-12 rounded-full bg-muted flex items-center justify-center mb-3">
                    <CreditCard class="h-6 w-6 text-muted-foreground" />
                    </div>
                    <h3 class="text-lg font-medium mb-1">No Active Subscription</h3>
                    <p class="text-sm text-muted-foreground mb-2">
                    Subscribe to a plan to access premium features.
                    </p>
                </div>
                </div>
            </CardContent>
          </Card>
        </div>

        <!-- Available Plans -->
        <div v-if="!subscription || isCancelledWithoutGracePeriod" class="mt-6 w-full max-w-full">
          <Card class="w-full max-w-full">
            <CardHeader>
              <CardTitle>Available Plans</CardTitle>
              <CardDescription>
                {{ isCancelledWithoutGracePeriod ? 'Your subscription has been cancelled. Choose a new plan to resubscribe.' : 'Choose a plan that fits your needs' }}
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div v-if="isCancelledWithoutGracePeriod" class="mb-4 p-4 border rounded-lg bg-amber-50 dark:bg-amber-950">
                <div class="flex items-start gap-3">
                  <div class="mt-0.5">
                    <AlertCircle class="h-5 w-5 text-amber-500" />
                  </div>
                  <div>
                    <p class="font-medium text-amber-800 dark:text-amber-300">Subscription Cancelled</p>
                    <p class="text-sm text-amber-700 dark:text-amber-400 mt-1">
                      Your subscription has been cancelled and will end on {{ formatDate(subscription.ends_at) }}.
                      You can subscribe to a new plan below.
                    </p>
                  </div>
                </div>
              </div>

              <!-- Offline Mode Warning for Plans -->
              <div v-if="availablePlans.length > 0 && availablePlans[0].offline_mode" class="bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-800 rounded-md p-4 mb-4">
                <div class="flex">
                  <div class="flex-shrink-0">
                    <AlertCircle class="h-5 w-5 text-amber-400" />
                  </div>
                  <div class="ml-3">
                    <h3 class="text-sm font-medium text-amber-800 dark:text-amber-300">Connection Issue</h3>
                    <div class="mt-2 text-sm text-amber-700 dark:text-amber-400">
                      <p>Unable to connect to payment service. Plan information is being displayed from local data and may not be up to date.</p>
                    </div>
                  </div>
                </div>
              </div>

              <div v-if="availablePlans.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                <div v-for="plan in availablePlans" :key="plan.id"
                  class="border rounded-lg p-4 sm:p-6 flex flex-col hover:shadow-md transition-all duration-200"
                  :class="{ 'border-primary ring-2 ring-primary/20': selectedPlan === plan.id }"
                >
                  <div class="mb-4">
                    <div class="flex items-center gap-2 mb-1">
                      <h3 class="text-lg sm:text-xl font-bold">{{ plan.name }}</h3>
                      <Badge v-if="plan.offline_mode" variant="outline" class="border-amber-300 text-amber-700 dark:border-amber-600 dark:text-amber-400">
                        Offline
                      </Badge>
                    </div>
                    <div class="flex items-end mt-2">
                      <span class="text-2xl sm:text-3xl font-bold">${{ plan.price }}</span>
                      <span class="text-muted-foreground ml-1">/ {{ plan.interval }}</span>
                    </div>
                  </div>

                  <div class="max-h-[200px] overflow-y-auto pr-1 mb-4">
                    <ul class="space-y-2 sm:space-y-3 flex-grow">
                      <li v-for="(feature, i) in plan.features" :key="i" class="flex items-start">
                        <div v-if="feature.included" class="rounded-full p-0.5 mr-2 mt-0.5 text-green-500 flex-shrink-0">
                          <Check class="h-4 w-4" />
                        </div>
                        <div v-else class="rounded-full p-0.5 mr-2 mt-0.5 text-gray-400 flex-shrink-0">
                          <X class="h-4 w-4" />
                        </div>
                        <span :class="{ 'text-muted-foreground': !feature.included }" class="text-sm sm:text-base">
                          {{ feature.name }}
                        </span>
                      </li>
                    </ul>
                  </div>

                  <Button
                    class="w-full mt-auto"
                    :variant="selectedPlan === plan.id ? 'default' : 'outline'"
                    @click="() => {
                      selectedPlan = plan.id;

                      // Check if this is an enterprise plan
                      if (isEnterprisePlan(plan.id)) {
                        showEnterpriseToast();
                        return;
                      }

                      showPlanSelection = false;
                      showChangePlanDialog = true;
                    }"
                  >
                    <Zap class="h-4 w-4 mr-1.5" />
                    {{ isCancelledWithoutGracePeriod ? 'Subscribe Again' : 'Get Started' }}
                  </Button>
                </div>
              </div>

              <!-- No Plans Available Message -->
              <div v-else class="text-center py-8 border rounded-lg">
                <div class="mx-auto w-16 h-16 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center mb-4">
                  <AlertCircle class="h-8 w-8 text-amber-600 dark:text-amber-400" />
                </div>
                <h3 class="text-lg font-medium mb-2">No Subscription Plans Available</h3>
                <p class="text-sm text-muted-foreground max-w-md mx-auto mb-6">
                  We couldn't find any subscription plans at the moment. Please check back later or contact support for assistance.
                </p>
                <Button size="sm" @click="syncSubscription">
                  <RefreshCw class="h-4 w-4 mr-1.5" />
                  Refresh Plans
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Invoices -->
        <div v-if="subscription && subscription.stripe_status !== 'incomplete'" class="w-full max-w-full">
          <Card class="w-full max-w-full">
            <CardHeader>
              <CardTitle>Invoices</CardTitle>
              <CardDescription>
                Your billing history with payment status and transaction details.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <div v-if="isLoading" class="space-y-3">
                <Skeleton class="h-[100px] w-full" />
              </div>
              <div v-else>
                <!-- Bulk actions for selected invoices -->
                <div v-if="subscription.invoices && subscription.invoices.length > 0" class="mb-4 flex items-center justify-between">
                  <div class="flex items-center">
                    <span class="text-sm text-muted-foreground mr-2">
                      {{ selectedInvoices.length }} of {{ subscription.invoices.length }} selected
                    </span>
                  </div>
                  <div class="flex items-center space-x-2">
                    <DropdownMenu v-if="selectedInvoices.length > 0">
                      <DropdownMenuTrigger as-child>
                        <Button variant="outline" size="sm">
                          Bulk Actions
                          <ChevronDown class="ml-2 h-4 w-4" />
                        </Button>
                      </DropdownMenuTrigger>
                      <DropdownMenuContent align="end">
                        <DropdownMenuItem @click="generateSelectedInvoices" :disabled="isBulkGenerating">
                          <FileDown class="mr-2 h-4 w-4" />
                          <span v-if="isBulkGenerating">Processing...</span>
                          <span v-else>Generate PDFs</span>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem @click="selectedInvoices = []">
                          <X class="mr-2 h-4 w-4" />
                          Clear Selection
                        </DropdownMenuItem>
                      </DropdownMenuContent>
                    </DropdownMenu>
                  </div>
                </div>

                <!-- Tabs for invoice types -->
                <Tabs v-if="subscription.grouped_invoices" default-value="all" class="mb-6">
                  <TabsList class="grid w-full grid-cols-4">
                    <TabsTrigger value="all">All</TabsTrigger>
                    <TabsTrigger value="paid">Paid</TabsTrigger>
                    <TabsTrigger value="pending">Pending</TabsTrigger>
                    <TabsTrigger value="upcoming">Upcoming</TabsTrigger>
                  </TabsList>

                  <!-- All Invoices Tab -->
                  <TabsContent value="all">
                    <div class="overflow-x-auto min-w-full">
                      <table class="w-full border-collapse">
                        <thead>
                          <tr class="border-b">
                            <th class="py-3 px-2 text-left font-medium whitespace-nowrap">
                              <input
                                type="checkbox"
                                :checked="selectAllInvoices"
                                @change="toggleSelectAllInvoices"
                                class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                              />
                            </th>
                            <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Invoice ID</th>
                            <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Date</th>
                            <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Amount</th>
                            <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Status</th>
                            <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="invoice in subscription.invoices" :key="invoice.id" class="border-b" :class="{ 'bg-muted/40': selectedInvoices.includes(invoice.id) }">
                            <td class="py-3 px-2">
                              <input
                                type="checkbox"
                                :checked="selectedInvoices.includes(invoice.id)"
                                @change="toggleInvoiceSelection(invoice.id)"
                                class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                              />
                            </td>
                            <td class="py-3 px-2 font-medium">{{ invoice.id }}</td>
                            <td class="py-3 px-2 whitespace-nowrap">{{ invoice.date }}</td>
                            <td class="py-3 px-2 whitespace-nowrap">{{ invoice.formatted_amount }}</td>
                            <td class="py-3 px-2">
                              <Badge :variant="invoice.status === 'paid' ? 'default' : 'outline'">
                                {{ invoice.status }}
                              </Badge>
                            </td>
                            <td class="py-3 px-2">
                              <Button
                                variant="outline"
                                size="sm"
                                @click="generateInvoicePdf(invoice.id)"
                                :disabled="isGeneratingInvoice[invoice.id]"
                                class="whitespace-nowrap"
                              >
                                <FileDown class="h-4 w-4 mr-1" />
                                <span v-if="isGeneratingInvoice[invoice.id]" class="hidden sm:inline">Processing...</span>
                                <span v-else class="hidden sm:inline">Download PDF</span>
                                <span v-if="isGeneratingInvoice[invoice.id]" class="sm:hidden">...</span>
                              </Button>
                            </td>
                          </tr>
                          <tr v-if="!subscription.invoices || subscription.invoices.length === 0">
                            <td colspan="6" class="text-center py-4">
                              No invoices found for this subscription
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </TabsContent>

                  <!-- Paid Invoices Tab -->
                  <TabsContent value="paid">
                    <div class="overflow-x-auto min-w-full">
                      <table class="w-full border-collapse">
                        <thead>
                          <tr class="border-b">
                            <th class="py-3 px-2 text-left font-medium whitespace-nowrap">
                              <input
                                type="checkbox"
                                :checked="selectAllPaidInvoices"
                                @change="toggleSelectAllPaidInvoices"
                                class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                              />
                            </th>
                            <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Invoice ID</th>
                            <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Date</th>
                            <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Amount</th>
                            <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="invoice in subscription.grouped_invoices.paid" :key="invoice.id" class="border-b" :class="{ 'bg-muted/40': selectedInvoices.includes(invoice.id) }">
                            <td class="py-3 px-2">
                              <input
                                type="checkbox"
                                :checked="selectedInvoices.includes(invoice.id)"
                                @change="toggleInvoiceSelection(invoice.id)"
                                class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                              />
                            </td>
                            <td class="py-3 px-2 font-medium">{{ invoice.id }}</td>
                            <td class="py-3 px-2 whitespace-nowrap">{{ invoice.date }}</td>
                            <td class="py-3 px-2 whitespace-nowrap">{{ invoice.formatted_amount }}</td>
                            <td class="py-3 px-2">
                              <Button
                                variant="outline"
                                size="sm"
                                @click="generateInvoicePdf(invoice.id)"
                                :disabled="isGeneratingInvoice[invoice.id]"
                                class="whitespace-nowrap"
                              >
                                <FileDown class="h-4 w-4 mr-1" />
                                <span v-if="isGeneratingInvoice[invoice.id]" class="hidden sm:inline">Processing...</span>
                                <span v-else class="hidden sm:inline">Download PDF</span>
                                <span v-if="isGeneratingInvoice[invoice.id]" class="sm:hidden">...</span>
                              </Button>
                            </td>
                          </tr>
                          <tr v-if="!subscription.grouped_invoices.paid || subscription.grouped_invoices.paid.length === 0">
                            <td colspan="5" class="text-center py-4">
                              No paid invoices found
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </TabsContent>

                  <!-- Pending Invoices Tab -->
                  <TabsContent value="pending">
                    <div class="overflow-x-auto min-w-full">
                      <table class="w-full border-collapse">
                        <thead>
                          <tr class="border-b">
                            <th class="py-3 px-2 text-left font-medium whitespace-nowrap">
                              <input
                                type="checkbox"
                                :checked="selectAllPendingInvoices"
                                @change="toggleSelectAllPendingInvoices"
                                class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                              />
                            </th>
                            <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Invoice ID</th>
                            <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Date</th>
                            <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Amount</th>
                            <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Actions</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="invoice in subscription.grouped_invoices.pending" :key="invoice.id" class="border-b" :class="{ 'bg-muted/40': selectedInvoices.includes(invoice.id) }">
                            <td class="py-3 px-2">
                              <input
                                type="checkbox"
                                :checked="selectedInvoices.includes(invoice.id)"
                                @change="toggleInvoiceSelection(invoice.id)"
                                class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                              />
                            </td>
                            <td class="py-3 px-2 font-medium">{{ invoice.id }}</td>
                            <td class="py-3 px-2 whitespace-nowrap">{{ invoice.date }}</td>
                            <td class="py-3 px-2 whitespace-nowrap">{{ invoice.formatted_amount }}</td>
                            <td class="py-3 px-2">
                              <Button
                                variant="outline"
                                size="sm"
                                @click="generateInvoicePdf(invoice.id)"
                                :disabled="isGeneratingInvoice[invoice.id]"
                                class="whitespace-nowrap"
                              >
                                <FileDown class="h-4 w-4 mr-1" />
                                <span v-if="isGeneratingInvoice[invoice.id]" class="hidden sm:inline">Processing...</span>
                                <span v-else class="hidden sm:inline">Download PDF</span>
                                <span v-if="isGeneratingInvoice[invoice.id]" class="sm:hidden">...</span>
                              </Button>
                            </td>
                          </tr>
                          <tr v-if="!subscription.grouped_invoices.pending || subscription.grouped_invoices.pending.length === 0">
                            <td colspan="5" class="text-center py-4">
                              No pending invoices found
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </TabsContent>

                  <!-- Upcoming Invoices Tab -->
                  <TabsContent value="upcoming">
                    <div class="overflow-x-auto min-w-full">
                      <table class="w-full border-collapse">
                        <thead>
                          <tr class="border-b">
                            <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Invoice ID</th>
                            <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Date</th>
                            <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Amount</th>
                            <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Status</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="invoice in subscription.grouped_invoices.upcoming" :key="invoice.id" class="border-b">
                            <td class="py-3 px-2 font-medium">{{ invoice.id }}</td>
                            <td class="py-3 px-2 whitespace-nowrap">{{ invoice.date }}</td>
                            <td class="py-3 px-2 whitespace-nowrap">{{ invoice.formatted_amount }}</td>
                            <td class="py-3 px-2">
                              <Badge variant="secondary">
                                Upcoming
                              </Badge>
                            </td>
                          </tr>
                          <tr v-if="!subscription.grouped_invoices.upcoming || subscription.grouped_invoices.upcoming.length === 0">
                            <td colspan="4" class="text-center py-4">
                              No upcoming invoices found
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </TabsContent>
                </Tabs>

                <!-- Fallback to original table if grouped invoices not available -->
                <div v-else class="overflow-x-auto min-w-full">
                  <table class="w-full border-collapse">
                    <thead>
                      <tr class="border-b">
                        <th class="py-3 px-2 text-left font-medium whitespace-nowrap">
                          <input
                            type="checkbox"
                            :checked="selectAllInvoices"
                            @change="toggleSelectAllInvoices"
                            class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                          />
                        </th>
                        <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Invoice ID</th>
                        <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Date</th>
                        <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Amount</th>
                        <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Status</th>
                        <th class="py-3 px-2 text-left font-medium whitespace-nowrap">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="invoice in subscription.invoices" :key="invoice.id" class="border-b" :class="{ 'bg-muted/40': selectedInvoices.includes(invoice.id) }">
                        <td class="py-3 px-2">
                          <input
                            type="checkbox"
                            :checked="selectedInvoices.includes(invoice.id)"
                            @change="toggleInvoiceSelection(invoice.id)"
                            class="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                          />
                        </td>
                        <td class="py-3 px-2 font-medium">{{ invoice.id }}</td>
                        <td class="py-3 px-2 whitespace-nowrap">{{ invoice.date }}</td>
                        <td class="py-3 px-2 whitespace-nowrap">{{ invoice.formatted_amount }}</td>
                        <td class="py-3 px-2">
                          <Badge :variant="invoice.status === 'paid' ? 'default' : 'outline'">
                            {{ invoice.status }}
                          </Badge>
                        </td>
                        <td class="py-3 px-2">
                          <Button
                            variant="outline"
                            size="sm"
                            @click="generateInvoicePdf(invoice.id)"
                            :disabled="isGeneratingInvoice[invoice.id]"
                            class="whitespace-nowrap"
                          >
                            <FileDown class="h-4 w-4 mr-1" />
                            <span v-if="isGeneratingInvoice[invoice.id]" class="hidden sm:inline">Processing...</span>
                            <span v-else class="hidden sm:inline">Download PDF</span>
                            <span v-if="isGeneratingInvoice[invoice.id]" class="sm:hidden">...</span>
                          </Button>
                        </td>
                      </tr>
                      <tr v-if="!subscription.invoices || subscription.invoices.length === 0">
                        <td colspan="6" class="text-center py-4">
                          No invoices found for this subscription
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Payment Methods -->
        <div class="w-full max-w-full">
          <Card class="w-full max-w-full">
            <CardHeader>
              <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                  <CardTitle>Payment Methods</CardTitle>
                  <CardDescription>Manage your payment information</CardDescription>
                </div>
                <Button size="sm" @click="() => router.visit(route('payment.methods'))" class="whitespace-nowrap">
                  <CreditCard class="h-4 w-4 mr-1.5" />
                  Manage Payment Methods
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              <div v-if="isLoading" class="space-y-3">
                <Skeleton class="h-[60px] w-full" />
              </div>
              <div v-else-if="subscription && subscription.card_brand" class="border rounded-lg p-4">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                  <div class="flex items-center">
                    <div class="mr-3">
                      <CardBrandIcon :brand="subscription.card_brand" size="md" />
                    </div>
                    <div>
                      <p class="font-medium capitalize">{{ subscription.card_brand }}</p>
                      <p class="text-sm text-muted-foreground">•••• •••• •••• {{ subscription.card_last_four }}</p>
                    </div>
                  </div>
                  <Badge variant="outline" class="bg-green-50 text-green-700 border-green-200">Default</Badge>
                </div>
              </div>
              <div v-else class="text-center py-6 border rounded-lg">
                <div class="flex justify-center space-x-2 mb-4">
                  <CardBrandIcon brand="visa" />
                  <CardBrandIcon brand="mastercard" />
                  <CardBrandIcon brand="amex" />
                </div>
                <h3 class="text-lg font-medium mb-1">No Payment Methods</h3>
                <p class="text-sm text-muted-foreground mb-4">
                  Add a payment method to subscribe to a plan.
                </p>
                <Button size="sm" @click="() => router.visit(route('payment.methods'))">
                  Add Payment Method
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>

    <!-- Change Plan Dialog -->
    <Dialog v-model:open="showChangePlanDialog">
      <DialogContent class="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle>{{ props.subscription ? 'Change Plan' : 'Subscribe to Plan' }}</DialogTitle>
          <DialogDescription>
            {{ props.subscription ? 'Update your subscription to a different plan.' : 'Choose a plan and enter your payment details.' }}
          </DialogDescription>
        </DialogHeader>
        <div class="py-4">
          <!-- Plan Selection UI -->
          <div v-if="showPlanSelection || !selectedPlan" class="mb-4">
            <Label>Select a Plan</Label>
            <div v-if="availablePlans.length > 0" class="grid grid-cols-1 gap-4 mt-2">
              <div v-for="plan in availablePlans" :key="plan.id"
                class="border rounded-lg p-4 flex flex-col hover:shadow-md transition-all duration-200 cursor-pointer"
                :class="{ 'border-primary ring-2 ring-primary/20': selectedPlan === plan.id }"
                @click="() => {
                  selectedPlan = plan.id;

                  // Check if this is an enterprise plan
                  if (isEnterprisePlan(plan.id)) {
                    // Close the dialog
                    showChangePlanDialog = false;

                    // Show a toast notification for enterprise plans
                    showEnterpriseToast();
                    return;
                  }

                  showPlanSelection = false;
                }"
              >
                <div class="flex justify-between items-center">
                  <div>
                    <p class="font-medium">{{ plan.name }}</p>
                    <p class="text-sm text-muted-foreground">${{ plan.price }}/{{ plan.interval }}</p>
                  </div>
                  <div class="h-5 w-5 rounded-full border flex items-center justify-center"
                    :class="{ 'bg-primary border-primary': selectedPlan === plan.id }">
                    <Check v-if="selectedPlan === plan.id" class="h-3 w-3 text-white" />
                  </div>
                </div>
              </div>
            </div>
            <div v-else class="text-center py-6 border rounded-lg mt-2">
              <AlertCircle class="h-8 w-8 text-amber-500 mx-auto mb-2" />
              <p class="font-medium mb-1">No Plans Available</p>
              <p class="text-sm text-muted-foreground mb-4">
                We couldn't find any subscription plans at the moment.
              </p>
              <Button size="sm" @click="syncSubscription">Refresh Plans</Button>
            </div>
          </div>

          <!-- Selected Plan Display -->
          <div v-else class="mb-4">
            <Label>Selected Plan</Label>
            <div class="border rounded-lg p-4 mt-2">
              <div class="flex justify-between items-center">
                <div>
                  <p class="font-medium">{{ getPlanName(selectedPlan) }}</p>
                  <p class="text-sm text-muted-foreground">
                    ${{ getPlanPrice(selectedPlan) }}/{{ getPlanInterval(selectedPlan) }}
                  </p>
                </div>
                <Button variant="outline" size="sm" @click="() => {
                  // Show the plan selection UI
                  showPlanSelection = true;
                }">
                  Change
                </Button>
              </div>
            </div>
          </div>

          <div v-if="!props.subscription || !props.subscription.card_brand">
            <div class="mb-4">
              <Label>Payment Information</Label>
              <div class="border rounded-lg p-4 mt-2">
                <div class="mb-4">
                  <div id="card-element" class="p-3 border rounded-md bg-background"></div>
                  <div v-if="cardErrors" class="text-sm text-red-500 mt-2">
                    {{ cardErrors }}
                  </div>
                </div>
                <div class="flex items-center text-sm text-muted-foreground">
                  <Shield class="h-4 w-4 mr-2" />
                  <span>Your payment information is secure and encrypted</span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button @click="showChangePlanDialog = false" variant="outline">Cancel</Button>
          <Button
            :disabled="!selectedPlan || (!props.subscription && !cardComplete) || availablePlans.length === 0"
            @click="handlePlanChangeOrSubscribe"
          >
            {{ availablePlans.length === 0
              ? 'No Plans Available'
              : props.subscription
                ? `Confirm ${getPlanName(selectedPlan)} Plan`
                : `Subscribe to ${getPlanName(selectedPlan)} Plan`
            }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Cancel Subscription Dialog -->
    <Dialog v-model:open="showCancelDialog">
      <DialogContent class="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>Cancel Subscription</DialogTitle>
          <DialogDescription>
            Are you sure you want to cancel your subscription? You'll lose access to premium features.
          </DialogDescription>
        </DialogHeader>
        <div class="py-4">
          <div class="mb-4">
            <Label for="cancel-reason">Reason for cancellation (optional)</Label>
            <Input id="cancel-reason" v-model="cancelReason" placeholder="Tell us why you're leaving..." class="mt-2" />
          </div>

          <div class="mb-4">
            <Label>Cancellation Options</Label>
            <div class="mt-2 space-y-4">
              <div class="flex items-start space-x-2">
                <input
                  type="radio"
                  id="end-of-period"
                  name="cancel_type"
                  value="end_of_period"
                  v-model="cancelForm.cancel_type"
                  class="mt-1"
                />
                <div>
                  <Label for="end-of-period" class="font-medium">Cancel at end of billing period</Label>
                  <p class="text-sm text-muted-foreground">
                    Your subscription will remain active until {{ formatDate(props.subscription?.next_billing_date) }}. You'll continue to have access to all features until then.
                  </p>
                </div>
              </div>

              <div class="flex items-start space-x-2">
                <input
                  type="radio"
                  id="immediately"
                  name="cancel_type"
                  value="immediately"
                  v-model="cancelForm.cancel_type"
                  class="mt-1"
                />
                <div>
                  <Label for="immediately" class="font-medium">Cancel immediately</Label>
                  <p class="text-sm text-muted-foreground">
                    Your subscription will be cancelled right away. You'll lose access to premium features immediately and no further charges will be made.
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="border rounded-lg p-4 bg-amber-50 dark:bg-amber-950">
            <div class="flex items-start gap-3">
              <div class="mt-0.5">
                <AlertCircle class="h-5 w-5 text-amber-500" />
              </div>
              <div>
                <p class="font-medium text-amber-800 dark:text-amber-300">Important information</p>
                <p class="text-sm text-amber-700 dark:text-amber-400 mt-1">
                  {{ cancelForm.cancel_type === 'end_of_period'
                    ? 'Your subscription will remain active until the end of your current billing period. After that, your account will be downgraded to the free plan.'
                    : 'Your subscription will be cancelled immediately and you will lose access to premium features right away. No refund will be issued for the current billing period.' }}
                </p>
              </div>
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button @click="showCancelDialog = false" variant="outline">Keep Subscription</Button>
          <Button
            variant="destructive"
            @click="handleCancelSubscription"
          >
            Cancel Subscription
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Update Payment Method Dialog -->
    <Dialog v-model:open="showUpdatePaymentDialog">
      <DialogContent class="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>Update Payment Method</DialogTitle>
          <DialogDescription>
            Update your payment information for future billing.
          </DialogDescription>
        </DialogHeader>
        <div class="py-4">
          <div class="mb-4">
            <Label>New Payment Method</Label>
            <div class="border rounded-lg p-4 mt-2">
              <div class="mb-4">
                <div id="update-card-element" class="p-3 border rounded-md bg-background"></div>
                <div v-if="cardErrors" class="text-sm text-red-500 mt-2">
                  {{ cardErrors }}
                </div>
              </div>
              <div class="flex items-center text-sm text-muted-foreground">
                <Shield class="h-4 w-4 mr-2" />
                <span>Your payment information is secure and encrypted</span>
              </div>
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button @click="showUpdatePaymentDialog = false" variant="outline">Cancel</Button>
          <Button
            :disabled="!cardComplete"
            @click="handleUpdatePaymentMethod"
          >
            Update Payment Method
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>
