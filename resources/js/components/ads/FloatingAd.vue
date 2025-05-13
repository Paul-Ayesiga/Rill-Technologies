<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { X } from 'lucide-vue-next';

const props = defineProps({
  adTitle: {
    type: String,
    default: 'Limited Time Offer'
  },
  adText: {
    type: String,
    default: 'Get 50% off your first month when you sign up today!'
  },
  adImage: {
    type: String,
    default: '/ads/ad1.png'
  },
  adLink: {
    type: String,
    default: '#'
  },
  position: {
    type: String,
    default: 'bottom-right', // Options: bottom-right, bottom-left, top-right, top-left
    validator: (value: string) => ['bottom-right', 'bottom-left', 'top-right', 'top-left'].includes(value)
  },
  delay: {
    type: Number,
    default: 3000 // Show after 3 seconds
  }
});

const isVisible = ref(false);
const adId = `floating-ad-${Math.random().toString(36).substring(2, 9)}`;

const closeAd = () => {
  isVisible.value = false;
  // Store in localStorage to remember user preference
  localStorage.setItem(adId, 'closed');
};

onMounted(() => {
  // Check if user has previously closed this ad
  if (localStorage.getItem(adId) !== 'closed') {
    // Show ad after delay
    setTimeout(() => {
      isVisible.value = true;
    }, props.delay);
  }
});

// Compute position classes
const positionClasses = computed(() => {
  switch (props.position) {
    case 'bottom-right':
      return 'bottom-4 right-4';
    case 'bottom-left':
      return 'bottom-4 left-4';
    case 'top-right':
      return 'top-4 right-4';
    case 'top-left':
      return 'top-4 left-4';
    default:
      return 'bottom-4 right-4';
  }
});
</script>

<template>
  <Transition
    enter-active-class="transition duration-300 ease-out"
    enter-from-class="transform translate-y-8 opacity-0"
    enter-to-class="transform translate-y-0 opacity-100"
    leave-active-class="transition duration-200 ease-in"
    leave-from-class="transform translate-y-0 opacity-100"
    leave-to-class="transform translate-y-8 opacity-0"
  >
    <div
      v-if="isVisible"
      :class="[positionClasses, 'fixed z-50 max-w-sm shadow-xl rounded-lg overflow-hidden']"
    >
      <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg">
        <div class="relative">
          <img :src="adImage" alt="Advertisement" class="w-full h-32 object-cover" />
          <button
            @click="closeAd"
            class="absolute top-2 right-2 p-1 bg-black/50 rounded-full hover:bg-black/70 transition-colors"
          >
            <X class="h-4 w-4 text-white" />
          </button>
        </div>
        <div class="p-4">
          <h3 class="font-bold text-lg mb-1 dark:text-white">{{ adTitle }}</h3>
          <p class="text-gray-700 dark:text-gray-300 text-sm mb-3">{{ adText }}</p>
          <a
            :href="adLink"
            class="block w-full py-2 px-4 bg-primary text-white text-center rounded-md hover:bg-primary/90 transition-colors"
          >
            Learn More
          </a>
        </div>
      </div>
    </div>
  </Transition>
</template>
