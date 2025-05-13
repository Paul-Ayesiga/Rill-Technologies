<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronRight, Home } from 'lucide-vue-next';
import { computed } from 'vue';
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator
} from '@/components/ui/breadcrumb';

export interface BreadcrumbItemProps {
  label: string;
  href?: string;
  current?: boolean;
}

const props = defineProps<{
  items: BreadcrumbItemProps[];
  showHome?: boolean;
}>();

const breadcrumbItems = computed(() => {
  const items = [...props.items];
  
  // Mark the last item as current if not explicitly set
  if (items.length > 0 && items[items.length - 1].current === undefined) {
    items[items.length - 1].current = true;
  }
  
  // Add home item if showHome is true
  if (props.showHome) {
    items.unshift({
      label: 'Dashboard',
      href: route('admin.dashboard'),
      current: false
    });
  }
  
  return items;
});
</script>

<template>
  <Breadcrumb class="mb-4">
    <BreadcrumbList>
      <BreadcrumbItem v-for="(item, index) in breadcrumbItems" :key="index">
        <BreadcrumbLink v-if="item.href && !item.current" :href="item.href">
          <Home v-if="props.showHome && index === 0" class="h-4 w-4 mr-1" />
          {{ item.label }}
        </BreadcrumbLink>
        <BreadcrumbPage v-else>
          <Home v-if="props.showHome && index === 0" class="h-4 w-4 mr-1" />
          {{ item.label }}
        </BreadcrumbPage>
        <BreadcrumbSeparator v-if="index < breadcrumbItems.length - 1" />
      </BreadcrumbItem>
    </BreadcrumbList>
  </Breadcrumb>
</template>
