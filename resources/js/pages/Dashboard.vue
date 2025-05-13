<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import CommandMenu from '@/components/CommandMenu.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, watchEffect, onBeforeUnmount } from 'vue';
import { toast } from 'vue-sonner';

// No Stripe declaration needed
import {
  Card, CardContent, CardDescription, CardHeader, CardTitle
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Bot, Plus, LineChart, ArrowUpRight, ArrowDownRight,
  Command as CommandIcon, ArrowRight, Users, CreditCard,
  Check, X
} from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

// State for loading and active tab
const isLoading = ref(true);
const activeTab = ref('overview');

// Define Agent interface
interface Agent {
  id: number;
  name: string;
  status: string;
  type: string;
  interactions: number;
  performance: number;
  last_active_at: string | null;
  last_active?: string;
  created_at?: string;
  updated_at?: string;
  user_id?: number;
}

// Get agents data from props
const props = defineProps<{
  agents?: Agent[];
  name: string;
  quote?: {
    message: string;
    author: string;
  };
}>();

// Convert agents from props to reactive ref
const agents = ref<Agent[]>(props.agents || []);

// Training progress tracking for agents
const agentProgress = ref<Record<string, number>>({});

// Animate progress for training agents only
watchEffect((cleanupFn: (cb: () => void) => void) => {
  // Create a map to track timers for each agent
  const timers: Record<number, number> = {};

  // Only process agents in training status
  const trainingAgents = agents.value.filter((agent: Agent) => agent.status === 'training');

  // For each agent in training status, animate their progress
  trainingAgents.forEach((agent: Agent) => {
    // Set initial progress if not already set
    if (agentProgress.value[agent.id] === undefined) {
      agentProgress.value[agent.id] = 25;
    }

    // Animate progress for training agents
    const timer = window.setInterval(() => {
      // Increment by random amount between 1-5%
      const increment = Math.floor(Math.random() * 5) + 1;

      // Update progress
      const currentProgress = agentProgress.value[agent.id] || 0;
      const newProgress = currentProgress + increment;

      // Check if training is complete (reached 100%)
      if (newProgress >= 100) {
        // Set progress to exactly 100%
        agentProgress.value[agent.id] = 100;

        // Clear the interval as training is complete
        window.clearInterval(timer);

        // Update agent status from 'training' to 'active'
        const index = agents.value.findIndex((a: Agent) => a.id === agent.id);
        if (index !== -1) {
          // Update the agent's status to 'active'
          agents.value[index] = {
            ...agents.value[index],
            status: 'active',
            last_active_at: new Date().toISOString()
          };

          // Show a success toast
          toast.success('Training complete', {
            description: `${agent.name} has completed training and is now active.`
          });
        }
      } else {
        // Update progress normally
        agentProgress.value[agent.id] = newProgress;
      }
    }, 2000); // Update every 2 seconds

    timers[agent.id] = timer;
  });

  // Cleanup function to clear all timers
  cleanupFn(() => {
    Object.values(timers).forEach(timer => window.clearInterval(timer));
  });
});

// Mock data for insights
const insights = ref({
  totalInteractions: 2099,
  averageRating: 4.7,
  conversionRate: 23,
  responseTime: 1.2,
  weeklyGrowth: 12,
  monthlyGrowth: -3
});

// No subscription-related code needed for dashboard highlights

// No Stripe-related code needed for dashboard highlights

// Simulate loading
onMounted(() => {
  setTimeout(() => {
    isLoading.value = false;
  }, 1000);
});

// Computed properties for insights
const interactionsChange = computed(() => insights.value.weeklyGrowth > 0);
const responseTimeChange = computed(() => insights.value.monthlyGrowth < 0);

// Command dialog - only keeping what's needed for dashboard highlights
const showCommandDialog = ref(false);

// Handle command selection
const handleCommandSelect = (action: string) => {
  switch (action) {
    case 'new-agent':
      // Redirect to agents page with new agent dialog open
      router.visit(route('agents'), {
        data: { new: true },
        preserveState: true,
        replace: true
      });
      break;
    case 'train-agent':
      // Redirect to agents page with training dialog open
      router.visit(route('agents'), {
        data: { train: true },
        preserveState: true,
        replace: true
      });
      break;
    case 'overview':
    case 'insights':
      activeTab.value = action;
      break;
    case 'agents':
      router.visit(route('agents'));
      break;
    case 'billing':
      router.visit(route('billing'));
      break;
    default:
      // Handle other actions if needed
      break;
  }
};

// No agent management functions needed for dashboard highlights

// Status badge color mapping
const getStatusColor = (status: string): string => {
  switch(status) {
    case 'active': return 'bg-green-500';
    case 'paused': return 'bg-amber-500';
    case 'training': return 'bg-blue-500';
    default: return 'bg-gray-500';
  }
};

// Format last active time with better human-readable format
const formatLastActive = (agent: Agent | null): string => {
  // If no agent is provided
  if (!agent) {
    return 'Never';
  }

  // If the agent has never been active
  if (!agent.last_active_at) {
    return 'Never';
  }

  // If the agent is currently active
  if (agent.status === 'active') {
    return 'Currently active';
  }

  // Use the last_active attribute which is already formatted as "X time ago"
  return agent.last_active || 'Never';
};
</script>

<template>
  <Head title="Dashboard" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <!-- Command Menu -->
    <CommandMenu
      v-model:open="showCommandDialog"
      @select="handleCommandSelect"
    />

    <div class="flex h-full flex-1 flex-col gap-4 p-4">
      <!-- Dashboard Header with Actions -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-2">
        <div>
          <h1 class="text-3xl font-bold">Agent Dashboard</h1>
          <p class="text-muted-foreground">Monitor and manage your AI agents</p>
        </div>
        <div class="flex gap-3">
          <!-- Command Button -->
          <Button variant="outline" size="sm" @click="showCommandDialog = true">
            <CommandIcon class="h-4 w-4 mr-2" />
            <span>Page search</span>
            <span class="ml-2 text-xs text-muted-foreground">⌘K</span>
          </Button>


          <!-- Create New Agent Button -->
          <Button @click="() => router.visit(route('agents'), { data: { new: true } })">
            <Plus class="mr-2 h-4 w-4" />
            New Agent
          </Button>
        </div>
      </div>

      <!-- Main Dashboard Tabs -->
      <Tabs v-model="activeTab" class="w-full">
        <TabsList class="mb-4">
          <TabsTrigger value="overview">Overview</TabsTrigger>
          <TabsTrigger value="agents" @click="() => router.visit(route('agents'))">Agents</TabsTrigger>
          <TabsTrigger value="insights">Insights</TabsTrigger>
          <TabsTrigger value="subscription" @click="() => router.visit(route('billing'))">Billing</TabsTrigger>
        </TabsList>

        <!-- Overview Tab -->
        <TabsContent value="overview">
          <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <!-- Stats Cards -->
            <Card>
              <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Total Agents</CardTitle>
                <Bot class="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div class="text-2xl font-bold">{{ agents.length }}</div>
                <p class="text-xs text-muted-foreground">
                  {{ agents.filter(a => a.status === 'active').length }} active
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Total Interactions</CardTitle>
                <Users class="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div class="text-2xl font-bold">{{ insights.totalInteractions }}</div>
                <p class="text-xs text-muted-foreground flex items-center">
                  <span :class="interactionsChange ? 'text-green-500' : 'text-red-500'" class="flex items-center">
                    <span v-if="interactionsChange"><ArrowUpRight class="h-3 w-3 mr-1" /></span>
                    <span v-else><ArrowDownRight class="h-3 w-3 mr-1" /></span>
                    {{ Math.abs(insights.weeklyGrowth) }}%
                  </span>
                  <span class="ml-1">from last week</span>
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Avg. Response Time</CardTitle>
                <LineChart class="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div class="text-2xl font-bold">{{ insights.responseTime }}s</div>
                <p class="text-xs text-muted-foreground flex items-center">
                  <span :class="responseTimeChange ? 'text-green-500' : 'text-red-500'" class="flex items-center">
                    <span v-if="responseTimeChange"><ArrowDownRight class="h-3 w-3 mr-1" /></span>
                    <span v-else><ArrowUpRight class="h-3 w-3 mr-1" /></span>
                    {{ Math.abs(insights.monthlyGrowth) }}%
                  </span>
                  <span class="ml-1">from last month</span>
                </p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle class="text-sm font-medium">Subscription</CardTitle>
                <CreditCard class="h-4 w-4 text-muted-foreground" />
              </CardHeader>
              <CardContent>
                <div class="text-2xl font-bold">Manage Billing</div>
                <p class="text-xs text-muted-foreground">
                  <Button variant="link" class="p-0 h-auto text-xs" @click="() => router.visit(route('billing'))">
                    View subscription details
                  </Button>
                </p>
              </CardContent>
            </Card>
          </div>


          <!-- Recent Activity -->
          <Card class="mt-4">
            <CardHeader>
              <CardTitle>Recent Agent Activity</CardTitle>
              <CardDescription>Your agents' recent interactions and performance</CardDescription>
            </CardHeader>
            <CardContent>
              <div v-if="isLoading" class="space-y-3">
                <Skeleton class="h-12 w-full" />
                <Skeleton class="h-12 w-full" />
                <Skeleton class="h-12 w-full" />
              </div>
              <div v-else class="space-y-4">
                <div v-for="agent in agents" :key="agent.id" class="flex items-center justify-between border-b pb-3">
                  <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                      <Bot class="h-5 w-5 text-primary" />
                    </div>
                    <div>
                      <p class="font-medium">{{ agent.name }}</p>
                      <p class="text-sm text-muted-foreground">
                        Last active: {{ formatLastActive(agent) }}
                      </p>
                    </div>
                  </div>
                  <div class="flex items-center gap-2">
                    <div class="flex items-center">
                      <div class="h-2 w-2 rounded-full mr-2" :class="getStatusColor(agent.status)"></div>
                      <span class="text-sm capitalize">{{ agent.status }}</span>
                    </div>
                    <Button variant="ghost" size="icon" @click="() => router.visit(route('agents'))">
                      <ArrowRight class="h-4 w-4" />
                    </Button>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <!-- Agents Tab -->
        <TabsContent value="agents">
          <Card>
            <CardHeader>
              <div class="flex justify-between items-center">
                <div>
                  <CardTitle>My AI Agents</CardTitle>
                  <CardDescription>Manage and monitor all your AI agents</CardDescription>
                </div>
                <Button @click="() => router.visit(route('agents'))">
                  View All Agents
                  <ArrowRight class="ml-2 h-4 w-4" />
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              <div v-if="isLoading" class="space-y-3">
                <Skeleton class="h-20 w-full" />
                <Skeleton class="h-20 w-full" />
                <Skeleton class="h-20 w-full" />
              </div>
              <div v-else class="space-y-4">
                <div v-for="agent in agents" :key="agent.id" class="flex flex-col sm:flex-row sm:items-center justify-between border rounded-lg p-4">
                  <div class="flex items-center gap-3 mb-3 sm:mb-0">
                    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                      <Bot class="h-6 w-6 text-primary" />
                    </div>
                    <div class="w-full">
                      <div class="flex items-center gap-2">
                        <p class="font-medium">{{ agent.name }}</p>
                        <div class="h-2 w-2 rounded-full" :class="getStatusColor(agent.status)"></div>
                        <span class="text-xs capitalize">{{ agent.status }}</span>
                      </div>
                      <p class="text-sm text-muted-foreground">Type: {{ agent.type }}</p>

                      <!-- Status Progress Bar - Only for training agents -->
                      <div v-if="agent.status === 'training'" class="mt-2 mb-1">
                        <div class="flex justify-between items-center mb-1">
                          <span class="text-xs font-medium">Training Progress</span>
                          <span class="text-xs">{{ Math.round(agentProgress[agent.id] || 25) }}%</span>
                        </div>
                        <Progress
                          :value="agentProgress[agent.id] || 25"
                          class="h-2 transition-all duration-500 bg-blue-100 [&>div]:bg-blue-500 [&>div]:animate-pulse"
                        />
                      </div>

                      <div class="flex items-center gap-4 mt-1">
                        <span class="text-xs">Interactions: {{ agent.interactions }}</span>
                        <span class="text-xs">Performance: {{ agent.performance }}%</span>
                      </div>
                    </div>
                  </div>
                  <div class="flex items-center">
                    <div class="flex items-center">
                      <div class="h-2 w-2 rounded-full mr-2" :class="getStatusColor(agent.status)"></div>
                      <span class="text-sm capitalize">{{ agent.status }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <!-- Insights Tab -->
        <TabsContent value="insights">
          <Card>
            <CardHeader>
              <CardTitle>Performance Insights</CardTitle>
              <CardDescription>Analytics and metrics for your AI agents</CardDescription>
            </CardHeader>
            <CardContent>
              <div v-if="isLoading" class="space-y-3">
                <Skeleton class="h-[200px] w-full" />
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <Skeleton class="h-[100px] w-full" />
                  <Skeleton class="h-[100px] w-full" />
                </div>
              </div>
              <div v-else>
                <!-- Chart Placeholder -->
                <div class="h-[200px] w-full bg-muted/20 rounded-lg flex items-center justify-center mb-6">
                  <div class="text-center">
                    <LineChart class="h-10 w-10 mx-auto mb-2 text-muted-foreground" />
                    <p class="text-sm text-muted-foreground">Interaction Analytics</p>
                  </div>
                </div>

                <!-- Key Metrics -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="border rounded-lg p-4">
                    <div class="flex justify-between items-center mb-2">
                      <h3 class="font-medium">User Satisfaction</h3>
                      <Badge variant="outline">{{ insights.averageRating }}/5</Badge>
                    </div>
                    <Progress
                      :value="(insights.averageRating / 5) * 100"
                      class="h-2 bg-indigo-100 [&>div]:bg-indigo-500 transition-all duration-500"
                    />
                    <p class="text-xs text-muted-foreground mt-2">Based on user feedback</p>
                  </div>

                  <div class="border rounded-lg p-4">
                    <div class="flex justify-between items-center mb-2">
                      <h3 class="font-medium">Conversion Rate</h3>
                      <Badge variant="outline">{{ insights.conversionRate }}%</Badge>
                    </div>
                    <Progress
                      :value="insights.conversionRate"
                      class="h-2 bg-teal-100 [&>div]:bg-teal-500 transition-all duration-500"
                    />
                    <p class="text-xs text-muted-foreground mt-2">Sales agent performance</p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <!-- Subscription Tab -->
        <TabsContent value="subscription">
          <div class="grid gap-6">
            <!-- Current Subscription -->
            <Card>
              <CardHeader>
                <CardTitle>Subscription Details</CardTitle>
                <CardDescription>Manage your plan and billing information</CardDescription>
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
                          <h3 class="font-medium text-lg">{{ subscription.name }} Plan</h3>
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
                          ${{ subscription.price }}/{{ subscription.interval }} •
                          {{ subscription.ends_at ? 'Ends on ' + new Date(subscription.ends_at).toLocaleDateString() :
                             subscription.trial_ends_at ? 'Trial ends on ' + new Date(subscription.trial_ends_at).toLocaleDateString() :
                             'Next billing on ' + new Date(subscription.next_billing_date).toLocaleDateString() }}
                        </p>

                        <!-- Payment Method -->
                        <div v-if="subscription.card_brand" class="flex items-center mt-3 text-sm text-muted-foreground">
                          <CreditCard class="h-4 w-4 mr-2" />
                          <span class="capitalize">{{ subscription.card_brand }}</span> ending in {{ subscription.card_last_four }}
                        </div>
                      </div>

                      <div class="flex flex-wrap gap-2">
                        <Button @click="() => router.visit(route('billing'))">
                          Manage Billing
                          <ArrowRight class="ml-2 h-4 w-4" />
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
                    <p class="text-sm text-muted-foreground mb-4">
                      Subscribe to a plan to access premium features.
                    </p>
                    <Button size="sm" @click="() => router.visit(route('billing'))">
                      View Pricing Plans
                      <ArrowRight class="ml-2 h-4 w-4" />
                    </Button>
                  </div>

                  <!-- No pricing lists here, just a button to navigate to billing page -->
                </div>
              </CardContent>
            </Card>

            <!-- Billing History -->
            <Card>
              <CardHeader>
                <CardTitle>Billing History</CardTitle>
                <CardDescription>View and download your invoices</CardDescription>
              </CardHeader>
              <CardContent>
                <div v-if="isLoading" class="space-y-3">
                  <Skeleton class="h-[200px] w-full" />
                </div>
                <div v-else-if="subscription && subscription.invoices && subscription.invoices.length > 0" class="border rounded-lg overflow-hidden">
                  <table class="min-w-full divide-y divide-border">
                    <thead class="bg-muted/50">
                      <tr>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Date</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Amount</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Status</th>
                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-muted-foreground uppercase tracking-wider">Invoice</th>
                      </tr>
                    </thead>
                    <tbody class="bg-card divide-y divide-border">
                      <tr v-for="(invoice, i) in subscription.invoices" :key="i">
                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ new Date(invoice.date).toLocaleDateString() }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">${{ (invoice.amount / 100).toFixed(2) }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                          <Badge :class="{
                            'bg-green-500': invoice.status === 'paid',
                            'bg-amber-500': invoice.status === 'open',
                            'bg-red-500': invoice.status === 'failed',
                            'bg-blue-500': invoice.status === 'draft'
                          }">
                            {{ invoice.status }}
                          </Badge>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                          <Button variant="ghost" size="sm" asChild>
                            <a :href="invoice.invoice_pdf" target="_blank" rel="noopener noreferrer">
                              Download
                            </a>
                          </Button>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div v-else class="text-center py-8 border rounded-lg">
                  <div class="mx-auto w-12 h-12 rounded-full bg-muted flex items-center justify-center mb-3">
                    <CreditCard class="h-6 w-6 text-muted-foreground" />
                  </div>
                  <h3 class="text-lg font-medium mb-1">No invoices yet</h3>
                  <p class="text-sm text-muted-foreground">
                    Your billing history will appear here once you've been charged.
                  </p>
                </div>
              </CardContent>
            </Card>

            <!-- Usage Stats -->
            <Card>
              <CardHeader>
                <CardTitle>Usage Statistics</CardTitle>
                <CardDescription>Monitor your current usage</CardDescription>
              </CardHeader>
              <CardContent>
                <div v-if="isLoading" class="space-y-3">
                  <Skeleton class="h-[100px] w-full" />
                </div>
                <div v-else class="space-y-6">
                  <!-- Agent Usage -->
                  <div>
                    <div class="flex justify-between items-center mb-2">
                      <div>
                        <h3 class="text-sm font-medium">AI Agents</h3>
                        <p class="text-xs text-muted-foreground">
                          {{ agents.length }} of {{ subscription && subscription.name === 'Enterprise' ? 'Unlimited' :
                             subscription && subscription.name === 'Professional' ? '5' :
                             subscription && subscription.name === 'Starter' ? '1' : '5' }} agents used
                        </p>
                      </div>
                      <Badge variant="outline">
                        {{ subscription && subscription.name === 'Enterprise' ? 'Unlimited' :
                           Math.round((agents.length / (subscription && subscription.name === 'Professional' ? 5 :
                                                       subscription && subscription.name === 'Starter' ? 1 : 5)) * 100) + '%' }}
                      </Badge>
                    </div>
                    <Progress
                      :value="subscription && subscription.name === 'Enterprise' ? 100 :
                              Math.min(100, (agents.length / (subscription && subscription.name === 'Professional' ? 5 :
                                                             subscription && subscription.name === 'Starter' ? 1 : 5)) * 100)"
                      class="h-2 bg-blue-100 [&>div]:bg-blue-500"
                    />
                  </div>

                  <!-- Interaction Usage -->
                  <div>
                    <div class="flex justify-between items-center mb-2">
                      <div>
                        <h3 class="text-sm font-medium">Interactions</h3>
                        <p class="text-xs text-muted-foreground">
                          {{ insights.totalInteractions }} of {{ subscription && subscription.name === 'Enterprise' ? 'Unlimited' :
                             subscription && subscription.name === 'Professional' ? '25,000' :
                             subscription && subscription.name === 'Starter' ? '5,000' : '25,000' }} interactions used
                        </p>
                      </div>
                      <Badge variant="outline">
                        {{ subscription && subscription.name === 'Enterprise' ? 'Unlimited' :
                           Math.round((insights.totalInteractions / (subscription && subscription.name === 'Professional' ? 25000 :
                                                                    subscription && subscription.name === 'Starter' ? 5000 : 25000)) * 100) + '%' }}
                      </Badge>
                    </div>
                    <Progress
                      :value="subscription && subscription.name === 'Enterprise' ? 100 :
                              Math.min(100, (insights.totalInteractions / (subscription && subscription.name === 'Professional' ? 25000 :
                                                                          subscription && subscription.name === 'Starter' ? 5000 : 25000)) * 100)"
                      class="h-2 bg-purple-100 [&>div]:bg-purple-500"
                    />
                  </div>
                </div>
              </CardContent>
            </Card>
          </div>
        </TabsContent>
      </Tabs>
    </div>

    <!-- No modals needed for dashboard highlights -->
  </AppLayout>
</template>
