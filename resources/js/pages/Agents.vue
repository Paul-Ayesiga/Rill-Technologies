<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { ref, computed, watchEffect, onMounted } from 'vue';
import { toast } from 'vue-sonner';
import { router } from '@inertiajs/vue3';

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
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
  Combobox, ComboboxAnchor, ComboboxInput, ComboboxList, ComboboxItem, ComboboxEmpty, ComboboxGroup, ComboboxItemIndicator
} from '@/components/ui/combobox';
import {
  DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger
} from '@/components/ui/dropdown-menu';
import {
  Bot, Plus, Upload, MoreVertical, Edit, Play, Pause, Zap, CheckIcon, LineChart,
  Rocket, CreditCard
} from 'lucide-vue-next';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Agents',
        href: '/agents',
    },
];

// State for loading
const isLoading = ref(true);

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

// Get agents and subscription status from props
const props = defineProps<{
  agents?: Agent[];
  subscription?: any;
  hasActiveSubscription?: boolean;
  flash?: {
    agent?: Agent;
    error?: string;
    message?: string;
  };
}>();

// Convert agents from props to reactive ref
const agents = ref<Agent[]>(props.agents || []);

// Check if there's a new agent in the flash data
onMounted(() => {
  // Check for agent in session flash data
  const flashAgent = window.sessionStorage.getItem('agent');
  if (flashAgent) {
    try {
      const newAgent = JSON.parse(flashAgent);
      console.log('New agent from session storage:', newAgent);

      // Check if the agent already exists in the local state
      const existingIndex = agents.value.findIndex((a: Agent) => a.id === newAgent.id);

      if (existingIndex !== -1) {
        // Update existing agent
        agents.value[existingIndex] = newAgent;
      } else {
        // Add new agent to the beginning of the array
        agents.value.unshift(newAgent);
      }

      // Show a success toast
      toast.success('Agent created successfully', {
        description: `${newAgent.name} has been created and is ready for training.`
      });

      // Clear the flash data
      window.sessionStorage.removeItem('agent');
    } catch (error) {
      console.error('Error parsing agent from session storage:', error);
    }
  }

  // Check for agent in props flash data
  else if (props.flash?.agent) {
    const newAgent = props.flash.agent;
    console.log('New agent from flash props:', newAgent);

    // Check if the agent already exists in the local state
    const existingIndex = agents.value.findIndex((a: Agent) => a.id === newAgent.id);

    if (existingIndex !== -1) {
      // Update existing agent
      agents.value[existingIndex] = newAgent;
    } else {
      // Add new agent to the beginning of the array
      agents.value.unshift(newAgent);
    }

    // Show a success toast
    toast.success('Agent created successfully', {
      description: `${newAgent.name} has been created and is ready for training.`
    });

    // Store the agent in session storage for page reloads
    window.sessionStorage.setItem('agent', JSON.stringify(newAgent));
  }

  // Check if there's an error message in the flash data
  if (props.flash?.error) {
    toast.error('Error', {
      description: props.flash.error
    });
  }
});

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

// Dialog states
const showNewAgentDialog = ref(false);
const showEditAgentDialog = ref(false);
const showViewDetailsDialog = ref(false);
const showAnalyticsDialog = ref(false);
const showDeleteDialog = ref(false);
const showTrainingDialog = ref(false);

// Selected agent for operations
const selectedAgent = ref<Agent | null>(null);

// Agent type options for combobox
const agentTypes = [
  { value: 'support', label: 'Customer Support' },
  { value: 'sales', label: 'Sales Assistant' },
  { value: 'analytics', label: 'Data Analyst' },
  { value: 'custom', label: 'Custom Agent' }
];

// Use Inertia form for agent creation
const form = useForm({
  name: '',
  type: 'support'
});

// Use Inertia form for agent editing
const editForm = useForm({
  name: '',
  type: '',
  status: ''
});

// Training dialog
const trainingMethod = ref('files');
const selectedFiles = ref<File[]>([]);
const websiteUrl = ref('');
const apiEndpoint = ref('');

// Simulate loading and check URL parameters
onMounted(() => {
  setTimeout(() => {
    isLoading.value = false;

    // Check URL parameters
    const urlParams = new URLSearchParams(window.location.search);

    // Open new agent dialog if 'new' parameter is present
    if (urlParams.has('new')) {
      showNewAgentDialog.value = true;

      // Remove the parameter from the URL without refreshing the page
      const newUrl = window.location.pathname +
                    (window.location.search ?
                      window.location.search.replace(/[?&]new=[^&]+(&|$)/, '$1') :
                      '');
      window.history.replaceState({}, document.title, newUrl);
    }

    // Open training dialog if 'train' parameter is present
    if (urlParams.has('train')) {
      showTrainingDialog.value = true;

      // Remove the parameter from the URL without refreshing the page
      const newUrl = window.location.pathname +
                    (window.location.search ?
                      window.location.search.replace(/[?&]train=[^&]+(&|$)/, '$1') :
                      '');
      window.history.replaceState({}, document.title, newUrl);
    }
  }, 1000);
});

// Methods
const createNewAgent = () => {
  // Check if user has an active subscription
  if (!props.hasActiveSubscription) {
    showNewAgentDialog.value = false;
    toast.error('Subscription required', {
      description: 'You need an active subscription to create agents. Please upgrade your plan.'
    });
    router.visit(route('billing'));
    return;
  }

  form.post(route('agents.store'), {
    preserveScroll: true,
    onSuccess: (page) => {
      // Get the agent from the response
      const newAgent = page?.props?.flash?.agent || null;

      if (newAgent) {
        console.log('New agent created:', newAgent);

        // Check if the agent already exists in the local state
        const existingIndex = agents.value.findIndex((a: Agent) => a.id === newAgent.id);

        if (existingIndex !== -1) {
          // Update existing agent
          agents.value[existingIndex] = newAgent;
        } else {
          // Add new agent to the beginning of the array
          agents.value.unshift(newAgent);
        }
      } else {
        console.warn('Agent created but not returned in response');
        // Refresh the page to get the updated list
        router.reload({ only: ['agents'] });
      }

      showNewAgentDialog.value = false;
      form.reset();
      toast.success('Agent created successfully', {
        description: `${form.name} has been created and is ready for training.`
      });
    },
    onError: (errors: any) => {
      // Check if this is a subscription required error
      if (errors.subscription_required) {
        toast.error('Subscription required', {
          description: errors.message || 'You need an active subscription to create agents. Please upgrade your plan.'
        });
        router.visit(route('billing'));
        return;
      }

      // Check if this is a subscription limit error
      if (errors.limit_reached) {
        toast.error('Agent limit reached', {
          description: errors.message || 'You have reached your plan\'s agent limit. Please upgrade to create more agents.'
        });
        router.visit(route('billing'));
        return;
      }

      // Generic error
      toast.error('Error creating agent', {
        description: errors.message || 'There was a problem creating your agent. Please try again.'
      });
    }
  });
};

// Open edit dialog with agent data
const openEditDialog = (agent: Agent) => {
  selectedAgent.value = agent;
  editForm.name = agent.name;
  editForm.type = agent.type;
  editForm.status = agent.status;
  showEditAgentDialog.value = true;
};

// Update agent
const updateAgent = () => {
  if (!selectedAgent.value) return;

  // Check if user has an active subscription
  if (!props.hasActiveSubscription) {
    showEditAgentDialog.value = false;
    toast.error('Subscription required', {
      description: 'You need an active subscription to update agents. Please upgrade your plan.'
    });
    router.visit(route('billing'));
    return;
  }

  const agentId = selectedAgent.value.id;

  editForm.put(route('agents.update', { agent: agentId }), {
    preserveScroll: true,
    onSuccess: (response: any) => {
      // Update the agent in the local state
      if (response?.data?.agent) {
        const updatedAgent = response.data.agent;
        const index = agents.value.findIndex((a: Agent) => a.id === updatedAgent.id);
        if (index !== -1) {
          agents.value[index] = updatedAgent;

          // If status changed to training, initialize progress
          if (updatedAgent.status === 'training') {
            agentProgress.value[agentId] = 25;
          }
        }
      } else {
        // Fallback to manual update if response doesn't contain the updated agent
        const index = agents.value.findIndex((a: Agent) => a.id === agentId);
        if (index !== -1) {
          const now = new Date().toISOString();
          const oldStatus = agents.value[index].status;
          const newStatus = editForm.status;

          // Update the agent in the local state
          agents.value[index] = {
            ...agents.value[index],
            name: editForm.name,
            type: editForm.type,
            status: newStatus,
            // Update last_active_at if status is changing from non-active to active
            last_active_at: (oldStatus !== 'active' && newStatus === 'active')
              ? now
              : agents.value[index].last_active_at
          };

          // If status changed to training, initialize progress
          if (newStatus === 'training') {
            agentProgress.value[agentId] = 25;

            // Show a toast notification
            toast.info('Training started', {
              description: `${editForm.name} is now being trained.`
            });
          }
        }
      }

      showEditAgentDialog.value = false;
      toast.success('Agent updated successfully', {
        description: `${editForm.name} has been updated.`
      });
    },
    onError: (errors: any) => {
      // Check if this is a subscription required error
      if (errors.subscription_required) {
        toast.error('Subscription required', {
          description: errors.message || 'You need an active subscription to update agents. Please upgrade your plan.'
        });
        router.visit(route('billing'));
        return;
      }

      // Generic error
      toast.error('Error updating agent', {
        description: errors.message || 'There was a problem updating your agent. Please try again.'
      });
    }
  });
};

// Open view details dialog
const openViewDetailsDialog = (agent: Agent) => {
  selectedAgent.value = agent;
  showViewDetailsDialog.value = true;
};

// Open analytics dialog
const openAnalyticsDialog = (agent: Agent) => {
  selectedAgent.value = agent;
  showAnalyticsDialog.value = true;
};

const handleFileChange = (event: Event) => {
  const target = event.target as HTMLInputElement;
  if (target.files) {
    selectedFiles.value = Array.from(target.files);
  }
};

const startTraining = () => {
  // Check if user has an active subscription
  if (!props.hasActiveSubscription) {
    showTrainingDialog.value = false;
    toast.error('Subscription required', {
      description: 'You need an active subscription to train agents. Please upgrade your plan.'
    });
    router.visit(route('billing'));
    return;
  }

  // Logic to start training would go here
  showTrainingDialog.value = false;

  // Show a success toast
  toast.success('Training started', {
    description: 'Your agent is now being trained with the provided data.'
  });

  // In a real implementation, you would submit the training data to the server
  // and then update the agent's status in the local state
  // For now, we'll just simulate updating the local state

  // Find the agent that's being trained (assuming it's the selected agent)
  if (selectedAgent.value) {
    const agentId = selectedAgent.value.id;
    const index = agents.value.findIndex((a: Agent) => a.id === agentId);

    if (index !== -1) {
      // Update the agent's status to 'training'
      agents.value[index] = {
        ...agents.value[index],
        status: 'training'
      };

      // Initialize the progress for this agent
      agentProgress.value[agentId] = 25;
    }
  }
};

// Toggle agent status (active/paused)
const toggleAgentStatus = (agent: Agent) => {
  // Check if user has an active subscription
  if (!props.hasActiveSubscription) {
    toast.error('Subscription required', {
      description: 'You need an active subscription to manage agents. Please upgrade your plan.'
    });
    router.visit(route('billing'));
    return;
  }

  useForm().put(route('agents.toggle-status', { agent: agent.id }), {
    preserveScroll: true,
    onSuccess: (response: any) => {
      const action = agent.status === 'active' ? 'paused' : 'activated';

      // Update the agent in the local state
      if (response?.data?.agent) {
        const updatedAgent = response.data.agent;
        const index = agents.value.findIndex((a: Agent) => a.id === updatedAgent.id);
        if (index !== -1) {
          agents.value[index] = updatedAgent;
        }
      } else {
        // Fallback to manual update if response doesn't contain the updated agent
        const index = agents.value.findIndex((a: Agent) => a.id === agent.id);
        if (index !== -1) {
          const newStatus = agent.status === 'active' ? 'paused' : 'active';
          const now = new Date().toISOString();

          // Update the agent in the local state
          agents.value[index] = {
            ...agents.value[index],
            status: newStatus,
            // Update last_active_at only if the agent is being activated
            last_active_at: newStatus === 'active' ? now : agents.value[index].last_active_at
          };
        }
      }

      toast.success(`Agent ${action}`, {
        description: `${agent.name} has been ${action}.`
      });
    },
    onError: (errors: any) => {
      // Check if this is a subscription required error
      if (errors.subscription_required) {
        toast.error('Subscription required', {
          description: errors.message || 'You need an active subscription to manage agents. Please upgrade your plan.'
        });
        router.visit(route('billing'));
        return;
      }

      // Generic error
      toast.error('Error updating agent status', {
        description: errors.message || 'There was a problem updating the agent status. Please try again.'
      });
    }
  });
};

// Open delete confirmation dialog
const openDeleteDialog = (agent: Agent) => {
  selectedAgent.value = agent;
  showDeleteDialog.value = true;
};

// Delete an agent
const deleteAgent = () => {
  if (!selectedAgent.value) return;

  // Check if user has an active subscription
  if (!props.hasActiveSubscription) {
    showDeleteDialog.value = false;
    toast.error('Subscription required', {
      description: 'You need an active subscription to manage agents. Please upgrade your plan.'
    });
    router.visit(route('billing'));
    return;
  }

  const agentId = selectedAgent.value.id;
  const agentName = selectedAgent.value.name;

  useForm().delete(route('agents.destroy', { agent: agentId }), {
    preserveScroll: true,
    onSuccess: () => {
      // Remove the agent from the local state
      const index = agents.value.findIndex((a: Agent) => a.id === agentId);
      if (index !== -1) {
        agents.value.splice(index, 1);
      }

      showDeleteDialog.value = false;
      toast.success('Agent deleted', {
        description: `${agentName} has been permanently deleted.`
      });
    },
    onError: (errors: any) => {
      // Check if this is a subscription required error
      if (errors.subscription_required) {
        toast.error('Subscription required', {
          description: errors.message || 'You need an active subscription to manage agents. Please upgrade your plan.'
        });
        router.visit(route('billing'));
        return;
      }

      // Generic error
      toast.error('Error deleting agent', {
        description: errors.message || 'There was a problem deleting your agent. Please try again.'
      });
    }
  });
};

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
  <Head title="Agents" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
      <!-- Agents Header with Actions -->
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-2">
        <div>
          <h1 class="text-3xl font-bold">AI Agents</h1>
          <p class="text-muted-foreground">Manage and monitor your AI agents</p>
        </div>
        <div class="flex gap-3">
          <!-- New Agent Dialog -->
          <Dialog v-model:open="showNewAgentDialog">
            <DialogTrigger as-child>
              <Button>
                <Plus class="mr-2 h-4 w-4" />
                New Agent
              </Button>
            </DialogTrigger>
            <DialogContent class="sm:max-w-[425px]">
              <DialogHeader>
                <DialogTitle>Create New Agent</DialogTitle>
                <DialogDescription>
                  Configure your new AI agent. You can train it after creation.
                </DialogDescription>
              </DialogHeader>
              <div class="grid gap-4 py-4">
                <div class="grid gap-2">
                  <Label for="name">Agent Name</Label>
                  <Input id="name" v-model="form.name" placeholder="Customer Support Bot ..." />
                </div>
                <div class="grid gap-2">
                  <Label for="type">Agent Type</Label>
                  <Combobox v-model="form.type" by="value">
                    <ComboboxAnchor>
                      <div class="relative w-full items-center">
                        <ComboboxInput
                          class="w-full flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                          :display-value="(val) => {
                            const type = agentTypes.find(t => t.value === val);
                            return type ? type.label : '';
                          }"
                          placeholder="Select agent type..."
                        />
                      </div>
                    </ComboboxAnchor>
                    <ComboboxList>
                      <ComboboxEmpty>
                        No agent type found.
                      </ComboboxEmpty>
                      <ComboboxGroup>
                        <ComboboxItem
                          v-for="type in agentTypes"
                          :key="type.value"
                          :value="type.value"
                        >
                          {{ type.label }}
                          <ComboboxItemIndicator>
                            <CheckIcon class="ml-auto h-4 w-4" />
                          </ComboboxItemIndicator>
                        </ComboboxItem>
                      </ComboboxGroup>
                    </ComboboxList>
                  </Combobox>
                </div>
              </div>
              <DialogFooter>
                <Button @click="showNewAgentDialog = false" variant="outline">Cancel</Button>
                <Button @click="createNewAgent">Create Agent</Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      </div>



      <!-- Agents List -->
      <Card>
        <CardHeader>
          <CardTitle>My AI Agents</CardTitle>
          <CardDescription>Manage and monitor all your AI agents</CardDescription>
        </CardHeader>
        <CardContent>
          <div v-if="isLoading" class="space-y-3">
            <Skeleton class="h-20 w-full" />
            <Skeleton class="h-20 w-full" />
            <Skeleton class="h-20 w-full" />
          </div>
          <div v-else-if="!agents.length && !hasActiveSubscription" class="text-center py-8">
            <div class="mx-auto w-16 h-16 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center mb-4">
              <Rocket class="h-8 w-8 text-amber-600 dark:text-amber-400" />
            </div>
            <h3 class="text-lg font-medium mb-2">Unlock AI Agents</h3>
            <p class="text-sm text-muted-foreground mb-6 max-w-md mx-auto">
              Subscribe to a plan to create and manage powerful AI agents that can automate tasks and provide intelligent assistance.
            </p>
            <Alert class="max-w-md mx-auto mb-6 text-left border-2 border-amber-400 dark:border-amber-600 bg-amber-50 dark:bg-amber-900/20">
              <Rocket class="h-4 w-4 text-amber-600 dark:text-amber-400" />
              <AlertTitle class="text-amber-800 dark:text-amber-300">Ready to get started?</AlertTitle>
              <AlertDescription class="text-amber-700 dark:text-amber-400">
                Choose a subscription plan that fits your needs and start creating AI agents today.
              </AlertDescription>
            </Alert>
            <Button @click="() => router.visit(route('billing'))" class="px-6 bg-purple-600 hover:bg-purple-700 text-white font-medium shadow-sm">
              View Subscription Plans
            </Button>
          </div>
          <div v-else-if="!agents.length" class="text-center py-8">
            <div class="mx-auto w-16 h-16 rounded-full bg-primary/10 flex items-center justify-center mb-4">
              <Bot class="h-8 w-8 text-primary" />
            </div>
            <h3 class="text-lg font-medium mb-2">No Agents Yet</h3>
            <p class="text-sm text-muted-foreground mb-6 max-w-md mx-auto">
              Create your first AI agent to start automating tasks and providing intelligent assistance.
            </p>
            <Alert class="max-w-md mx-auto mb-6 text-left">
              <Rocket class="h-4 w-4 text-primary" />
              <AlertTitle>Get Started</AlertTitle>
              <AlertDescription>
                Your subscription is active! You can now create AI agents to help with your tasks.
              </AlertDescription>
            </Alert>
            <Button @click="showNewAgentDialog = true" class="px-6">
              <Plus class="mr-2 h-4 w-4" />
              Create Your First Agent
            </Button>
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
              <div class="flex items-center gap-2 flex-wrap">
                <Button
                  v-if="agent.status === 'active'"
                  variant="outline"
                  size="sm"
                  @click="() => toggleAgentStatus(agent)"
                >
                  <Pause class="h-3 w-3 mr-1" />
                  Pause
                </Button>
                <Button
                  v-if="agent.status === 'paused'"
                  variant="outline"
                  size="sm"
                  @click="() => toggleAgentStatus(agent)"
                >
                  <Play class="h-3 w-3 mr-1" />
                  Resume
                </Button>
                <Button
                  variant="outline"
                  size="sm"
                  @click="() => openEditDialog(agent)"
                >
                  <Edit class="h-3 w-3 mr-1" />
                  Edit
                </Button>

                <Dialog v-model:open="showTrainingDialog">
                  <DialogTrigger as-child>
                    <Button variant="outline" size="sm" @click="() => selectedAgent = agent">
                      <Zap class="h-3 w-3 mr-1" />
                      Train
                    </Button>
                  </DialogTrigger>
                  <DialogContent class="sm:max-w-[500px]">
                    <DialogHeader>
                      <DialogTitle>Train Your AI Agent</DialogTitle>
                      <DialogDescription>
                        Upload files, provide website URLs, or connect to APIs to train your agent.
                      </DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-4 py-4">
                      <Tabs v-model="trainingMethod" class="w-full">
                        <TabsList class="grid grid-cols-3 mb-4">
                          <TabsTrigger value="files">Files</TabsTrigger>
                          <TabsTrigger value="website">Website</TabsTrigger>
                          <TabsTrigger value="api">API</TabsTrigger>
                        </TabsList>
                        <TabsContent value="files">
                          <div class="space-y-4">
                            <Label>Upload Documents</Label>
                            <div class="border-2 border-dashed rounded-lg p-6 text-center cursor-pointer hover:bg-muted/50 transition-colors">
                              <Upload class="h-8 w-8 mx-auto mb-2 text-muted-foreground" />
                              <p class="text-sm text-muted-foreground mb-1">Drag and drop files here or click to browse</p>
                              <p class="text-xs text-muted-foreground">Supports PDF, DOCX, TXT, CSV (Max 50MB)</p>
                              <input type="file" multiple class="hidden" @change="handleFileChange" />
                            </div>
                            <div v-if="selectedFiles.length > 0" class="text-sm">
                              {{ selectedFiles.length }} files selected
                            </div>
                          </div>
                        </TabsContent>
                        <TabsContent value="website">
                          <div class="space-y-4">
                            <div class="space-y-2">
                              <Label>Website URL</Label>
                              <Input v-model="websiteUrl" placeholder="https://example.com" />
                              <p class="text-xs text-muted-foreground">We'll crawl this website to train your agent</p>
                            </div>
                          </div>
                        </TabsContent>
                        <TabsContent value="api">
                          <div class="space-y-4">
                            <div class="space-y-2">
                              <Label>API Endpoint</Label>
                              <Input v-model="apiEndpoint" placeholder="https://api.example.com/data" />
                              <p class="text-xs text-muted-foreground">Connect to your data source via API</p>
                            </div>
                          </div>
                        </TabsContent>
                      </Tabs>
                    </div>
                    <DialogFooter>
                      <Button @click="showTrainingDialog = false" variant="outline">Cancel</Button>
                      <Button @click="startTraining">Start Training</Button>
                    </DialogFooter>
                  </DialogContent>
                </Dialog>
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="sm">
                      <MoreVertical class="h-4 w-4" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end">
                    <DropdownMenuItem @click="() => openViewDetailsDialog(agent)">
                      View Details
                    </DropdownMenuItem>
                    <DropdownMenuItem @click="() => openAnalyticsDialog(agent)">
                      Analytics
                    </DropdownMenuItem>
                    <DropdownMenuItem>
                      Export Data
                    </DropdownMenuItem>
                    <DropdownMenuItem
                      class="text-red-500"
                      @click="() => openDeleteDialog(agent)"
                    >
                      Delete
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>

    <!-- Edit Agent Dialog -->
    <Dialog v-model:open="showEditAgentDialog">
      <DialogContent class="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>Edit Agent</DialogTitle>
          <DialogDescription>
            Update your agent's configuration.
          </DialogDescription>
        </DialogHeader>
        <div v-if="selectedAgent" class="grid gap-4 py-4">
          <div class="grid gap-2">
            <Label for="edit-name">Agent Name</Label>
            <Input id="edit-name" v-model="editForm.name" />
          </div>
          <div class="grid gap-2">
            <Label for="edit-type">Agent Type</Label>
            <Combobox v-model="editForm.type" by="value">
              <ComboboxAnchor>
                <div class="relative w-full items-center">
                  <ComboboxInput
                    class="w-full flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                    :display-value="(val) => {
                      const type = agentTypes.find(t => t.value === val);
                      return type ? type.label : '';
                    }"
                    placeholder="Select agent type..."
                  />
                </div>
              </ComboboxAnchor>
              <ComboboxList>
                <ComboboxEmpty>
                  No agent type found.
                </ComboboxEmpty>
                <ComboboxGroup>
                  <ComboboxItem
                    v-for="type in agentTypes"
                    :key="type.value"
                    :value="type.value"
                  >
                    {{ type.label }}
                    <ComboboxItemIndicator>
                      <CheckIcon class="ml-auto h-4 w-4" />
                    </ComboboxItemIndicator>
                  </ComboboxItem>
                </ComboboxGroup>
              </ComboboxList>
            </Combobox>
          </div>
          <div class="grid gap-2">
            <Label for="edit-status">Status</Label>
            <Combobox v-model="editForm.status" by="value">
              <ComboboxAnchor>
                <div class="relative w-full items-center">
                  <ComboboxInput
                    class="w-full flex h-9 rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                    :display-value="(val) => val.charAt(0).toUpperCase() + val.slice(1)"
                    placeholder="Select status..."
                  />
                </div>
              </ComboboxAnchor>
              <ComboboxList>
                <ComboboxEmpty>
                  No status found.
                </ComboboxEmpty>
                <ComboboxGroup>
                  <ComboboxItem value="active">
                    Active
                    <ComboboxItemIndicator>
                      <CheckIcon class="ml-auto h-4 w-4" />
                    </ComboboxItemIndicator>
                  </ComboboxItem>
                  <ComboboxItem value="paused">
                    Paused
                    <ComboboxItemIndicator>
                      <CheckIcon class="ml-auto h-4 w-4" />
                    </ComboboxItemIndicator>
                  </ComboboxItem>
                  <ComboboxItem value="training">
                    Training
                    <ComboboxItemIndicator>
                      <CheckIcon class="ml-auto h-4 w-4" />
                    </ComboboxItemIndicator>
                  </ComboboxItem>
                </ComboboxGroup>
              </ComboboxList>
            </Combobox>
          </div>
        </div>
        <DialogFooter>
          <Button @click="showEditAgentDialog = false" variant="outline">Cancel</Button>
          <Button @click="updateAgent">Save Changes</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Delete Confirmation Modal -->
    <Dialog v-model:open="showDeleteDialog">
      <DialogContent class="sm:max-w-[425px]">
        <DialogHeader>
          <DialogTitle>Delete Agent</DialogTitle>
          <DialogDescription>
            Are you sure you want to delete this agent? This action cannot be undone.
          </DialogDescription>
        </DialogHeader>
        <div v-if="selectedAgent" class="py-4">
          <div class="flex items-center gap-3 p-4 border rounded-lg bg-destructive/5">
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-destructive/10">
              <Bot class="h-5 w-5 text-destructive" />
            </div>
            <div>
              <p class="font-medium">{{ selectedAgent.name }}</p>
              <p class="text-sm text-muted-foreground">Type: {{ selectedAgent.type }}</p>
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button @click="showDeleteDialog = false" variant="outline">Cancel</Button>
          <Button @click="deleteAgent" variant="destructive">Delete Agent</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- View Details Modal -->
    <Dialog v-model:open="showViewDetailsDialog">
      <DialogContent class="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle>Agent Details</DialogTitle>
          <DialogDescription>
            Detailed information about your agent.
          </DialogDescription>
        </DialogHeader>
        <div v-if="selectedAgent" class="py-4">
          <div class="flex items-center gap-4 mb-4">
            <div class="flex h-16 w-16 items-center justify-center rounded-full bg-primary/10">
              <Bot class="h-8 w-8 text-primary" />
            </div>
            <div>
              <h3 class="text-lg font-semibold">{{ selectedAgent.name }}</h3>
              <div class="flex items-center gap-2">
                <div class="h-2 w-2 rounded-full" :class="getStatusColor(selectedAgent.status)"></div>
                <span class="text-sm capitalize">{{ selectedAgent.status }}</span>
              </div>
            </div>
          </div>

          <div class="space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-1">
                <p class="text-sm font-medium text-muted-foreground">Type</p>
                <p>{{ selectedAgent.type }}</p>
              </div>
              <div class="space-y-1">
                <p class="text-sm font-medium text-muted-foreground">Created</p>
                <p>{{ new Date(selectedAgent.created_at).toLocaleDateString() }}</p>
              </div>
              <div class="space-y-1">
                <p class="text-sm font-medium text-muted-foreground">Last Active</p>
                <p>{{ formatLastActive(selectedAgent) }}</p>
              </div>
              <div class="space-y-1">
                <p class="text-sm font-medium text-muted-foreground">Interactions</p>
                <p>{{ selectedAgent.interactions }}</p>
              </div>
            </div>

            <div class="border-t pt-4">
              <h4 class="text-sm font-medium mb-2">Performance</h4>
              <div class="flex items-center gap-2">
                <div class="w-full bg-muted rounded-full h-2.5">
                  <div class="bg-primary h-2.5 rounded-full" :style="{ width: `${selectedAgent.performance}%` }"></div>
                </div>
                <span class="text-sm">{{ selectedAgent.performance }}%</span>
              </div>
            </div>
          </div>
        </div>
        <DialogFooter>
          <Button @click="showViewDetailsDialog = false">Close</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Analytics Modal -->
    <Dialog v-model:open="showAnalyticsDialog">
      <DialogContent class="sm:max-w-[700px] max-h-[90vh] overflow-y-auto">
        <DialogHeader class="pb-2">
          <DialogTitle>Agent Analytics</DialogTitle>
          <DialogDescription>
            Performance metrics for {{ selectedAgent?.name }}
          </DialogDescription>
        </DialogHeader>
        <div v-if="selectedAgent" class="py-2">
          <!-- Agent Info and Chart in a row -->
          <div class="flex flex-col md:flex-row gap-4 mb-4">
            <!-- Agent Info -->
            <div class="flex items-center gap-3 md:w-1/3">
              <div class="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                <Bot class="h-6 w-6 text-primary" />
              </div>
              <div>
                <h3 class="font-medium">{{ selectedAgent.name }}</h3>
                <div class="flex items-center gap-2">
                  <div class="h-2 w-2 rounded-full" :class="getStatusColor(selectedAgent.status)"></div>
                  <span class="text-xs capitalize">{{ selectedAgent.status }}</span>
                </div>
              </div>
            </div>

            <!-- Mini Chart -->
            <div class="md:w-2/3 h-[120px] bg-muted/20 rounded-lg flex items-center justify-center">
              <div class="text-center">
                <LineChart class="h-8 w-8 mx-auto mb-1 text-muted-foreground" />
                <p class="text-xs text-muted-foreground">Interaction Trends</p>
              </div>
            </div>
          </div>

          <!-- Key Metrics in a compact grid -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="border rounded-lg p-3">
              <h3 class="text-sm font-medium">Response Time</h3>
              <div class="text-xl font-bold">1.2s</div>
              <p class="text-xs text-muted-foreground">Average</p>
            </div>

            <div class="border rounded-lg p-3">
              <h3 class="text-sm font-medium">Satisfaction</h3>
              <div class="text-xl font-bold">4.7/5</div>
              <p class="text-xs text-muted-foreground">User rating</p>
            </div>

            <div class="border rounded-lg p-3">
              <h3 class="text-sm font-medium">Interactions</h3>
              <div class="text-xl font-bold">{{ selectedAgent.interactions }}</div>
              <p class="text-xs text-muted-foreground">Total</p>
            </div>

            <div class="border rounded-lg p-3">
              <h3 class="text-sm font-medium">Performance</h3>
              <div class="text-xl font-bold">{{ selectedAgent.performance }}%</div>
              <p class="text-xs text-muted-foreground">Effectiveness</p>
            </div>
          </div>
        </div>
        <DialogFooter class="pt-2">
          <Button @click="showAnalyticsDialog = false">Close</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  </AppLayout>
</template>
