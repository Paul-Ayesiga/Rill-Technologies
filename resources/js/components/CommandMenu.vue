<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import {
  Command,
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
  CommandShortcut,
} from '@/components/ui/command';

import {
  Calculator,
  Calendar,
  CreditCard,
  Settings,
  Smile,
  User,
  Search,
  BarChart,
  Bot,
  LineChart,
  Plus,
  Zap,
} from 'lucide-vue-next';

const props = defineProps<{
  open: boolean;
}>();

const emit = defineEmits<{
  (e: 'update:open', value: boolean): void;
  (e: 'select', action: string): void;
}>();

// Create a local ref that syncs with the prop
const isOpen = ref(props.open);

// Watch for changes to the prop and update the local ref
watch(() => props.open, (newValue) => {
  isOpen.value = newValue;
});

// Watch for changes to the local ref and emit update events
watch(isOpen, (newValue) => {
  emit('update:open', newValue);
});

// Handle keyboard shortcut
onMounted(() => {
  window.addEventListener('keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
      e.preventDefault();
      isOpen.value = true;
    }
  });
});

// Close the dialog
const closeDialog = () => {
  isOpen.value = false;
};

// Handle command selection
const handleSelect = (action: string) => {
  emit('select', action);
  closeDialog();

  // Navigate to pages directly using Inertia
  switch (action) {
    case 'agents':
      router.visit(route('agents'));
      break;
    case 'billing':
    case 'subscription':
      router.visit(route('billing'));
      break;
  }
};
</script>

<template>
  <CommandDialog :open="isOpen" @update:open="isOpen = $event">
    <CommandInput placeholder="Type a command or search..." />
    <CommandList>
      <CommandEmpty>No results found.</CommandEmpty>
      <CommandGroup heading="Actions">
        <CommandItem value="new-agent" @select="() => handleSelect('new-agent')">
          <Plus class="mr-2 h-4 w-4" />
          <span>Create New Agent</span>
          <CommandShortcut>⌘N</CommandShortcut>
        </CommandItem>
        <CommandItem value="train-agent" @select="() => handleSelect('train-agent')">
          <Zap class="mr-2 h-4 w-4" />
          <span>Train Agent</span>
          <CommandShortcut>⌘T</CommandShortcut>
        </CommandItem>
        <CommandItem value="search" @select="() => handleSelect('search')">
          <Search class="mr-2 h-4 w-4" />
          <span>Search</span>
          <CommandShortcut>⌘F</CommandShortcut>
        </CommandItem>
      </CommandGroup>
      <CommandSeparator />
      <CommandGroup heading="Navigation">
        <CommandItem value="overview" @select="() => handleSelect('overview')">
          <BarChart class="mr-2 h-4 w-4" />
          <span>Overview</span>
        </CommandItem>
        <CommandItem value="agents" @select="() => handleSelect('agents')">
          <Bot class="mr-2 h-4 w-4" />
          <span>My Agents</span>
          <CommandShortcut>⌘A</CommandShortcut>
        </CommandItem>
        <CommandItem value="insights" @select="() => handleSelect('insights')">
          <LineChart class="mr-2 h-4 w-4" />
          <span>Insights</span>
          <CommandShortcut>⌘I</CommandShortcut>
        </CommandItem>
        <CommandItem value="billing" @select="() => handleSelect('billing')">
          <CreditCard class="mr-2 h-4 w-4" />
          <span>Billing</span>
          <CommandShortcut>⌘B</CommandShortcut>
        </CommandItem>
      </CommandGroup>
      <CommandSeparator />
      <CommandGroup heading="Settings">
        <CommandItem value="profile" @select="() => handleSelect('profile')">
          <User class="mr-2 h-4 w-4" />
          <span>Profile</span>
          <CommandShortcut>⌘P</CommandShortcut>
        </CommandItem>
        <CommandItem value="settings" @select="() => handleSelect('settings')">
          <Settings class="mr-2 h-4 w-4" />
          <span>Settings</span>
          <CommandShortcut>⌘,</CommandShortcut>
        </CommandItem>
      </CommandGroup>
    </CommandList>
  </CommandDialog>
</template>
