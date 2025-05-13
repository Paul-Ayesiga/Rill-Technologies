<script setup lang="ts">
import { computed } from 'vue';

// Define props
const props = defineProps<{
  brand: string;
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}>();

// Default size
const size = props.size || 'md';

// Compute size classes
const sizeClasses = computed(() => {
  switch (size) {
    case 'sm': return 'h-6 w-10';
    case 'lg': return 'h-10 w-16';
    case 'md':
    default: return 'h-8 w-12';
  }
});

// Normalize brand name
const normalizedBrand = computed(() => props.brand.toLowerCase());

// Determine if we have a specific icon for this brand
const hasSpecificIcon = computed(() => {
  // Only include brands that have corresponding SVG files in public/card-icons
  return ['visa', 'mastercard', 'amex', 'discover'].includes(normalizedBrand.value);
});

// Compute the background color based on the brand
const bgColor = computed(() => {
  switch (normalizedBrand.value) {
    case 'visa': return 'bg-blue-50 dark:bg-blue-900/20';
    case 'mastercard': return 'bg-orange-50 dark:bg-orange-900/20';
    case 'amex': return 'bg-indigo-50 dark:bg-indigo-900/20';
    case 'discover': return 'bg-orange-50 dark:bg-orange-900/20';
    default: return 'bg-gray-50 dark:bg-gray-800';
  }
});

// Compute the border color based on the brand
const borderColor = computed(() => {
  switch (normalizedBrand.value) {
    case 'visa': return 'border-blue-200 dark:border-blue-800';
    case 'mastercard': return 'border-orange-200 dark:border-orange-800';
    case 'amex': return 'border-indigo-200 dark:border-indigo-800';
    case 'discover': return 'border-orange-200 dark:border-orange-800';
    default: return 'border-gray-200 dark:border-gray-700';
  }
});
</script>

<template>
  <div
    :class="[
      'flex items-center justify-center rounded-md border shadow-sm overflow-hidden',
      sizeClasses,
      bgColor,
      borderColor,
      className
    ]"
  >
    <!-- Use image files from public folder -->
    <img
      v-if="hasSpecificIcon"
      :src="`/card-icons/${normalizedBrand}-svgrepo-com.svg`"
      :alt="`${normalizedBrand} card`"
      class="h-4/5 w-4/5 object-contain p-1"
    />

    <!-- Generic Card -->
    <div v-else class="text-xs font-bold uppercase">{{ normalizedBrand }}</div>
  </div>
</template>
