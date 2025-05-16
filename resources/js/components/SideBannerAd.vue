<script setup lang="ts">
/**
 * @deprecated Use AdDisplay component instead
 */
import { ref, onMounted, computed } from 'vue';
import { fetchAds, trackAdClick as trackClick, getAdsByType } from '@/services/AdService';

// Props for the side banner ad
const props = defineProps<{
  position?: 'left' | 'right'; // Position of the ad banner
  adId?: string; // Unique ID for the ad (for tracking)
  adUrl?: string; // URL to redirect when ad is clicked
  adImageUrl?: string; // Image URL for the ad
  adText?: string; // Alternative text content if no image
  page?: string; // Page name for fetching relevant ads
  useStatic?: boolean; // Whether to use static props or fetch from API
}>();

// State
const isVisible = ref(true);
const adData = ref<any>(null);
const isLoading = ref(false);

// Default values for static mode
const position = computed(() => props.position || 'right');
const adId = computed(() => adData.value?.ad_id || props.adId || 'ad-' + Math.random().toString(36).substring(2, 9));
const adUrl = computed(() => adData.value?.url || props.adUrl || '#');
const adImageUrl = computed(() => adData.value?.image_url || props.adImageUrl || '/storage/ads/default-ad.jpg');
const adText = computed(() => adData.value?.title || props.adText || 'Advertisement');

// Close the ad
const closeAd = () => {
  isVisible.value = false;

  // Store in localStorage to remember user preference for this ad
  if (adId.value) {
    localStorage.setItem(`ad-closed-${adId.value}`, 'true');
  }
};

// Track ad click
const trackAdClick = () => {
  if (adId.value) {
    trackClick(adId.value).then(success => {
      if (success) {
        console.log(`Ad click tracked: ${adId.value}`);
      }
    });
  }
};

// Fetch ad data from API
const fetchAdData = async () => {
  if (props.useStatic) return;

  isLoading.value = true;
  try {
    console.log(`SideBannerAd: Fetching ads for page ${props.page || 'all'} with position ${position.value}`);
    const ads = await fetchAds(props.page || 'all');
    console.log('SideBannerAd: Available ads:', ads);

    // Look for side banner ads (type: 'banner')
    const sideAds = ads.filter(ad => {
      const matches = ad.type === 'banner' && ad.position === position.value;
      console.log(`SideBannerAd: Ad ${ad.id} (${ad.type}, ${ad.position}) matches position ${position.value}? ${matches}`);
      return matches;
    });

    console.log(`SideBannerAd: Found ${sideAds.length} matching ads`);

    if (sideAds.length > 0) {
      // Use the first matching ad
      adData.value = sideAds[0];
      console.log('SideBannerAd: Using ad:', adData.value);

      // Check if this ad was previously closed
      if (localStorage.getItem(`ad-closed-${adData.value.ad_id}`) === 'true') {
        console.log('SideBannerAd: Ad was previously closed');
        isVisible.value = false;
      }
    } else {
      // No matching ads found
      console.log('SideBannerAd: No matching ads found, hiding banner');
      isVisible.value = false;
    }
  } catch (error) {
    console.error('SideBannerAd: Error fetching ad data:', error);
    isVisible.value = false;
  } finally {
    isLoading.value = false;
  }
};

// Initialize on mount
onMounted(() => {
  // Check if this ad was previously closed (for static mode)
  if (props.useStatic && props.adId && localStorage.getItem(`ad-closed-${props.adId}`) === 'true') {
    isVisible.value = false;
  }

  // Fetch ad data if not using static mode
  if (!props.useStatic) {
    fetchAdData();
  }
});
</script>

<template>
  <div
    v-if="isVisible && !isLoading"
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

        <!-- Ad description (if available) -->
        <div v-if="adData?.description" class="p-2 text-xs">
          {{ adData.description }}
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
