<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Brain, Check, Rocket } from 'lucide-vue-next';

const page = usePage();
const name = page.props.name || 'Real';

// Features list for the auth page
const features = [
    {
        icon: Brain,
        title: 'AI-Powered Insights',
        description: 'Our platform uses advanced AI to analyze data and provide actionable insights for your business.'
    },
    {
        icon: Check,
        title: 'Easy Integration',
        description: 'Seamlessly integrate with your existing tools and workflows with our simple API.'
    },
    {
        icon: Rocket,
        title: 'Scale Effortlessly',
        description: 'From startups to enterprises, our platform grows with your business needs.'
    }
];

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="relative grid h-dvh flex-col items-center justify-center px-8 sm:px-0 lg:max-w-none lg:grid-cols-2 lg:px-0">
        <div class="relative hidden h-full flex-col p-10 text-white lg:flex">
            <!-- Background with gradient overlay -->
            <div class="absolute inset-0 overflow-hidden">
                <img src="/home2.jpg" alt="AI technology background" class="w-full h-full object-cover opacity-70">
                <div class="absolute inset-0 bg-gradient-to-r from-gray-900 to-primary/40"></div>
            </div>

            <!-- Logo and brand -->
            <Link :href="route('home')" class="relative z-20 flex items-center text-lg font-medium">
                <img src="/logo.png" alt="AgentForge Logo" class="h-8 w-auto mr-2" />
                <span class="text-xl font-bold">{{ name }}</span>
            </Link>

            <!-- Main content -->
            <div class="relative z-20 mt-auto max-w-md">
                <h1 class="text-3xl font-bold mb-6">Create Custom <span class="text-primary">AI Agents</span> in Minutes</h1>
                <p class="text-lg text-gray-300 mb-8">Empower your business with intelligent AI agents tailored to your specific needs. No coding required.</p>

                <!-- Features list -->
                <div class="space-y-6 mb-8">
                    <div v-for="(feature, index) in features" :key="index" class="flex items-start">
                        <div class="w-10 h-10 flex-shrink-0 bg-primary/10 rounded-full flex items-center justify-center mr-4">
                            <component :is="feature.icon" class="text-primary w-5 h-5" />
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold mb-1">{{ feature.title }}</h3>
                            <p class="text-gray-300 text-sm">{{ feature.description }}</p>
                        </div>
                    </div>
                </div>

                <!-- Social proof -->
                <div class="flex items-center space-x-4">
                    <div class="flex -space-x-2">
                        <div v-for="i in 3" :key="i" class="w-8 h-8 rounded-full border-2 border-white bg-gray-200 flex items-center justify-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd" />
                            </svg>
                        </div>
                    </div>
                    <p class="text-sm text-gray-300"><span class="font-semibold">2,500+</span> businesses already using our platform</p>
                </div>
            </div>
        </div>

        <!-- Right side - Auth form -->
        <div class="lg:p-8">
            <div class="mx-auto flex w-full flex-col justify-center space-y-6 sm:w-[350px]">
                <!-- Mobile logo - only visible on small screens -->
                <div class="flex justify-center items-center mb-6 lg:hidden">
                    <img src="/logo.png" alt="AgentForge Logo" class="h-10 w-auto mr-2" />
                    <span class="text-xl font-bold">{{ name }}</span>
                </div>

                <div class="flex flex-col space-y-2 text-center">
                    <h1 class="text-2xl font-bold tracking-tight" v-if="title">{{ title }}</h1>
                    <p class="text-sm text-muted-foreground" v-if="description">{{ description }}</p>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
