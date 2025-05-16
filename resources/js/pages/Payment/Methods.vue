<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import AppLayout from '@/layouts/AppLayout.vue';
import {
  Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import {
  CreditCard, Shield, Trash2, Check, Plus
} from 'lucide-vue-next';
import CardBrandIcon from '@/components/CardBrandIcon.vue';

// Define breadcrumbs
const breadcrumbs = [
  {
    title: 'Dashboard',
    href: route('dashboard')
  },
  {
    title: 'Billing',
    href: route('billing')
  },
  {
    title: 'Payment Methods',
    href: route('payment.methods'),
    current: true
  }
];

// Define props
const props = defineProps<{
  paymentMethods: any[];
  setupIntent: {
    client_secret: string;
  };
  stripeKey: string;
}>();

// State
const isLoading = ref(false);
const showAddPaymentDialog = ref(false);
const showDeleteDialog = ref(false);
const selectedPaymentMethod = ref(null);
const cardErrors = ref('');
const stripe = ref<any>(null);
const cardElement = ref<any>(null);
const cardComplete = ref(false);
const stripeLoading = ref(true);

// Forms
const addPaymentForm = useForm({
  payment_method: '',
});

const deletePaymentForm = useForm({
  payment_method: '',
});

const defaultPaymentForm = useForm({
  payment_method: '',
});

// Load Stripe.js script
const loadStripeScript = () => {
  try {
    // Check if we're in a browser environment
    if (typeof document !== 'undefined') {
      // Check for internet connectivity
      if (!navigator.onLine) {
        console.error('No internet connection detected when loading Stripe script');
        cardErrors.value = 'No internet connection. Please check your connection and try again.';
        return;
      }

      const script = document.createElement('script');
      script.src = 'https://js.stripe.com/v3/';
      script.async = true;

      // Add error handling for script loading
      script.onerror = () => {
        console.error('Failed to load Stripe.js script');
        cardErrors.value = 'Unable to connect to payment service. Please check your internet connection and try again.';
      };

      document.head.appendChild(script);
    } else {
      console.error('Document is not defined, cannot load Stripe script');
      cardErrors.value = 'Payment system could not be initialized. Please try again later.';
    }
  } catch (error) {
    console.error('Error loading Stripe script:', error);
    cardErrors.value = 'Error loading payment system. Please check your internet connection and try again.';
  }
};

// Initialize Stripe Elements
const initializeStripe = () => {
  try {
    // Check if we're in a browser environment
    if (typeof window === 'undefined') {
      console.error('Window is not defined, cannot initialize Stripe');
      return;
    }

    // Check if Stripe is already loaded
    if (window.Stripe) {
      stripeLoading.value = false;

      try {
        // Check for internet connectivity before initializing Stripe
        if (!navigator.onLine) {
          console.error('No internet connection detected');
          cardErrors.value = 'No internet connection. Please check your connection and try again.';
          return;
        }

        try {
          stripe.value = window.Stripe(props.stripeKey);
        } catch (stripeError) {
          console.error('Error initializing Stripe object:', stripeError);
          cardErrors.value = 'Unable to connect to payment service. Please check your internet connection and try again.';
          return;
        }

        // Wait for the DOM to be updated
        setTimeout(() => {
          try {
            // Check again for internet connectivity
            if (!navigator.onLine) {
              console.error('No internet connection detected when creating elements');
              cardErrors.value = 'No internet connection. Please check your connection and try again.';
              return;
            }

            let elements;
            try {
              elements = stripe.value.elements({
                clientSecret: props.setupIntent.client_secret
              });
            } catch (elementsError) {
              console.error('Error creating Stripe elements:', elementsError);
              cardErrors.value = 'Unable to initialize payment form. Please check your internet connection and try again.';
              return;
            }

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
      } catch (error) {
        console.error('Error initializing Stripe:', error);
        cardErrors.value = 'Error initializing payment system. Please check your internet connection and try again.';
      }
    } else {
      // If Stripe.js is not loaded yet, try again in 100ms
      setTimeout(initializeStripe, 100);
    }
  } catch (error) {
    console.error('Unexpected error in initializeStripe:', error);
    cardErrors.value = 'An unexpected error occurred. Please check your internet connection and try again.';
  }
};

// Watch for dialog open/close to initialize/cleanup Stripe
watch(() => showAddPaymentDialog.value, (isOpen: boolean) => {
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

// Create a payment method with Stripe
const createPaymentMethod = async (): Promise<string | null> => {
  try {
    // Check for internet connectivity
    if (!navigator.onLine) {
      toast.error('No internet connection', {
        description: 'Please check your internet connection and try again.'
      });
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
        // Handle network errors specifically
        if (stripeError.type === 'network_error' || !navigator.onLine) {
          toast.error('Connection error', {
            description: 'Unable to connect to payment service. Please check your internet connection and try again.'
          });
        } else {
          toast.error('Payment processing error', {
            description: stripeError.message || 'There was an error processing your payment. Please try again.'
          });
        }
        console.error('Stripe API error:', stripeError);
        return null;
      }

      const { paymentMethod, error } = paymentMethodResult;

      if (error) {
        // Handle different types of Stripe errors
        if (error.type === 'network_error' || error.type === 'api_connection_error') {
          toast.error('Connection error', {
            description: 'Unable to connect to payment service. Please check your internet connection and try again.'
          });
        } else {
          toast.error('Payment error', {
            description: error.message || 'There was an error processing your payment method.'
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
      // Check if this is a network error
      if (error.name === 'NetworkError' || error.message?.includes('network') || !navigator.onLine) {
        toast.error('Connection error', {
          description: 'Unable to connect to payment service. Please check your internet connection and try again.'
        });
      } else {
        toast.error('Payment processing error', {
          description: error.message || 'There was an error processing your payment.'
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

// Methods
const addPaymentMethod = async () => {
  // Check for internet connectivity
  if (!navigator.onLine) {
    toast.error('No internet connection', {
      description: 'Please check your internet connection and try again.'
    });
    return;
  }

  // Create a payment method with Stripe
  const paymentMethodId = await createPaymentMethod();

  if (!paymentMethodId) {
    return; // Error already handled in createPaymentMethod
  }

  // Set the payment method ID in the form
  addPaymentForm.payment_method = paymentMethodId;

  // Submit the form
  addPaymentForm.post(route('payment.methods.store'), {
    onSuccess: () => {
      showAddPaymentDialog.value = false;
      toast.success('Payment method added', {
        description: 'Your payment method has been added successfully.'
      });
    },
    onError: (errors: any) => {
      toast.error('Error adding payment method', {
        description: errors.error || 'Please try again.'
      });
    }
  });
};

const confirmDeletePaymentMethod = (paymentMethodId: string) => {
  selectedPaymentMethod.value = paymentMethodId;
  showDeleteDialog.value = true;
};

const deletePaymentMethod = () => {
  deletePaymentForm.payment_method = selectedPaymentMethod.value;

  deletePaymentForm.delete(route('payment.methods.destroy'), {
    onSuccess: () => {
      showDeleteDialog.value = false;
      toast.success('Payment method removed', {
        description: 'Your payment method has been removed successfully.'
      });
    },
    onError: (errors: any) => {
      toast.error('Error removing payment method', {
        description: errors.error || 'Please try again.'
      });
    }
  });
};

const setDefaultPaymentMethod = (paymentMethodId: string) => {
  defaultPaymentForm.payment_method = paymentMethodId;

  defaultPaymentForm.put(route('payment.methods.default'), {
    onSuccess: () => {
      toast.success('Default payment method updated', {
        description: 'Your default payment method has been updated successfully.'
      });
    },
    onError: (errors: any) => {
      toast.error('Error updating default payment method', {
        description: errors.error || 'Please try again.'
      });
    }
  });
};
</script>

<template>
  <AppLayout :breadcrumbs="breadcrumbs">
    <Head title="Payment Methods" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-2">
        <div>
          <h1 class="text-3xl font-bold">Payment Methods</h1>
          <p class="text-muted-foreground">Manage your payment information</p>
        </div>
      </div>

      <Card>
        <CardHeader>
          <div class="flex justify-between items-center">
            <div>
              <CardTitle>Your Payment Methods</CardTitle>
              <CardDescription>Manage your credit cards and other payment methods</CardDescription>
            </div>
            <Button size="sm" @click="showAddPaymentDialog = true">
              <Plus class="h-4 w-4 mr-2" />
              Add Method
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <div v-if="isLoading" class="space-y-3">
            <Skeleton class="h-[100px] w-full" />
          </div>
          <div v-else-if="paymentMethods && paymentMethods.length > 0" class="space-y-4">
            <div v-for="method in paymentMethods" :key="method.id" class="border rounded-lg p-4 hover:shadow-md transition-all duration-200">
              <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex items-center">
                  <div class="mr-4">
                    <CardBrandIcon :brand="method.brand" size="lg" />
                  </div>
                  <div>
                    <div class="flex items-center">
                      <p class="font-medium text-lg capitalize">{{ method.brand }}</p>
                      <Badge v-if="method.is_default" variant="outline" class="ml-2 bg-green-50 text-green-700 border-green-200">Default</Badge>
                    </div>
                    <p class="text-sm text-muted-foreground mt-1">•••• {{ method.last4 }} • Expires {{ method.exp_month }}/{{ method.exp_year }}</p>
                  </div>
                </div>
                <div class="flex items-center gap-2 ml-auto sm:ml-0">
                  <Button
                    v-if="!method.is_default"
                    variant="outline"
                    size="sm"
                    @click="setDefaultPaymentMethod(method.id)"
                    :class="{ 'opacity-50 cursor-not-allowed': defaultPaymentForm.processing }"
                    class="border-green-200 text-green-700 hover:bg-green-50 hover:text-green-800"
                  >
                    <Check class="h-4 w-4 mr-2" />
                    Make Default
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    class="text-red-500 hover:bg-red-50 hover:text-red-600 border-red-200"
                    @click="confirmDeletePaymentMethod(method.id)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-12 border rounded-lg">
            <div class="flex justify-center space-x-2 mb-4">
              <CardBrandIcon brand="visa" />
              <CardBrandIcon brand="mastercard" />
              <CardBrandIcon brand="amex" />
            </div>
            <h3 class="text-xl font-medium mb-2">No Payment Methods</h3>
            <p class="text-sm text-muted-foreground mb-6 max-w-md mx-auto">
              Add a payment method to manage your subscriptions and billing information. Your payment details are securely processed by Stripe.
            </p>
            <Button
              @click="showAddPaymentDialog = true"
              class="px-6"
              size="lg"
            >
              <Plus class="h-4 w-4 mr-2" />
              Add Payment Method
            </Button>
          </div>
        </CardContent>
        <CardFooter>
          <div class="bg-muted/30 rounded-lg p-4 w-full">
            <div class="flex items-start">
              <Shield class="h-5 w-5 text-green-500 mr-2 mt-0.5" />
              <div>
                <h4 class="text-sm font-medium">Secure Payment Processing</h4>
                <p class="text-xs text-muted-foreground mt-1">
                  Your payment information is securely processed by Stripe. We never store your full card details on our servers.
                </p>
              </div>
            </div>
          </div>
        </CardFooter>
      </Card>
    </div>

    <!-- Add Payment Method Dialog -->
    <Dialog v-model:open="showAddPaymentDialog">
      <DialogContent class="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle>Add Payment Method</DialogTitle>
          <DialogDescription>
            Add a new credit card or payment method to your account.
          </DialogDescription>
        </DialogHeader>
        <div class="py-4">
          <div class="space-y-6">
            <!-- Card Element Container -->
            <div class="border rounded-lg p-5 bg-card">
              <div class="mb-4">
                <Label for="card-element" class="text-sm font-medium mb-2 block">Card Information</Label>
                <div id="card-element" class="p-4 border rounded-md bg-background min-h-[40px]"></div>
                <div v-if="cardErrors" class="text-sm text-red-500 mt-2">
                  {{ cardErrors }}
                </div>
              </div>

              <div class="grid gap-4">
                <div class="grid gap-2">
                  <Label for="name">Name on Card</Label>
                  <Input id="name" placeholder="John Doe" />
                </div>

                <div class="grid gap-2">
                  <Label for="address">Billing Address</Label>
                  <Input id="address" placeholder="123 Main St" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                  <div class="grid gap-2">
                    <Label for="city">City</Label>
                    <Input id="city" placeholder="New York" />
                  </div>
                  <div class="grid gap-2">
                    <Label for="zip">ZIP / Postal Code</Label>
                    <Input id="zip" placeholder="10001" />
                  </div>
                </div>
              </div>
            </div>

            <div class="flex items-center bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border border-green-100 dark:border-green-900/30">
              <Shield class="h-5 w-5 text-green-500 mr-3 flex-shrink-0" />
              <div>
                <h4 class="text-sm font-medium text-green-800 dark:text-green-300">Secure Payment Processing</h4>
                <p class="text-xs text-green-700 dark:text-green-400 mt-1">
                  Your payment information is securely processed by Stripe. We never store your full card details on our servers.
                </p>
              </div>
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button @click="showAddPaymentDialog = false" variant="outline">Cancel</Button>
          <Button
            @click="addPaymentMethod"
            :class="{ 'opacity-50 cursor-not-allowed': addPaymentForm.processing }"
          >
            <Plus class="h-4 w-4 mr-2" />
            Add Payment Method
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Delete Payment Method Dialog -->
    <Dialog v-model:open="showDeleteDialog">
      <DialogContent class="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle>Remove Payment Method</DialogTitle>
          <DialogDescription>
            Are you sure you want to remove this payment method?
          </DialogDescription>
        </DialogHeader>
        <div class="py-6">
          <div class="bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-900/30 rounded-lg p-4 mb-4">
            <div class="flex items-start">
              <Trash2 class="h-5 w-5 text-red-500 mr-3 mt-0.5 flex-shrink-0" />
              <div>
                <h4 class="text-sm font-medium text-red-800 dark:text-red-300">Warning</h4>
                <p class="text-sm text-red-700 dark:text-red-400 mt-1">
                  This action cannot be undone. If this is your default payment method, your subscription may be affected.
                </p>
              </div>
            </div>
          </div>

          <div class="border rounded-lg p-4">
            <p class="text-sm font-medium mb-2">What happens when you remove a payment method:</p>
            <ul class="text-sm text-muted-foreground space-y-2 ml-5 list-disc">
              <li>The payment method will be removed from your account</li>
              <li>If this is your default payment method, you'll need to set a new one</li>
              <li>Any active subscriptions using this payment method may be affected</li>
            </ul>
          </div>
        </div>
        <DialogFooter class="gap-2">
          <Button @click="showDeleteDialog = false" variant="outline" size="lg">Cancel</Button>
          <Button
            variant="destructive"
            size="lg"
            @click="deletePaymentMethod"
            :class="{ 'opacity-50 cursor-not-allowed': deletePaymentForm.processing }"
          >
            <Trash2 class="h-4 w-4 mr-2" />
            Remove Payment Method
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>
