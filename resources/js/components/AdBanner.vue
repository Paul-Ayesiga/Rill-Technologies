<script setup lang="ts">
import { ref, onMounted } from 'vue';

// Props for the ad banner
const props = defineProps<{
  position?: 'left' | 'right'; // Position of the ad banner
  adId?: string; // Unique ID for the ad (for tracking)
  adUrl?: string; // URL to redirect when ad is clicked
  adImageUrl?: string; // Image URL for the ad
  adText?: string; // Alternative text content if no image
}>();

// Default values
const position = props.position || 'right';
const adId = props.adId || 'ad-' + Math.random().toString(36).substring(2, 9);
const adUrl = props.adUrl || '#';
const adImageUrl = props.adImageUrl || '/ads/default-ad.jpg';
const adText = props.adText || 'Advertisement';

// State for visibility
const isVisible = ref(true);

// Close the ad
const closeAd = () => {
  isVisible.value = false;
};

// Track ad click
const trackAdClick = () => {
  // Here you would typically implement ad click tracking
  console.log(`Ad clicked: ${adId}`);
};

// Handle ad impression
onMounted(() => {
  // Here you would typically implement ad impression tracking
  console.log(`Ad impression: ${adId}`);
});
</script>

<template>
  <div 
    v-if="isVisible" 
    :class="[
      'ad-banner fixed z-40 transition-all duration-300 ease-in-out',
      position === 'left' ? 'left-4' : 'right-4',
      'top-32 md:top-40'
    ]"
  >
    <div class="relative bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden flex flex-col">
      <!-- Close button -->
      <button 
        @click.prevent="closeAd" 
        class="absolute top-2 right-2 bg-gray-200 dark:bg-gray-700 rounded-full p-1 z-10 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors"
        aria-label="Close advertisement"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-600 dark:text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
        </svg>
      </button>
      
      <!-- Ad content -->
      <a 
        :href="adUrl" 
        target="_blank" 
        rel="noopener noreferrer" 
        @click="trackAdClick"
        class="block"
      >
        <!-- Ad image -->
        <div class="w-[160px] md:w-[200px] h-auto">
          <img 
            :src="adImageUrl" 
            :alt="adText" 
            class="w-full h-auto object-cover"
          />
        </div>
        
        <!-- Ad label -->
        <div class="text-xs text-center py-1 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
          Advertisement
        </div>
      </a>
    </div>
  </div>
</template>

<style scoped>
.ad-banner {
  max-height: 80vh;
  width: auto;
}
</style>
