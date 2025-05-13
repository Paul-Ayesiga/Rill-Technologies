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

import {
  Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader,
  DialogTitle, DialogTrigger
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Skeleton } from '@/components/ui/skeleton';
import {
  CreditCard, Check, X, AlertCircle, Shield
} from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Billing',
        href: '/billing',
    },
];

// State for loading
const isLoading = ref(true);
const isSyncing = ref(false);

// Get subscription data from props
const props = defineProps<{
  subscription?: any;
  plans?: any[];
}>();

// Dialog states for subscription management
const showChangePlanDialog = ref(false);
const showCancelDialog = ref(false);
const showUpdatePaymentDialog = ref(false);
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

// Default plans in case the plans prop is empty
const defaultPlans = [
  {
    id: 'price_1RFkCl06DSRI9z5wXnLQZJnO',
    name: 'Starter',
    price: 29.99,
    interval: 'month',
    currency: 'usd',
    features: [
      { name: '1 AI Agent', included: true },
      { name: '5,000 interactions per month', included: true },
      { name: 'Basic analytics', included: true },
      { name: 'Email support', included: true },
      { name: 'File uploads (up to 50MB)', included: true },
      { name: 'Website training', included: true },
      { name: 'API access', included: false },
      { name: 'Custom branding', included: false },
      { name: 'Advanced analytics', included: false },
      { name: 'Priority support', included: false },
    ]
  },
  {
    id: 'price_1RFkDK06DSRI9z5wJnLQZJnO',
    name: 'Professional',
    price: 79.99,
    interval: 'month',
    currency: 'usd',
    features: [
      { name: '5 AI Agents', included: true },
      { name: '25,000 interactions per month', included: true },
      { name: 'Advanced analytics', included: true },
      { name: 'Priority email support', included: true },
      { name: 'File uploads (up to 200MB)', included: true },
      { name: 'Website training', included: true },
      { name: 'API access', included: true },
      { name: 'Custom branding', included: true },
      { name: 'Team collaboration', included: false },
      { name: '24/7 phone support', included: false },
    ]
  },
  {
    id: 'price_1RFkDr06DSRI9z5wJnLQZJnO',
    name: 'Enterprise',
    price: 199.99,
    interval: 'month',
    currency: 'usd',
    features: [
      { name: 'Unlimited AI Agents', included: true },
      { name: 'Unlimited interactions', included: true },
      { name: 'Advanced analytics & reporting', included: true },
      { name: '24/7 priority support', included: true },
      { name: 'Unlimited file uploads', included: true },
      { name: 'Website & API training', included: true },
      { name: 'Advanced API access', included: true },
      { name: 'Custom branding & white labeling', included: true },
      { name: 'Team collaboration', included: true },
      { name: 'Dedicated account manager', included: true },
    ]
  }
];

// Use the plans from props or fallback to default plans
const availablePlans = computed(() => props.plans && props.plans.length > 0 ? props.plans : defaultPlans);

// Stripe Elements variables
const stripe = ref<any>(null);
const cardElement = ref<any>(null);
const cardErrors = ref<string>('');
const stripeLoading = ref(true);
const cardComplete = ref(false);

// Load Stripe.js and initialize Elements
const initializeStripe = () => {
  try {
    // Only initialize if the dialog is open
    if (!showChangePlanDialog.value) return;

    // Check if we're in a browser environment
    if (typeof window === 'undefined') {
      console.error('Window is not defined, cannot initialize Stripe');
      return;
    }

    // Check if Stripe is already loaded
    if (window.Stripe) {
      stripeLoading.value = false;

      try {
        // Initialize Stripe with your publishable key from environment variables
        const stripeKey = import.meta.env.VITE_STRIPE_KEY;
        // console.log('Using Stripe key:', stripeKey);
        stripe.value = window.Stripe(stripeKey);

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
            const cardElementContainer = document.getElementById('card-element');
            if (cardElementContainer) {
              cardElement.value.mount('#card-element');

              // Listen for changes in the card element
              cardElement.value.on('change', (event: any) => {
                cardComplete.value = event.complete;
                cardErrors.value = event.error ? event.error.message : '';
              });
            } else {
              console.warn('Card element container not found');
            }
          } catch (error) {
            console.error('Error initializing Stripe elements:', error);
            cardErrors.value = 'Error initializing payment form. Please try again.';
          }
        }, 100);
      } catch (error) {
        console.error('Error initializing Stripe:', error);
        cardErrors.value = 'Error initializing payment system. Please try again.';
      }
    } else {
      // If Stripe.js is not loaded yet, try again in 100ms
      setTimeout(initializeStripe, 100);
    }
  } catch (error) {
    console.error('Unexpected error in initializeStripe:', error);
    cardErrors.value = 'An unexpected error occurred. Please try again.';
  }
};

// Load Stripe.js script
const loadStripeScript = () => {
  try {
    // Check if we're in a browser environment
    if (typeof document !== 'undefined') {
      const script = document.createElement('script');
      script.src = 'https://js.stripe.com/v3/';
      script.async = true;
      document.head.appendChild(script);
    } else {
      console.error('Document is not defined, cannot load Stripe script');
    }
  } catch (error) {
    console.error('Error loading Stripe script:', error);
  }
};

// Watch for dialog open/close to initialize/cleanup Stripe
watch(() => showChangePlanDialog.value, (isOpen: boolean) => {
  try {
    if (isOpen) {
      // Load Stripe.js if not already loaded
      if (typeof window !== 'undefined' && !window.Stripe) {
        loadStripeScript();
      }
      // Initialize Stripe Elements
      initializeStripe();
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
    console.error('Error in dialog watch handler:', error);
  }
});

// Watch for update payment dialog open/close
watch(() => showUpdatePaymentDialog.value, (isOpen: boolean) => {
  try {
    if (isOpen) {
      // Load Stripe.js if not already loaded
      if (typeof window !== 'undefined' && !window.Stripe) {
        loadStripeScript();
      }

      // Wait for Stripe to be loaded
      const checkStripe = () => {
        if (window.Stripe) {
          stripeLoading.value = false;

          try {
            // Initialize Stripe with your publishable key
            const stripeKey = import.meta.env.VITE_STRIPE_KEY ;
            stripe.value = window.Stripe(stripeKey);

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
                }
              } catch (error) {
                console.error('Error initializing Stripe elements for update payment:', error);
                cardErrors.value = 'Error initializing payment form. Please try again.';
              }
            }, 100);
          } catch (error) {
            console.error('Error initializing Stripe for update payment:', error);
            cardErrors.value = 'Error initializing payment system. Please try again.';
          }
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
  }
});

// Helper function to safely get plan name
const getPlanName = (planId: string | null): string => {
  if (!planId) return 'Selected';

  try {
    // In the script section, we need to use .value for computed properties
    const plans = availablePlans.value;
    if (!plans || !Array.isArray(plans)) return 'Selected';

    const plan = plans.find((p: any) => p.id === planId);
    return plan?.name || 'Selected';
  } catch (error) {
    console.error('Error getting plan name:', error);
    return 'Selected';
  }
};

// Helper function to safely get plan price
const getPlanPrice = (planId: string | null): string => {
  if (!planId) return '0.00';

  try {
    const plans = availablePlans.value;
    if (!plans || !Array.isArray(plans)) return '0.00';

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
    if (!plans || !Array.isArray(plans)) return 'month';

    const plan = plans.find((p: any) => p.id === planId);
    return plan?.interval || 'month';
  } catch (error) {
    console.error('Error getting plan interval:', error);
    return 'month';
  }
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

    // Format the date using toLocaleDateString
    return date.toLocaleDateString(undefined, {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    });
  } catch (error) {
    console.error('Error formatting date:', error, dateString);
    return 'N/A';
  }
};

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
      isSyncing.value = false;
    },
    onError: (errors) => {
      toast.error('Error syncing subscription', {
        description: errors.message || 'There was an error syncing your subscription with Stripe.'
      });
      isSyncing.value = false;
    }
  });
};

// Create a payment method with Stripe
const createPaymentMethod = async (): Promise<string | null> => {
  try {
    if (!stripe.value || !cardElement.value) {
      toast.error('Payment processing error', {
        description: 'Stripe has not been initialized properly.'
      });
      return null;
    }

    try {
      const { paymentMethod, error } = await stripe.value.createPaymentMethod({
        type: 'card',
        card: cardElement.value,
      });

      if (error) {
        toast.error('Payment error', {
          description: error.message || 'There was an error processing your payment method.'
        });
        return null;
      }

      if (!paymentMethod || !paymentMethod.id) {
        toast.error('Payment error', {
          description: 'No payment method was created. Please try again.'
        });
        return null;
      }

      return paymentMethod.id;
    } catch (error: any) {
      toast.error('Payment processing error', {
        description: error.message || 'There was an error processing your payment.'
      });
      console.error('Error creating payment method:', error);
      return null;
    }
  } catch (error: any) {
    toast.error('Unexpected error', {
      description: 'An unexpected error occurred. Please try again.'
    });
    console.error('Unexpected error in createPaymentMethod:', error);
    return null;
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
  }, 1000);
});
</script>

<template>
  <Head title="Billing" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
      <!-- Billing Header -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-2">
        <div>
          <h1 class="text-3xl font-bold">Billing & Subscription</h1>
          <p class="text-muted-foreground">Manage your subscription and payment methods</p>
        </div>
      </div>

      <div class="grid gap-6">
        <!-- Current Subscription -->
        <Card>
          <CardHeader>
            <div class="flex justify-between items-center">
              <div>
                <CardTitle>Subscription Details</CardTitle>
                <CardDescription>Manage your plan and billing information</CardDescription>
              </div>
              <Button
                size="sm"
                variant="outline"
                @click="syncSubscription"
                :disabled="isSyncing"
              >
                <template v-if="isSyncing">
                  <div class="animate-spin mr-2 h-4 w-4 border-2 border-current border-t-transparent rounded-full"></div>
                  Syncing...
                </template>
                <template v-else>
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
              <!-- Current Plan -->
              <div class="border rounded-lg p-4 mb-6">
                <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                  <div>
                    <div class="flex items-center gap-2">
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
                    </div>
                    <p class="text-sm text-muted-foreground mt-1">
                      ${{ subscription.price || '0.00' }}/{{ subscription.interval || 'month' }} •
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

                  <div class="flex flex-wrap gap-2">
                    <Button
                      v-if="subscription.stripe_status === 'active'"
                      variant="outline"
                      size="sm"
                      @click="showChangePlanDialog = true"
                    >
                      Change Plan
                    </Button>
                    <Button
                      variant="outline"
                      size="sm"
                      @click="showUpdatePaymentDialog = true"
                    >
                      Update Payment
                    </Button>
                    <Button
                      v-if="subscription.stripe_status === 'active'"
                      variant="outline"
                      size="sm"
                      class="text-red-500 hover:bg-red-50"
                      @click="showCancelDialog = true"
                    >
                      Cancel Plan
                    </Button>
                    <Button
                      v-if="subscription.stripe_status === 'canceled' && !subscription.ends_at"
                      variant="default"
                      size="sm"
                    >
                      Resume Subscription
                    </Button>
                  </div>
                </div>
              </div>

              <!-- Plan Features -->
              <div v-if="subscription.features" class="border rounded-lg p-4">
                <h3 class="font-medium mb-4">Plan Features</h3>
                <ul class="space-y-2">
                  <li v-for="(feature, index) in subscription.features" :key="index" class="flex items-center">
                    <div v-if="feature.included" class="h-5 w-5 rounded-full bg-primary/10 flex items-center justify-center mr-2">
                      <Check class="h-3 w-3 text-primary" />
                    </div>
                    <div v-else class="h-5 w-5 rounded-full bg-gray-100 flex items-center justify-center mr-2">
                      <X class="h-3 w-3 text-gray-400" />
                    </div>
                    <span class="text-sm" :class="{ 'text-muted-foreground': !feature.included }">
                      {{ feature.name }}
                    </span>
                  </li>
                </ul>
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

        <!-- Available Plans -->
        <div v-if="!subscription" class="mt-6">
          <Card>
            <CardHeader>
              <CardTitle>Available Plans</CardTitle>
              <CardDescription>Choose a plan that fits your needs</CardDescription>
            </CardHeader>
            <CardContent>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div v-for="plan in availablePlans" :key="plan.id"
                  class="border rounded-lg p-6 flex flex-col hover:shadow-md transition-all duration-200"
                  :class="{ 'border-primary ring-2 ring-primary/20': selectedPlan === plan.id }"
                >
                  <div class="mb-4">
                    <h3 class="text-xl font-bold">{{ plan.name }}</h3>
                    <div class="flex items-end mt-2">
                      <span class="text-3xl font-bold">${{ plan.price }}</span>
                      <span class="text-muted-foreground ml-1">/ {{ plan.interval }}</span>
                    </div>
                  </div>

                  <ul class="space-y-3 mb-6 flex-grow">
                    <li v-for="(feature, i) in plan.features" :key="i" class="flex items-start">
                      <div v-if="feature.included" class="rounded-full p-0.5 mr-2 mt-0.5 text-green-500">
                        <Check class="h-4 w-4" />
                      </div>
                      <div v-else class="rounded-full p-0.5 mr-2 mt-0.5 text-gray-400">
                        <X class="h-4 w-4" />
                      </div>
                      <span :class="{ 'text-muted-foreground': !feature.included }">
                        {{ feature.name }}
                      </span>
                    </li>
                  </ul>

                  <Button
                    class="w-full mt-auto"
                    :variant="selectedPlan === plan.id ? 'default' : 'outline'"
                    @click="() => {
                      selectedPlan = plan.id;
                      showChangePlanDialog = true;
                    }"
                  >
                    Get Started
                  </Button>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        <!-- Payment Methods -->
        <Card>
          <CardHeader>
            <div class="flex justify-between items-center">
              <div>
                <CardTitle>Payment Methods</CardTitle>
                <CardDescription>Manage your payment information</CardDescription>
              </div>
              <Button size="sm" as-child>
                <a href="/payment/methods">Manage Payment Methods</a>
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            <div v-if="isLoading" class="space-y-3">
              <Skeleton class="h-[60px] w-full" />
            </div>
            <div v-else-if="subscription && subscription.card_brand" class="border rounded-lg p-4">
              <div class="flex justify-between items-center">
                <div class="flex items-center">
                  <div class="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center mr-3">
                    <CreditCard class="h-5 w-5 text-primary" />
                  </div>
                  <div>
                    <p class="font-medium capitalize">{{ subscription.card_brand }}</p>
                    <p class="text-sm text-muted-foreground">•••• •••• •••• {{ subscription.card_last_four }}</p>
                  </div>
                </div>
                <Badge variant="outline">Default</Badge>
              </div>
            </div>
            <div v-else class="text-center py-6 border rounded-lg">
              <div class="mx-auto w-12 h-12 rounded-full bg-muted flex items-center justify-center mb-3">
                <CreditCard class="h-6 w-6 text-muted-foreground" />
              </div>
              <h3 class="text-lg font-medium mb-1">No Payment Methods</h3>
              <p class="text-sm text-muted-foreground mb-4">
                Add a payment method to subscribe to a plan.
              </p>
              <Button as-child size="sm">
                <a href="/payment/methods">Add Payment Method</a>
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>

    <!-- Change Plan Dialog -->
    <Dialog v-model:open="showChangePlanDialog">
      <DialogContent class="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle>{{ subscription ? 'Change Plan' : 'Subscribe to Plan' }}</DialogTitle>
          <DialogDescription>
            {{ subscription ? 'Update your subscription to a different plan.' : 'Choose a plan and enter your payment details.' }}
          </DialogDescription>
        </DialogHeader>
        <div class="py-4">
          <div class="mb-4">
            <Label>Selected Plan</Label>
            <div class="border rounded-lg p-4 mt-2">
              <div v-if="selectedPlan" class="flex justify-between items-center">
                <div>
                  <p class="font-medium">{{ getPlanName(selectedPlan) }}</p>
                  <p class="text-sm text-muted-foreground">
                    ${{ getPlanPrice(selectedPlan) }}/{{ getPlanInterval(selectedPlan) }}
                  </p>
                </div>
                <Button variant="outline" size="sm" @click="showChangePlanDialog = false">
                  Change
                </Button>
              </div>
              <div v-else class="text-center py-2">
                <p class="text-sm text-muted-foreground">No plan selected</p>
              </div>
            </div>
          </div>

          <div v-if="!subscription || !subscription.card_brand">
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
            :disabled="!selectedPlan || (!subscription && !cardComplete)"
            @click="async () => {
              // Set the plan in the form
              subscriptionForm.plan = selectedPlan;

              // If we already have a payment method on file, just update the plan
              if (subscription && subscription.card_brand) {
                // Get the plan name safely
                const planName = getPlanName(selectedPlan);

                // Submit the form using Inertia
                subscriptionForm.post(route('subscription.subscribe'), {
                  onSuccess: () => {
                    showChangePlanDialog.value = false;
                    toast.success('Plan updated', {
                      description: `Your subscription has been updated to the ${planName} plan.`
                    });
                  },
                  onError: (errors) => {
                    toast.error('Error updating plan', {
                      description: errors.message || 'There was an error processing your request.'
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
              const planName = getPlanName(selectedPlan);

              // Submit the form using Inertia
              subscriptionForm.post(route('subscription.subscribe'), {
                onSuccess: () => {
                  showChangePlanDialog.value = false;
                  toast.success('Subscription created', {
                    description: `Your ${planName} plan subscription has been processed successfully.`
                  });
                },
                onError: (errors) => {
                  toast.error('Error creating subscription', {
                    description: errors.message || 'There was an error processing your request.'
                  });
                }
              });
            }"
          >
            {{ subscription ? `Confirm ${getPlanName(selectedPlan)} Plan` : `Subscribe to ${getPlanName(selectedPlan)} Plan` }}
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
                    Your subscription will remain active until {{ formatDate(subscription.next_billing_date) }}. You'll continue to have access to all features until then.
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
            @click="() => {
              // Set the reason in the form
              cancelForm.reason = cancelReason;

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
                onError: (errors) => {
                  toast.error('Error canceling subscription', {
                    description: errors.message || 'There was an error processing your request.'
                  });
                }
              });
            }"
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
            @click="async () => {
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
                onError: (errors) => {
                  toast.error('Error updating payment method', {
                    description: errors.message || 'There was an error processing your request.'
                  });
                }
              });
            }"
          >
            Update Payment Method
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>
