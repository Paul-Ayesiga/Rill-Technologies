<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { X } from 'lucide-vue-next';

const props = defineProps({
  adText: {
    type: String,
    default: '🔥 Special Offer: Get 20% off all plans with code WELCOME20'
  },
  adLink: {
    type: String,
    default: '#'
  },
  bgColor: {
    type: String,
    default: 'bg-gradient-to-r from-primary to-purple-600'
  },
  textColor: {
    type: String,
    default: 'text-white'
  }
});

const isVisible = ref(true);

const closeAd = () => {
  isVisible.value = false;
  // Store in localStorage to remember user preference
  localStorage.setItem('topBannerClosed', 'true');
};

onMounted(() => {
  // Check if user has previously closed the banner
  if (localStorage.getItem('topBannerClosed') === 'true') {
    isVisible.value = false;
  }
});
</script>

<template>
  <div v-if="isVisible" :class="[bgColor, textColor, 'py-2 px-4 text-center relative z-50']">
    <div class="container mx-auto flex items-center justify-center">
      <a :href="adLink" class="font-medium hover:underline">
        {{ adText }}
      </a>
      <button @click="closeAd" class="ml-4 p-1 rounded-full hover:bg-black/10 transition-colors">
        <X class="h-4 w-4" />
      </button>
    </div>
  </div>
</template>
