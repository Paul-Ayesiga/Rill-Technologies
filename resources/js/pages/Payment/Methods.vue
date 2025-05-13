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

// Methods
const addPaymentMethod = () => {
  // In a real implementation, you would use Stripe.js to create a payment method
  // and then submit the form with the payment method ID
  addPaymentForm.payment_method = 'pm_card_visa'; // This is a test payment method
  
  addPaymentForm.post(route('payment.methods.store'), {
    onSuccess: () => {
      showAddPaymentDialog.value = false;
      toast.success('Payment method added', {
        description: 'Your payment method has been added successfully.'
      });
    },
    onError: (errors) => {
      toast.error('Error adding payment method', {
        description: errors.error || 'Please try again.'
      });
    }
  });
};

const confirmDeletePaymentMethod = (paymentMethodId) => {
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
    onError: (errors) => {
      toast.error('Error removing payment method', {
        description: errors.error || 'Please try again.'
      });
    }
  });
};

const setDefaultPaymentMethod = (paymentMethodId) => {
  defaultPaymentForm.payment_method = paymentMethodId;
  
  defaultPaymentForm.put(route('payment.methods.default'), {
    onSuccess: () => {
      toast.success('Default payment method updated', {
        description: 'Your default payment method has been updated successfully.'
      });
    },
    onError: (errors) => {
      toast.error('Error updating default payment method', {
        description: errors.error || 'Please try again.'
      });
    }
  });
};
</script>

<template>
  <AppLayout>
    <Head title="Payment Methods" />
    
    <div class="container py-6">
      <div class="mb-6">
        <h1 class="text-2xl font-bold">Payment Methods</h1>
        <p class="text-muted-foreground">Manage your payment methods</p>
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
            <div v-for="method in paymentMethods" :key="method.id" class="border rounded-lg p-4">
              <div class="flex justify-between items-center">
                <div class="flex items-center">
                  <div class="h-10 w-16 bg-gradient-to-br from-blue-500 to-purple-600 rounded-md flex items-center justify-center mr-3">
                    <CreditCard class="h-5 w-5 text-white" />
                  </div>
                  <div>
                    <div class="flex items-center">
                      <p class="font-medium capitalize">{{ method.brand }}</p>
                      <Badge v-if="method.is_default" variant="outline" class="ml-2">Default</Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">•••• {{ method.last4 }} • Expires {{ method.exp_month }}/{{ method.exp_year }}</p>
                  </div>
                </div>
                <div class="flex items-center gap-2">
                  <Button 
                    v-if="!method.is_default"
                    variant="outline" 
                    size="sm"
                    @click="setDefaultPaymentMethod(method.id)"
                    :class="{ 'opacity-50 cursor-not-allowed': defaultPaymentForm.processing }"
                  >
                    <Check class="h-4 w-4 mr-2" />
                    Make Default
                  </Button>
                  <Button 
                    variant="outline" 
                    size="sm"
                    class="text-red-500 hover:bg-red-50"
                    @click="confirmDeletePaymentMethod(method.id)"
                  >
                    <Trash2 class="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </div>
          </div>
          <div v-else class="text-center py-8">
            <div class="mx-auto w-12 h-12 rounded-full bg-muted flex items-center justify-center mb-3">
              <CreditCard class="h-6 w-6 text-muted-foreground" />
            </div>
            <h3 class="text-lg font-medium mb-1">No payment methods</h3>
            <p class="text-sm text-muted-foreground mb-4">
              Add a payment method to manage your subscriptions.
            </p>
            <Button @click="showAddPaymentDialog = true">
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
            Add a new credit card or payment method.
          </DialogDescription>
        </DialogHeader>
        <div class="py-4">
          <div class="space-y-4">
            <div class="grid gap-2">
              <Label for="card-number">Card Number</Label>
              <Input id="card-number" placeholder="•••• •••• •••• ••••" />
            </div>
            
            <div class="grid grid-cols-2 gap-4">
              <div class="grid gap-2">
                <Label for="expiry">Expiry Date</Label>
                <Input id="expiry" placeholder="MM/YY" />
              </div>
              <div class="grid gap-2">
                <Label for="cvc">CVC</Label>
                <Input id="cvc" placeholder="•••" />
              </div>
            </div>
            
            <div class="grid gap-2">
              <Label for="name">Name on Card</Label>
              <Input id="name" placeholder="John Doe" />
            </div>
          </div>
          
          <div class="flex items-center mt-4 bg-muted/30 rounded-lg p-3">
            <Shield class="h-5 w-5 text-green-500 mr-2" />
            <p class="text-xs text-muted-foreground">
              Your payment information is securely processed by Stripe.
            </p>
          </div>
        </div>
        <DialogFooter>
          <Button @click="showAddPaymentDialog = false" variant="outline">Cancel</Button>
          <Button 
            @click="addPaymentMethod"
            :class="{ 'opacity-50 cursor-not-allowed': addPaymentForm.processing }"
          >
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
        <div class="py-4">
          <div class="bg-destructive/5 border border-destructive/20 rounded-lg p-4">
            <div class="flex items-start">
              <Trash2 class="h-5 w-5 text-destructive mr-2 mt-0.5" />
              <div>
                <h4 class="text-sm font-medium">Warning</h4>
                <p class="text-xs text-muted-foreground mt-1">
                  This action cannot be undone. If this is your default payment method, your subscription may be affected.
                </p>
              </div>
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button @click="showDeleteDialog = false" variant="outline">Cancel</Button>
          <Button 
            variant="destructive"
            @click="deletePaymentMethod"
            :class="{ 'opacity-50 cursor-not-allowed': deletePaymentForm.processing }"
          >
            Remove Payment Method
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>
