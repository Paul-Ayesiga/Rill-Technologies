<script setup lang="ts">
import { useColorMode } from '@vueuse/core';
import { ref, computed, watch, onMounted } from 'vue';
import { toast } from 'vue-sonner';
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
  Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger
} from '@/components/ui/dropdown-menu';
import {
  Check, X, CreditCard, Zap, Users, Bot, Database, LineChart, Shield,
  Sun, Moon, Laptop, Menu
} from 'lucide-vue-next';

import { Toaster } from '@/components/ui/sonner'
import AdBanner from '@/components/AdBanner.vue';

// Get page props
const page = usePage();
const colorMode = useColorMode();

// Create a ref to track the selected theme
const selectedTheme = ref(colorMode.value);

// Theme state
//Computed property for theme text
const themeText = computed(() => {
    switch (selectedTheme.value) {
        case 'light': return 'Light';
        case 'dark': return 'Dark';
        case 'auto': return 'System';
        default: return 'System';
    }
});

// Handle plan selection
const handlePlanSelection = (plan: UIPlan) => {
  const planName = plan.name.toLowerCase();

  // We'll fetch the price ID from the backend when redirecting
  // This ensures we always use the correct price ID from Stripe

  // For Enterprise plan, show contact form or redirect to contact page
  if (planName === 'enterprise') {
    // Show a toast notification using Sonner
    toast('Enterprise Plan', {
      description: "Please contact our sales team for Enterprise plans. We'll create a custom solution for your business needs.",
      duration: 5000,
      action: {
        label: 'Contact Sales',
        onClick: () => window.location.href = 'mailto:sales@example.com'
      }
    });
    return;
  }

  if (page.props.auth.user) {
    // Use Inertia's router to navigate to billing page with plan selection
    router.visit(route('billing'), {
      data: {
        plan: planName
      },
      preserveState: false
    });
  } else {
    // Use Inertia's router to navigate to register with plan selection
    router.visit(route('login'), {
      data: {
        plan: planName
      },
      preserveState: false
    });
  }
};

// Watch for changes to selectedTheme and update colorMode
watch(selectedTheme, (newTheme: string) => {
    if (newTheme === 'light' || newTheme === 'dark' || newTheme === 'auto') {
        colorMode.value = newTheme;
    }
});

// Watch for external changes to colorMode
watch(colorMode, (newMode: string) => {
    selectedTheme.value = newMode;
});

// Theme icon component based on current theme
const ThemeIcon = computed(() => {
    switch (selectedTheme.value) {
        case 'light': return Sun;
        case 'dark': return Moon;
        default: return Laptop;
    }
});

// Content visibility state
const contentVisible = ref(false);

// Show content after a short delay
setTimeout(() => {
  contentVisible.value = true;
}, 100);

// Function to fetch plans from the backend
const fetchPlans = async () => {
  isLoadingPlans.value = true;

  try {
    // Fetch plans from the backend using Inertia's built-in axios instance
    // which already has CSRF token handling set up
    const response = await axios.get(route('subscription.plans'));

    if (response.data && Array.isArray(response.data)) {
      stripePlans.value = response.data;
      console.log('Fetched plans from Stripe:', stripePlans.value);
    } else {
      console.warn('Invalid response format from plans API');
      // Use fallback plans
    }
  } catch (error) {
    console.error('Error fetching plans:', error);
    toast.error('Error loading plans', {
      description: 'Could not load pricing plans. Using default pricing information.'
    });
    // Use fallback plans on error
  } finally {
    isLoadingPlans.value = false;
  }
};

// Fetch plans when component is mounted
onMounted(() => {
  fetchPlans();
});

// Billing period state
const billingPeriod = ref('monthly');

// Loading state for plans
const isLoadingPlans = ref(true);

// Fallback pricing plans data (used if API fetch fails)
const fallbackPlans = [
  {
    name: 'Starter',
    description: 'Perfect for individuals and small projects',
    monthlyPrice: 29,
    yearlyPrice: 29 * 10, // 10 months for the price of 12 (16.67% discount)
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
    ],
    cta: 'Get Started',
    popular: false,
    color: 'bg-blue-500'
  },
  {
    name: 'Professional',
    description: 'For growing businesses and teams',
    monthlyPrice: 79,
    yearlyPrice: 79 * 10, // 10 months for the price of 12 (16.67% discount)
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
    ],
    cta: 'Get Started',
    popular: true,
    color: 'bg-purple-500'
  },
  {
    name: 'Enterprise',
    description: 'For large organizations with advanced needs',
    monthlyPrice: 199,
    yearlyPrice: 199 * 10, // 10 months for the price of 12 (16.67% discount)
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
    ],
    cta: 'Contact Sales',
    popular: false,
    color: 'bg-green-500'
  }
];

// Reactive plans data that will be populated from API
const stripePlans = ref([]);

// Define the plan interface
interface StripePlan {
  id: string;
  name: string;
  price: string | number;
  interval: string;
  currency: string;
  features?: Array<{name: string, included: boolean}>;
}

interface UIPlan {
  id?: string;
  name: string;
  description: string;
  monthlyPrice: number;
  yearlyPrice: number;
  interval?: string;
  currency?: string;
  features: Array<{name: string, included: boolean}>;
  cta: string;
  popular: boolean;
  color: string;
}

// Computed property to get the plans to display
const plans = computed<UIPlan[]>(() => {
  if (stripePlans.value && stripePlans.value.length > 0) {
    // Map Stripe plans to the format expected by the UI
    return stripePlans.value.map((plan: StripePlan) => {
      // Find matching fallback plan to get description and other UI properties
      const fallbackPlan = fallbackPlans.find(fp =>
        fp.name.toLowerCase() === plan.name.toLowerCase()
      ) || fallbackPlans[0];

      // Calculate yearly price (monthly price * 10 for 2 months free)
      const monthlyPrice = parseFloat(plan.price as string);
      const yearlyPrice = monthlyPrice * 10; // 10 months for the price of 12 (16.67% discount)

      return {
        id: plan.id,
        name: plan.name,
        description: fallbackPlan.description,
        monthlyPrice: monthlyPrice,
        yearlyPrice: yearlyPrice,
        interval: plan.interval,
        currency: plan.currency,
        features: plan.features || fallbackPlan.features,
        cta: plan.name.toLowerCase() === 'enterprise' ? 'Contact Sales' : 'Get Started',
        popular: fallbackPlan.popular,
        color: fallbackPlan.color
      };
    });
  }

  // Return fallback plans if no Stripe plans are available
  return fallbackPlans;
});

// Computed property to calculate the average savings percentage across all plans
const averageSavings = computed<number>(() => {
  if (plans.value.length === 0) return 17; // Default value if no plans are available

  // Calculate savings for each plan
  const savingsPercentages = plans.value.map((plan: UIPlan) =>
    calculateSavings(plan.monthlyPrice, plan.yearlyPrice)
  );

  // Calculate the average savings
  const totalSavings = savingsPercentages.reduce((sum: number, percentage: number) => sum + percentage, 0);
  const averageSavingsValue = Math.round(totalSavings / savingsPercentages.length);

  return averageSavingsValue;
});

// FAQ data
const faqs = [
  {
    question: 'What happens when I reach my interaction limit?',
    answer: 'When you reach your monthly interaction limit, you can either upgrade to a higher plan or purchase additional interactions. Your agents will continue to function, but you\'ll be notified about the overage.'
  },
  {
    question: 'Can I switch between plans?',
    answer: 'Yes, you can upgrade or downgrade your plan at any time. When upgrading, the new features will be available immediately. When downgrading, the changes will take effect at the start of your next billing cycle.'
  },
  {
    question: 'How do you count interactions?',
    answer: 'An interaction is counted each time a user sends a message to your AI agent and receives a response. Multiple messages in a single conversation are counted as separate interactions.'
  },
  {
    question: 'Do you offer a free trial?',
    answer: 'Yes, we offer a 14-day free trial on all plans. No credit card is required to start your trial, and you can upgrade to a paid plan at any time.'
  },
  {
    question: 'What payment methods do you accept?',
    answer: 'We accept all major credit cards (Visa, Mastercard, American Express), as well as PayPal. For Enterprise plans, we also offer invoicing options.'
  },
  {
    question: 'Can I get a refund if I\'m not satisfied?',
    answer: 'We offer a 30-day money-back guarantee on all plans. If you\'re not satisfied with our service, contact our support team within 30 days of your purchase for a full refund.'
  }
];

// Calculate savings
const calculateSavings = (monthlyPrice: number, yearlyPrice: number): number => {
  const monthlyCostPerYear = monthlyPrice * 12;
  const savings = monthlyCostPerYear - yearlyPrice;
  const savingsPercentage = Math.round((savings / monthlyCostPerYear) * 100);
  return savingsPercentage;
};
</script>

<template>
  <Toaster />
  <Head title="Pricing - Rill Technologies" />

  <!-- Ad Banner -->
  <AdBanner
    position="left"
    adId="pricing-ad-1"
    adUrl="http://localhost:8000/dashboard"
    adImageUrl="/ads/ad2.png"
    adText="Premium Offer"
  />

  <div class="bg-gray-900 min-h-screen">
    <div class="relative top-0">
      <div class="absolute inset-0 overflow-hidden">
        <img src="/home2.jpg" alt="AI technology background" class="w-full h-full object-cover opacity-70">
        <div class="absolute inset-0 bg-gradient-to-b from-transparent to-gray-900 dark:to-gray-800"></div>
      </div>
      <header class="shadow-md backdrop-blur-lg sticky top-0 z-50 transition-colors duration-300">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
          <Link :href="route('home')" class="flex items-center">
            <img src="/logo.png" alt="Rill Technologies Logo" class="h-8 w-auto mr-2" />
            <span class="text-white">Rill Technologies</span>
          </Link>

          <!-- Mobile Menu -->
          <div class="flex md:hidden">
            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon"
                  class="text-white hover:bg-white/10">
                  <Menu class="h-5 w-5" />
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="end" class="w-56 bg-gray-900/95 backdrop-blur-md border border-gray-700 rounded-xl shadow-xl p-1">
                <div class="px-2 py-1.5 text-xs font-medium text-gray-400 border-b border-gray-800 mb-1">
                  Theme Settings
                </div>
                <DropdownMenuItem @select="selectedTheme = 'light'" class="cursor-pointer rounded-lg transition-colors hover:bg-gray-800 focus:bg-gray-800 my-1">
                  <Sun class="h-4 w-4 text-orange-500 mx-auto" />
                  <!-- <span class="text-white">Light</span> -->
                </DropdownMenuItem>
                <DropdownMenuItem @select="selectedTheme = 'dark'" class="cursor-pointer rounded-lg transition-colors hover:bg-gray-800 focus:bg-gray-800 my-1">
                  <Moon class="h-4 w-4 text-indigo-500 mx-auto" />
                  <!-- <span class="text-white">Dark</span> -->
                </DropdownMenuItem>
                <DropdownMenuItem @select="selectedTheme = 'auto'" class="cursor-pointer rounded-lg transition-colors hover:bg-gray-800 focus:bg-gray-800 my-1">
                  <Laptop class="h-4 w-4 text-gray-400 mx-auto" />
                  <!-- <span class="text-white">System</span> -->
                </DropdownMenuItem>

                <div class="h-px bg-gray-800 my-2" />

                <DropdownMenuItem asChild>
                  <Link :href="route('home')"
                    class="w-full py-2.5 px-3 rounded-lg text-white font-medium justify-center transition-all duration-300">
                    Home
                  </Link>
                </DropdownMenuItem>

                <DropdownMenuItem asChild>
                  <Link :href="route('pricing')"
                    class="w-full py-2.5 px-3 rounded-lg bg-primary/20 text-white font-medium justify-center transition-all duration-300 border-l-2 border-primary">
                    Pricing
                  </Link>
                </DropdownMenuItem>

                <div class="h-px bg-gray-800 my-2" />

                <template v-if="page.props.auth.user">
                  <div class="px-2 py-3">
                    <DropdownMenuItem asChild>
                      <Link :href="route('dashboard')"
                        class="w-full py-2.5 px-3 rounded-lg bg-gradient-to-r from-primary to-purple-600 text-white hover:from-primary/90 hover:to-purple-500 font-medium justify-center transition-all duration-300 shadow-md hover:shadow-lg hover:scale-[1.02]">
                        Dashboard
                      </Link>
                    </DropdownMenuItem>
                  </div>
                </template>
                <template v-else>
                  <div class="px-2 py-3">
                    <DropdownMenuItem asChild class="mb-2">
                      <Link :href="route('login')"
                        class="w-full py-2.5 px-3 rounded-lg bg-white/10 backdrop-blur-sm border border-white/20 text-white hover:bg-white/20 font-medium justify-center transition-all duration-300 shadow-md hover:shadow-lg">
                        Log in
                      </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                      <Link :href="route('register')"
                        class="w-full py-2.5 px-3 rounded-lg bg-gradient-to-r from-primary to-purple-600 text-white hover:from-primary/90 hover:to-purple-500 font-medium justify-center transition-all duration-300 shadow-md hover:shadow-lg hover:scale-[1.02]">
                        Register
                      </Link>
                    </DropdownMenuItem>
                  </div>
                </template>
              </DropdownMenuContent>
            </DropdownMenu>
          </div>

          <!-- Desktop Menu -->
          <div class="hidden md:flex items-center space-x-4">
            <Link :href="route('home')" class="text-white hover:text-gray-300 transition-colors">
              Home
            </Link>
            <Link :href="route('pricing')" class="relative text-white hover:text-gray-300 transition-colors font-medium">
              Pricing
              <div class="absolute -bottom-1 left-0 right-0 h-0.5 bg-primary rounded-full"></div>
            </Link>

            <DropdownMenu>
              <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" class="gap-2">
                  <component :is="ThemeIcon" class="h-4 w-4 text-orange-400" />
                  <!-- <span class="hidden sm:inline-block text-blue-600">{{ themeText }}</span> -->
                </Button>
              </DropdownMenuTrigger>
              <DropdownMenuContent align="center" class="w-0">
                <DropdownMenuItem @select="selectedTheme = 'light'" class="cursor-pointer ">
                  <Sun class="h-4 w-4 text-orange-500 mx-auto" />
                  <!-- <span>Light</span> -->
                </DropdownMenuItem>
                <DropdownMenuItem @select="selectedTheme = 'dark'" class="cursor-pointer">
                  <Moon class="h-4 w-4 text-indigo-500 mx-auto" />
                  <!-- <span>Dark</span> -->
                </DropdownMenuItem>
                <DropdownMenuItem @select="selectedTheme = 'auto'" class="cursor-pointer">
                  <Laptop class="h-4 w-4 text-gray-500 dark:text-gray-400 mx-auto" />
                  <!-- <span>System</span> -->
                </DropdownMenuItem>
              </DropdownMenuContent>
            </DropdownMenu>

            <template v-if="page.props.auth.user">
              <Link :href="route('dashboard')"
                class="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-sm hover:bg-primary/90 h-9 px-4 py-2">
                Dashboard
              </Link>
            </template>
            <template v-else>
              <div class="flex items-center gap-3">
                <Link :href="route('login')"
                  class="inline-flex items-center justify-center rounded-full md:rounded-md text-sm font-medium transition-all duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/50 disabled:pointer-events-none disabled:opacity-50 bg-white/10 backdrop-blur-sm border border-white/20 text-white hover:bg-white/20 h-10 md:h-9 px-5 md:px-4 py-2 shadow-lg hover:shadow-xl">
                  <span class="relative z-10">Log in</span>
                </Link>
                <Link :href="route('register')"
                  class="inline-flex items-center justify-center rounded-full md:rounded-md text-sm font-medium transition-all duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/50 disabled:pointer-events-none disabled:opacity-50 bg-gradient-to-r from-primary to-purple-600 text-white hover:from-primary/90 hover:to-purple-500 h-10 md:h-9 px-5 md:px-4 py-2 shadow-lg hover:shadow-xl hover:scale-105">
                  <span class="relative z-10">Register</span>
                </Link>
              </div>
            </template>
          </div>
        </div>
      </header>

    <!-- Hero Section -->
    <section class="pt-24 pb-16 md:pt-40 md:pb-24 overflow-hidden bg-gray-900 dark:bg-gray-800">
      <div class="container mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
        <div class="flex flex-col items-center text-center">
          <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-4 max-w-3xl dark:text-gray-100">
            Simple, <span class="text-primary">Transparent</span> Pricing
          </h1>
          <p class="text-lg md:text-xl text-gray-300 mb-8 max-w-2xl mx-auto dark:text-gray-400">
            Choose the perfect plan for your business needs. All plans include a 14-day free trial.
          </p>

          <!-- Billing Toggle -->
          <div class="flex justify-center mb-12">
            <Tabs v-model="billingPeriod" class="w-full max-w-xs">
              <TabsList class="grid grid-cols-2">
                <TabsTrigger value="monthly">Monthly</TabsTrigger>
                <TabsTrigger value="yearly">Yearly (Save {{ averageSavings }}%)</TabsTrigger>
              </TabsList>
            </Tabs>
          </div>

          <!-- Pricing Cards -->
          <div v-if="isLoadingPlans" class="grid md:grid-cols-3 gap-8 max-w-6xl mx-auto">
            <!-- Loading Skeleton -->
            <div v-for="i in 3" :key="i" class="relative">
              <Card class="h-full flex flex-col animate-pulse">
                <CardHeader>
                  <div class="flex items-center justify-center w-12 h-12 rounded-full mb-4 bg-gray-300 dark:bg-gray-700"></div>
                  <div class="h-6 bg-gray-300 dark:bg-gray-700 rounded w-1/3 mb-2"></div>
                  <div class="h-4 bg-gray-200 dark:bg-gray-800 rounded w-2/3"></div>
                </CardHeader>
                <CardContent class="flex-grow">
                  <div class="mb-6">
                    <div class="h-8 bg-gray-300 dark:bg-gray-700 rounded w-1/2 mb-2"></div>
                    <div class="h-4 bg-gray-200 dark:bg-gray-800 rounded w-1/3"></div>
                  </div>
                  <div class="space-y-3">
                    <div v-for="j in 5" :key="j" class="flex items-start">
                      <div class="rounded-full bg-gray-300 dark:bg-gray-700 p-1 mr-2 h-6 w-6"></div>
                      <div class="h-4 bg-gray-200 dark:bg-gray-800 rounded w-3/4"></div>
                    </div>
                  </div>
                </CardContent>
                <CardFooter>
                  <div class="h-10 bg-gray-300 dark:bg-gray-700 rounded w-full"></div>
                </CardFooter>
              </Card>
            </div>
          </div>

          <div v-else class="grid md:grid-cols-3 gap-8 max-w-6xl mx-auto">
            <div v-for="(plan, index) in plans" :key="index" class="relative">
              <!-- Popular Badge -->
              <div v-if="plan.popular" class="absolute -top-4 left-0 right-0 flex justify-center">
                <Badge class="bg-primary text-white px-3 py-1">Most Popular</Badge>
              </div>

              <Card :class="[plan.popular ? 'border-primary shadow-lg' : '', 'h-full flex flex-col']">
                <CardHeader>
                  <div class="flex items-center justify-center w-12 h-12 rounded-full mb-4" :class="plan.color">
                    <Bot v-if="index === 0" class="h-6 w-6 text-white" />
                    <Users v-else-if="index === 1" class="h-6 w-6 text-white" />
                    <Database v-else class="h-6 w-6 text-white" />
                  </div>
                  <CardTitle>{{ plan.name }}</CardTitle>
                  <CardDescription>{{ plan.description }}</CardDescription>
                </CardHeader>
                <CardContent class="flex-grow">
                  <div class="mb-6">
                    <div class="flex items-end">
                      <span class="text-4xl font-bold">
                        ${{ billingPeriod === 'monthly' ? plan.monthlyPrice : plan.yearlyPrice }}
                      </span>
                      <span class="text-muted-foreground ml-2">
                        /{{ billingPeriod === 'monthly' ? 'month' : 'year' }}
                      </span>
                    </div>
                    <div v-if="billingPeriod === 'yearly'" class="text-sm text-green-600 mt-1">
                      Save {{ calculateSavings(plan.monthlyPrice, plan.yearlyPrice) }}% with annual billing
                    </div>
                  </div>

                  <ul class="space-y-3">
                    <li v-for="(feature, featureIndex) in plan.features" :key="featureIndex" class="flex items-start">
                      <div v-if="feature.included" class="rounded-full bg-green-500/10 p-1 mr-2">
                        <Check class="h-4 w-4 text-green-500" />
                      </div>
                      <div v-else class="rounded-full bg-red-500/10 p-1 mr-2">
                        <X class="h-4 w-4 text-red-500" />
                      </div>
                      <span :class="feature.included ? 'text-foreground' : 'text-muted-foreground'">
                        {{ feature.name }}
                      </span>
                    </li>
                  </ul>
                </CardContent>
                <CardFooter>
                  <Button
                    :variant="plan.popular ? 'default' : 'outline'"
                    class="w-full"
                    @click="handlePlanSelection(plan)"
                  >
                    {{ plan.cta }}
                  </Button>
                </CardFooter>
              </Card>
            </div>
          </div>
        </div>
      </div>
    </section>
    </div> <!-- Close the relative container -->

    <!-- Features Section -->
    <section class="py-16 bg-background">
      <div class="container mx-auto px-4">
        <h2 class="text-3xl font-bold text-center mb-12">All Plans Include</h2>
        <div class="grid md:grid-cols-3 gap-8">
          <div class="text-center">
            <div class="bg-primary/10 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-4">
              <Zap class="h-6 w-6 text-primary" />
            </div>
            <h3 class="text-xl font-semibold mb-2">Fast & Reliable</h3>
            <p class="text-muted-foreground">Our AI agents respond in milliseconds with 99.9% uptime guarantee.</p>
          </div>
          <div class="text-center">
            <div class="bg-primary/10 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-4">
              <Shield class="h-6 w-6 text-primary" />
            </div>
            <h3 class="text-xl font-semibold mb-2">Secure & Private</h3>
            <p class="text-muted-foreground">Enterprise-grade security with data encryption at rest and in transit.</p>
          </div>
          <div class="text-center">
            <div class="bg-primary/10 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-4">
              <LineChart class="h-6 w-6 text-primary" />
            </div>
            <h3 class="text-xl font-semibold mb-2">Detailed Analytics</h3>
            <p class="text-muted-foreground">Gain insights into your agents' performance and user interactions.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-16 md:py-24 bg-background">
      <div class="container mx-auto px-4">
        <h2 class="text-3xl font-bold text-center mb-12">Frequently Asked Questions</h2>
        <div class="grid md:grid-cols-2 gap-8 max-w-4xl mx-auto">
          <div v-for="(faq, index) in faqs" :key="index" class="border-b pb-4">
            <h3 class="text-xl font-semibold mb-2">{{ faq.question }}</h3>
            <p class="text-muted-foreground">{{ faq.answer }}</p>
          </div>
        </div>

        <div class="text-center mt-12">
          <p class="text-lg mb-4">Still have questions?</p>
          <Button variant="outline">Contact Sales</Button>
        </div>
      </div>
    </section>

    <!-- CTA Section -->
    <section class="py-16 bg-secondary text-primary">
      <div class="container mx-auto px-4 text-center">
        <h2 class="text-3xl font-bold mb-4">Ready to get started?</h2>
        <p class="text-xl opacity-90 max-w-2xl mx-auto mb-8">
          Start your 14-day free trial today. No credit card required.
        </p>
        <Button size="lg" variant="secondary" asChild>
          <Link :href="route('register')">
            Start Free Trial
            <CreditCard class="ml-2 h-4 w-4" />
          </Link>
        </Button>
      </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white pt-16 pb-8 dark:bg-gray-800">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-12">
                <div>
                    <div class="flex items-center mb-6">
                        <img src="/logo.png" alt="Rill Technologies Logo" class="h-8 w-auto mr-2" />
                        <span class="text-2xl font-bold text-white">Rill Technologies</span>
                    </div>
                    <p class="text-gray-400 mb-6 dark:text-gray-500">Empowering businesses with intelligent AI agents
                        tailored to specific needs.</p>
                    <div class="flex space-x-4">
                        <a href="#"
                            class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center hover:bg-primary/20">
                            <i class="fab fa-twitter text-white"></i>
                        </a>
                        <a href="#"
                            class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center hover:bg-primary/20">
                            <i class="fab fa-linkedin text-white"></i>
                        </a>
                        <a href="#"
                            class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center hover:bg-primary/20">
                            <i class="fab fa-facebook text-white"></i>
                        </a>
                        <a href="#"
                            class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center hover:bg-primary/20">
                            <i class="fab fa-instagram text-white"></i>
                        </a>
                    </div>
                </div>
                <div>
                    <h3 class="font-semibold text-lg mb-6">Product</h3>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-gray-400 hover:text-white">Features</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Pricing</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Case Studies</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Documentation</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">API Reference</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-semibold text-lg mb-6">Company</h3>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-gray-400 hover:text-white">About Us</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Careers</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Blog</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Press</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-semibold text-lg mb-6">Support</h3>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-gray-400 hover:text-white">Help Center</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Community</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Status</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Tutorials</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Resources</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-500 text-sm mb-4 md:mb-0">© 2025 Rill Technologies. All rights reserved.</p>
                <div class="flex space-x-6">
                    <a href="#" class="text-gray-500 hover:text-white text-sm">Privacy Policy</a>
                    <a href="#" class="text-gray-500 hover:text-white text-sm">Terms of Service</a>
                    <a href="#" class="text-gray-500 hover:text-white text-sm">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>
  </div>
</template>
