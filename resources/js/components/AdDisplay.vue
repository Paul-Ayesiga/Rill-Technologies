<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { fetchAds, trackAdClick } from '@/services/AdService';

// Props for the ad display
const props = defineProps<{
  page?: string; // Current page name for fetching relevant ads
  type?: string; // Optional type filter (banner, floating, top)
  position?: string; // Optional position filter
  maxAds?: number; // Maximum number of ads to display (default: all)
}>();

// State
const ads = ref<any[]>([]);
const isLoading = ref(true);

// Fetch ads from API
const fetchPageAds = async () => {
  isLoading.value = true;
  try {
    console.log(`AdDisplay: Fetching ads for page ${props.page || 'all'}`);
    const fetchedAds = await fetchAds(props.page || 'all');
    console.log('AdDisplay: All available ads:', fetchedAds);

    // Filter ads based on type and position if provided
    let filteredAds = [...fetchedAds];

    if (props.type) {
      filteredAds = filteredAds.filter(ad => ad.type === props.type);
      console.log(`AdDisplay: Filtered by type "${props.type}":`, filteredAds.length);
    }

    if (props.position) {
      filteredAds = filteredAds.filter(ad => ad.position === props.position);
      console.log(`AdDisplay: Filtered by position "${props.position}":`, filteredAds.length);
    }

    // Limit the number of ads if maxAds is provided
    if (props.maxAds && props.maxAds > 0) {
      filteredAds = filteredAds.slice(0, props.maxAds);
      console.log(`AdDisplay: Limited to ${props.maxAds} ads:`, filteredAds.length);
    }

    // Set the ads
    ads.value = filteredAds.map(ad => ({
      ...ad,
      isVisible: !localStorage.getItem(`ad-closed-${ad.ad_id}`)
    }));

    console.log('AdDisplay: Final ads to display:', ads.value);
  } catch (error) {
    console.error('AdDisplay: Error fetching ads:', error);
    ads.value = [];
  } finally {
    isLoading.value = false;
  }
};

// Handle ad click
const handleAdClick = (adId: string) => {
  trackAdClick(adId).then(success => {
    if (success) {
      console.log(`AdDisplay: Ad click tracked: ${adId}`);
    }
  });
};

// Close an ad
const closeAd = (adId: string) => {
  // Find the ad and set it to not visible
  const adIndex = ads.value.findIndex(ad => ad.ad_id === adId);
  if (adIndex !== -1) {
    ads.value[adIndex].isVisible = false;

    // Store in localStorage to remember user preference
    localStorage.setItem(`ad-closed-${adId}`, 'true');
  }
};

// Get position class based on ad position and type
const getPositionClass = (type: string, position: string) => {
  // Banner ads (sidebar)
  if (type === 'banner') {
    switch (position) {
      case 'left':
        return 'fixed left-4 top-32 md:top-40 z-40';
      case 'right':
        return 'fixed right-4 top-32 md:top-40 z-40';
      default:
        return 'fixed right-4 top-32 md:top-40 z-40'; // Default to right
    }
  }

  // Floating ads
  if (type === 'floating') {
    switch (position) {
      case 'bottom-right':
        return 'fixed bottom-4 right-4 z-40';
      case 'bottom-left':
        return 'fixed bottom-4 left-4 z-40';
      case 'top-right':
        return 'fixed top-20 right-4 z-40';
      case 'top-left':
        return 'fixed top-20 left-4 z-40';
      default:
        return 'fixed bottom-4 right-4 z-40'; // Default to bottom-right
    }
  }

  // Top/bottom banner ads
  if (type === 'top') {
    switch (position) {
      case 'top':
        return 'sticky top-0 left-0 right-0 z-50'; // Promotional banner at the very top
      case 'bottom':
        return 'fixed bottom-0 left-0 right-0 z-40';
      default:
        return 'sticky top-0 left-0 right-0 z-50'; // Default to top
    }
  }

  // Default fallback
  return 'fixed right-4 top-32 md:top-40 z-40';
};

// Get size class based on ad type
const getSizeClass = (type: string, position: string) => {
  switch (type) {
    case 'banner':
      return 'w-[160px] md:w-[200px]';
    case 'floating':
      return 'w-[250px] md:w-[300px]';
    case 'top':
      return position === 'top' || position === 'bottom' ? 'w-full' : 'w-[300px]';
    default:
      return 'w-[160px] md:w-[200px]';
  }
};

// Get container class based on ad type
const getContainerClass = (type: string, position: string) => {
  if (type === 'top') {
    if (position === 'top') {
      return 'flex justify-center items-center bg-primary text-primary-foreground py-1 text-center'; // Promotional banner style
    } else if (position === 'bottom') {
      return 'flex justify-center items-center bg-gray-100 dark:bg-gray-800 py-2';
    }
  }
  return '';
};

// Initialize on mount
onMounted(() => {
  fetchPageAds();
});
</script>

<template>
  <div v-if="!isLoading">
    <div v-for="ad in ads" :key="ad.ad_id" v-show="ad.isVisible">
      <div :class="[
        'ad-container transition-all duration-300 ease-in-out',
        getPositionClass(ad.type, ad.position)
      ]">
        <div :class="[
          ad.type === 'top' && ad.position === 'top'
            ? 'relative overflow-hidden flex flex-col'
            : 'relative bg-white dark:bg-gray-800 rounded-lg shadow-lg overflow-hidden flex flex-col',
          getContainerClass(ad.type, ad.position)
        ]">
          <!-- Close button - different style for top promotional banners -->
          <button
            @click.prevent="closeAd(ad.ad_id)"
            :class="[
              ad.type === 'top' && ad.position === 'top'
                ? 'absolute right-2 top-1/2 -translate-y-1/2 text-white hover:text-gray-200 transition-colors'
                : 'absolute top-2 right-2 bg-gray-200 dark:bg-gray-700 rounded-full p-1 hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors'
            ]"
            aria-label="Close advertisement"
          >
            <svg xmlns="http://www.w3.org/2000/svg"
              :class="[
                ad.type === 'top' && ad.position === 'top'
                  ? 'h-5 w-5'
                  : 'h-4 w-4 text-gray-600 dark:text-gray-300'
              ]"
              fill="none" viewBox="0 0 24 24" stroke="currentColor"
            >
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <!-- Ad content -->
          <a
            :href="ad.url"
            target="_blank"
            rel="noopener noreferrer"
            @click="handleAdClick(ad.ad_id)"
            class="block"
          >
            <!-- Special handling for top promotional banners -->
            <template v-if="ad.type === 'top' && ad.position === 'top'">
              <div class="flex items-center justify-center gap-2 px-4">
                <span class="text-sm font-medium">{{ ad.title || 'Special Offer' }}</span>
                <span v-if="ad.description" class="text-sm">{{ ad.description }}</span>
                <span class="text-xs font-semibold bg-white text-primary px-2 py-0.5 rounded">
                  Learn More →
                </span>
              </div>
            </template>

            <!-- Standard ad display for other types -->
            <template v-else>
              <!-- Ad image -->
              <div :class="[getSizeClass(ad.type, ad.position), 'h-auto']">
                <img
                  :src="ad.image_url"
                  :alt="ad.title || 'Advertisement'"
                  class="w-full h-auto object-cover"
                />
              </div>

              <!-- Ad description (if available) -->
              <div v-if="ad.description" class="p-2 text-xs">
                {{ ad.description }}
              </div>

              <!-- Ad label -->
              <div class="text-xs text-center py-1 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                Advertisement
              </div>
            </template>
          </a>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.ad-container {
  max-height: 80vh;
  width: auto;
}
</style>
