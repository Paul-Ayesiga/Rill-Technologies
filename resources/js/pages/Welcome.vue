<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { useColorMode } from '@vueuse/core';
import { ref, watch, computed, onMounted } from 'vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { Sun, Moon, Laptop, Menu as MenuIcon, Cpu, Building2, BrainCircuit, Code, LineChart, Layers, Zap, Workflow, ShoppingCart, Heart, PieChart, Check, Headset, Search, CalendarCheck, Settings, Brain, Rocket, ArrowRight, User, ArrowUp, MessageSquare } from 'lucide-vue-next';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import AdDisplay from '@/components/AdDisplay.vue';

interface PageProps {
    auth: {
        user: any;
    };
    [key: string]: any;
}

interface IndustryCase {
    id: string;
    title: string;
    category: string;
    description: string;
    image: string;
    icon: string;
    iconBg: string;
    iconColor: string;
    popular?: boolean;
    features: {
        text: string;
    }[];
}

const page = usePage<PageProps>();
const colorMode = useColorMode();

// Create a ref to track the selected theme
const selectedTheme = ref(colorMode.value);

// We're using DOM manipulation for the back to top button visibility

// Agent chat state
const showAgentChat = ref(false);

// Preloader state
const isLoading = ref(true);
const contentVisible = ref(false);

// Handle page load
onMounted(() => {
    // Simulate loading time or wait for resources
    setTimeout(() => {
        isLoading.value = false;
        // Add a small delay before showing content for smooth transition
        setTimeout(() => {
            contentVisible.value = true;
        }, 400);
    }, 2000); // 2 seconds loading time
});

// Industry use cases data
const industryCases: IndustryCase[] = [
    {
        id: '1',
        title: 'E-commerce & Retail',
        category: 'Sales & Customer Experience',
        description: 'AI agents that handle customer inquiries, personalize shopping experiences, and manage inventory in real-time.',
        image: '/industry-uses/ecommerce.png',
        icon: 'ShoppingCart',
        iconBg: 'bg-primary/10',
        iconColor: 'text-primary',
        popular: true,
        features: [
            { text: '35% Efficiency Increase' },
            { text: '24/7 Customer Support' }
        ]
    },
    {
        id: '2',
        title: 'Healthcare',
        category: 'Patient Care & Administration',
        description: 'Streamline appointment scheduling, answer patient questions, and assist with administrative tasks.',
        image: '/industry-uses/health.png',
        icon: 'Heart',
        iconBg: 'bg-blue-100',
        iconColor: 'text-blue-600',
        features: [
            { text: '40% Time Saved' },
            { text: 'HIPAA Compliant' }
        ]
    },
    {
        id: '3',
        title: 'Financial Services',
        category: 'Banking & Investment',
        description: 'Automate fraud detection, provide personalized financial advice, and process routine transactions.',
        image: '/industry-uses/finance.png',
        icon: 'PieChart',
        iconBg: 'bg-purple-100',
        iconColor: 'text-purple-600',
        features: [
            { text: 'Enterprise Security' },
            { text: 'Smart Automation' }
        ]
    },
    {
        id: '4',
        title: 'Education',
        category: 'Learning & Teaching',
        description: 'Create personalized learning experiences, answer student questions, and automate administrative tasks.',
        image: '/industry-uses/education.png',
        icon: 'Brain',
        iconBg: 'bg-amber-100',
        iconColor: 'text-amber-600',
        features: [
            { text: 'Personalized Learning' },
            { text: '24/7 Student Support' }
        ]
    },
    {
        id: '5',
        title: 'Real Estate',
        category: 'Property Management',
        description: 'Handle property inquiries, schedule viewings, and provide information about listings and neighborhoods.',
        image: '/industry-uses/estate1.png',
        icon: 'Building2',
        iconBg: 'bg-green-100',
        iconColor: 'text-green-600',
        features: [
            { text: 'Instant Responses' },
            { text: 'Qualified Lead Generation' }
        ]
    }
];

// Computed property for theme text
const themeText = computed(() => {
    switch (selectedTheme.value) {
        case 'light': return 'Light';
        case 'dark': return 'Dark';
        case 'auto': return 'System';
        default: return 'System';
    }
});

// Watch for changes to selectedTheme and update colorMode
watch(selectedTheme, (newTheme: string) => {
    if (newTheme === 'light' || newTheme === 'dark' || newTheme === 'auto') {
        colorMode.value = newTheme;
    }
});

// Watch for external changes to colorMode
watch(colorMode, (newMode: string) => {
    selectedTheme.value = newMode;
});

// Theme icon component based on current theme
const ThemeIcon = computed(() => {
    switch (selectedTheme.value) {
        case 'light': return Sun;
        case 'dark': return Moon;
        default: return Laptop;
    }
});

// Function to scroll to top
const scrollToTop = () => {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
};

// Function to toggle agent chat
const toggleAgentChat = () => {
    showAgentChat.value = !showAgentChat.value;
    // Here you would typically show/hide your chat interface
    console.log('Agent chat toggled:', showAgentChat.value);
};

</script>

<template>

    <Head title="AI Agents Platform - Create Custom AI Agents in Minutes">
        <link rel="preconnect" href="https://rsms.me/" />
        <link rel="stylesheet" href="https://rsms.me/inter/inter.css" />
    </Head>

    <!-- Preloader -->
    <div v-if="isLoading" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900">
        <div class="preloader-container">
            <img src="/logo.png" alt="Rill Technologies Logo" class="h-16 w-auto mb-6 animate-pulse" />
            <div class="preloader-spinner">
                <div class="preloader-circle"></div>
                <div class="preloader-circle"></div>
                <div class="preloader-circle"></div>
            </div>
            <p class="text-white mt-6 text-lg font-medium">Loading amazing experiences...</p>
        </div>
    </div>

    <!-- Main Content with Transition -->
    <div class="bg-gray-900 text-gray-50 dark:bg-gray-900 dark:text-gray-100 transition-opacity duration-1000"
        :class="{ 'opacity-0': !contentVisible, 'opacity-100': contentVisible }">

        <!-- Ads for Welcome page -->
        <AdDisplay
          page="welcome"
        />
        <div class="relative top-0">
            <div class="absolute inset-0 overflow-hidden">
                <img src="/home2.jpg" alt="AI technology background" class="w-full h-full object-cover opacity-70">
                <div class="absolute inset-0 bg-gradient-to-b from-transparent to-gray-900 dark:to-gray-800"></div>
            </div>
            <header class="shadow-md backdrop-blur-lg sticky top-0 z-50 transition-colors duration-300">
                <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
                    <div class="flex items-center">
                        <img src="/logo.png" alt="Rill Technologies Logo" class="h-8 w-auto mr-2" />
                        <span class="text-white">Rill Technologies</span>
                    </div>

                    <!-- Mobile Menu -->
                    <div class="flex md:hidden">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="ghost" size="icon" class="text-white hover:bg-white/10">
                                    <MenuIcon class="h-5 w-5" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end"
                                class="w-56 bg-gray-900/95 backdrop-blur-md border border-gray-700 rounded-xl shadow-xl p-1">
                                <div
                                    class="px-2 py-1.5 text-xs font-medium text-gray-400 border-b border-gray-800 mb-1">
                                    Theme Settings
                                </div>
                                <DropdownMenuItem @select="selectedTheme = 'light'"
                                    class="cursor-pointer rounded-lg transition-colors hover:bg-gray-800 focus:bg-gray-800 my-1">
                                    <Sun class="h-4 w-4 text-orange-500 mx-auto" />
                                    <!-- <span class="text-white">Light</span> -->
                                </DropdownMenuItem>
                                <DropdownMenuItem @select="selectedTheme = 'dark'"
                                    class="cursor-pointer rounded-lg transition-colors hover:bg-gray-800 focus:bg-gray-800 my-1">
                                    <Moon class="h-4 w-4 text-indigo-500 mx-auto" />
                                    <!-- <span class="text-white">Dark</span> -->
                                </DropdownMenuItem>
                                <DropdownMenuItem @select="selectedTheme = 'auto'"
                                    class="cursor-pointer rounded-lg transition-colors hover:bg-gray-800 focus:bg-gray-800 my-1">
                                    <Laptop class="h-4 w-4 text-gray-400 mx-auto" />
                                    <!-- <span class="text-white">System</span> -->
                                </DropdownMenuItem>

                                <div class="h-px bg-gray-800 my-2" />

                                <DropdownMenuItem asChild>
                                    <Link :href="route('home')"
                                        class="w-full py-2.5 px-3 rounded-lg bg-primary/20 text-white font-medium justify-center transition-all duration-300 border-l-2 border-primary">
                                    Home
                                    </Link>
                                </DropdownMenuItem>

                                <DropdownMenuItem asChild>
                                    <Link :href="route('pricing')"
                                        class="w-full py-2.5 px-3 rounded-lg  text-white font-medium justify-center transition-all duration-300 ">
                                    Pricing
                                    </Link>
                                </DropdownMenuItem>

                                <div class="h-px bg-gray-800 my-2" />

                                <template v-if="page.props.auth.user">
                                    <div class="px-2 py-3">
                                        <DropdownMenuItem asChild>
                                            <Link :href="route('dashboard')"
                                                class="w-full py-2.5 px-3 rounded-lg bg-gradient-to-r from-primary to-purple-600 text-white hover:from-primary/90 hover:to-purple-500 font-medium justify-center transition-all duration-300 shadow-md hover:shadow-lg hover:scale-[1.02]">
                                            Dashboard
                                            </Link>
                                        </DropdownMenuItem>
                                    </div>
                                </template>
                                <template v-else>
                                    <div class="px-2 py-3">
                                        <DropdownMenuItem asChild class="mb-2">
                                            <Link :href="route('login')"
                                                class="w-full py-2.5 px-3 rounded-lg bg-white/10 backdrop-blur-sm border border-white/20 text-white hover:bg-white/20 font-medium justify-center transition-all duration-300 shadow-md hover:shadow-lg">
                                            Log in
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem asChild>
                                            <Link :href="route('register')"
                                                class="w-full py-2.5 px-3 rounded-lg bg-gradient-to-r from-primary to-purple-600 text-white hover:from-primary/90 hover:to-purple-500 font-medium justify-center transition-all duration-300 shadow-md hover:shadow-lg hover:scale-[1.02]">
                                            Register
                                            </Link>
                                        </DropdownMenuItem>
                                    </div>
                                </template>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    <!-- Desktop Menu -->
                    <div class="hidden md:flex items-center space-x-4">
                        <div class="relative text-white hover:text-gray-300 transition-colors font-medium">
                            <div class="absolute -bottom-1 left-0 right-0 h-0.5 bg-primary rounded-full"></div>
                            <span>Home</span>
                        </div>
                        <Link :href="route('pricing')" class="text-white hover:text-gray-300 transition-colors">
                        Pricing
                        </Link>

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="sm" class="gap-2">
                                    <component :is="ThemeIcon" class="h-4 w-4 text-orange-400" />
                                    <!-- <span class="hidden sm:inline-block text-blue-600">{{ themeText }}</span> -->
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="center" class="w-0 mx-auto">
                                <DropdownMenuItem @select="selectedTheme = 'light'" class="cursor-pointer ">
                                    <Sun class="h-4 w-4 text-orange-500 mx-auto" />
                                    <!-- <span>Light</span> -->
                                </DropdownMenuItem>
                                <DropdownMenuItem @select="selectedTheme = 'dark'" class="cursor-pointer">
                                    <Moon class="h-4 w-4 text-indigo-500 mx-auto" />
                                    <!-- <span>Dark</span> -->
                                </DropdownMenuItem>
                                <DropdownMenuItem @select="selectedTheme = 'auto'" class="cursor-pointer">
                                    <Laptop class="h-4 w-4 text-gray-500 dark:text-gray-400 mx-auto" />
                                    <!-- <span>System</span> -->
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <template v-if="page.props.auth.user">
                            <Link :href="route('dashboard')"
                                class="inline-flex items-center justify-center rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground shadow-sm hover:bg-primary/90 h-9 px-4 py-2">
                            Dashboard
                            </Link>
                        </template>
                        <template v-else>
                            <div class="flex items-center gap-3">
                                <Link :href="route('login')"
                                    class="inline-flex items-center justify-center rounded-full md:rounded-md text-sm font-medium transition-all duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/50 disabled:pointer-events-none disabled:opacity-50 bg-white/10 backdrop-blur-sm border border-white/20 text-white hover:bg-white/20 h-10 md:h-9 px-5 md:px-4 py-2 shadow-lg hover:shadow-xl">
                                <span class="relative z-10">Log in</span>
                                </Link>
                                <Link :href="route('register')"
                                    class="inline-flex items-center justify-center rounded-full md:rounded-md text-sm font-medium transition-all duration-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/50 disabled:pointer-events-none disabled:opacity-50 bg-gradient-to-r from-primary to-purple-600 text-white hover:from-primary/90 hover:to-purple-500 h-10 md:h-9 px-5 md:px-4 py-2 shadow-lg hover:shadow-xl hover:scale-105">
                                <span class="relative z-10">Register</span>
                                </Link>
                            </div>
                        </template>
                    </div>
                </div>
            </header>
            <section class="pt-24 pb-16 md:pt-40 md:pb-24 overflow-hidden bg-gray-900 dark:bg-gray-800">
                <div class="container mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                    <div class="flex flex-col items-center text-center">
                        <h1
                            class="text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-4 max-w-3xl dark:text-gray-100">
                            Create Custom <span class="text-primary">AI Agents</span> in Minutes</h1>
                        <p class="text-lg md:text-xl text-gray-300 mb-8 max-w-xl dark:text-gray-400">Empower your
                            business with intelligent AI agents tailored to your specific needs. No coding required.</p>
                        <div class="flex flex-col sm:flex-row gap-4 mb-12">
                            <a href="#"
                                class="bg-primary text-white px-8 py-3 rounded-lg font-medium hover:bg-primary/90 whitespace-nowrap cta-button text-center pulse">Create
                                Your Agent</a>
                            <a href="#"
                                class="border border-gray-300 bg-white/80 text-gray-800 px-8 py-3 rounded-lg font-medium hover:bg-white whitespace-nowrap text-center">Watch
                                Demo</a>
                        </div>
                        <div class="flex items-center space-x-6">
                            <div class="flex -space-x-3">
                                <div
                                    class="w-10 h-10 rounded-full border-2 border-white overflow-hidden bg-gray-200 flex items-center justify-center">
                                    <User class="text-gray-500 w-5 h-5" />
                                </div>
                                <div
                                    class="w-10 h-10 rounded-full border-2 border-white overflow-hidden bg-gray-200 flex items-center justify-center">
                                    <User class="text-gray-500 w-5 h-5" />
                                </div>
                                <div
                                    class="w-10 h-10 rounded-full border-2 border-white overflow-hidden bg-gray-200 flex items-center justify-center">
                                    <User class="text-gray-500 w-5 h-5" />
                                </div>
                            </div>
                            <p class="text-gray-300 text-sm dark:text-gray-400"><span
                                    class="font-semibold">2,500+</span> businesses already using our platform</p>
                        </div>

                        <!-- How It Works Section -->
                        <div class="mt-16 mb-8">
                            <h3 class="text-xl text-white mb-6 dark:text-gray-100">How It Works</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 max-w-3xl mx-auto">
                                <!-- Step 1 Card -->
                                <div
                                    class="bg-white/10 backdrop-blur-sm p-6 rounded-xl border border-white/20 hover:bg-white/20 transition duration-300 group dark:bg-gray-800/50 dark:border-gray-700/50">
                                    <div class="text-center h-full flex flex-col justify-between">
                                        <div>
                                            <div
                                                class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-primary/10 mb-4 group-hover:scale-110 transition-transform">
                                                <Settings class="text-primary w-6 h-6" />
                                            </div>
                                            <h4 class="text-white text-lg font-semibold mb-2 dark:text-gray-100">1.
                                                Configure Your Agent
                                            </h4>
                                            <p class="text-gray-300 text-sm mb-4 dark:text-gray-400">Choose your agent's
                                                purpose, personality, and knowledge base. Customize its appearance and
                                                communication style.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 2 Card -->
                                <div
                                    class="bg-white/10 backdrop-blur-sm p-6 rounded-xl border border-white/20 hover:bg-white/20 transition duration-300 group dark:bg-gray-800/50 dark:border-gray-700/50">
                                    <div class="text-center h-full flex flex-col justify-between">
                                        <div>
                                            <div
                                                class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-primary/10 mb-4 group-hover:scale-110 transition-transform">
                                                <Brain class="text-primary w-6 h-6" />
                                            </div>
                                            <h4 class="text-white text-lg font-semibold mb-2 dark:text-gray-100">
                                                2. Train Your Agent</h4>
                                            <p class="text-gray-300 text-sm mb-4 dark:text-gray-400">Upload your data or
                                                connect to your existing systems. Our platform handles the complex AI
                                                training automatically.</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step 3 Card -->
                                <div
                                    class="bg-white/10 backdrop-blur-sm p-6 rounded-xl border border-white/20 hover:bg-white/20 transition duration-300 group dark:bg-gray-800/50 dark:border-gray-700/50">
                                    <div class="text-center h-full flex flex-col justify-between">
                                        <div>
                                            <div
                                                class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-primary/10 mb-4 group-hover:scale-110 transition-transform">
                                                <Rocket class="text-primary w-6 h-6" />
                                            </div>
                                            <h4 class="text-white text-lg font-semibold mb-2 dark:text-gray-100">3.
                                                Deploy & Scale
                                            </h4>
                                            <p class="text-gray-300 text-sm mb-4 dark:text-gray-400">Integrate your
                                                agent into your website, app, or internal tools with our simple API or
                                                no-code widgets.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>


        <!-- Powerful Features Section -->
        <section id="features" class="py-20 bg-white dark:bg-gray-900">
            <div class="container mx-auto px-6">
                <div class="flex flex-col md:flex-row items-center gap-12 md:gap-16">
                    <div class="w-full md:w-1/2">
                        <div class="text-center md:text-left mb-8 md:mb-0">
                            <div
                                class="inline-block px-3 py-1 text-xs font-medium text-primary bg-primary/10 rounded-full mb-3">
                                Features</div>
                            <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4 dark:text-gray-100">Powerful
                                Features for Every Business Need</h2>
                            <p class="text-lg text-gray-600 mb-8 dark:text-gray-400">Our platform offers a comprehensive
                                suite of tools to create AI agents that truly understand your business.</p>
                        </div>
                        <div class="space-y-6">
                            <div class="flex items-start">
                                <div
                                    class="w-10 h-10 flex-shrink-0 bg-green-100 rounded-full flex items-center justify-center mr-4 mt-1 dark:bg-green-900">
                                    <Check class="text-green-600 w-5 h-5 dark:text-green-400" />
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900 mb-2 dark:text-gray-100">Natural
                                        Language Understanding</h3>
                                    <p class="text-gray-600 dark:text-gray-400">Our agents understand context, nuance,
                                        and industry-specific terminology.</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div
                                    class="w-10 h-10 flex-shrink-0 bg-green-100 rounded-full flex items-center justify-center mr-4 mt-1 dark:bg-green-900">
                                    <Check class="text-green-600 w-5 h-5 dark:text-green-400" />
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900 mb-2 dark:text-gray-100">
                                        Multi-channel Integration</h3>
                                    <p class="text-gray-600 dark:text-gray-400">Deploy your agent across web, mobile,
                                        chat, email, and voice channels.</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <div
                                    class="w-10 h-10 flex-shrink-0 bg-green-100 rounded-full flex items-center justify-center mr-4 mt-1 dark:bg-green-900">
                                    <Check class="text-green-600 w-5 h-5 dark:text-green-400" />
                                </div>
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-900 mb-2 dark:text-gray-100">Continuous
                                        Learning</h3>
                                    <p class="text-gray-600 dark:text-gray-400">Agents improve over time based on
                                        interactions and feedback.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="w-full md:w-1/2 mt-10 md:mt-0">
                        <div
                            class="bg-gray-50 rounded-2xl p-6 md:p-8 shadow-sm border border-gray-100 dark:bg-gray-800 dark:border-gray-700">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div class="bg-white rounded-xl p-5 md:p-6 shadow-sm dark:bg-gray-700">
                                    <div
                                        class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mb-4 dark:bg-blue-900">
                                        <Headset class="text-blue-600 w-6 h-6 dark:text-blue-400" />
                                    </div>
                                    <h4 class="text-lg font-semibold text-gray-900 mb-2 dark:text-gray-100">Customer
                                        Support</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">24/7 support with instant
                                        responses to common questions.</p>
                                </div>
                                <div class="bg-white rounded-xl p-5 md:p-6 shadow-sm dark:bg-gray-700">
                                    <div
                                        class="w-12 h-12 bg-purple-100 rounded-full flex items-center justify-center mb-4 dark:bg-purple-900">
                                        <ShoppingCart class="text-purple-600 w-6 h-6 dark:text-purple-400" />
                                    </div>
                                    <h4 class="text-lg font-semibold text-gray-900 mb-2 dark:text-gray-100">Sales
                                        Assistant</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Guide customers to the right
                                        products and increase conversions.</p>
                                </div>
                                <div class="bg-white rounded-xl p-5 md:p-6 shadow-sm dark:bg-gray-700">
                                    <div
                                        class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center mb-4 dark:bg-amber-900">
                                        <Search class="text-amber-600 w-6 h-6 dark:text-amber-400" />
                                    </div>
                                    <h4 class="text-lg font-semibold text-gray-900 mb-2 dark:text-gray-100">Research
                                        Agent</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Analyze data and generate
                                        insights for your business.</p>
                                </div>
                                <div class="bg-white rounded-xl p-5 md:p-6 shadow-sm dark:bg-gray-700">
                                    <div
                                        class="w-12 h-12 bg-rose-100 rounded-full flex items-center justify-center mb-4 dark:bg-rose-900">
                                        <CalendarCheck class="text-rose-600 w-6 h-6 dark:text-rose-400" />
                                    </div>
                                    <h4 class="text-lg font-semibold text-gray-900 mb-2 dark:text-gray-100">Scheduling
                                        Bot</h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Manage appointments and meetings
                                        automatically.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Trusted by Industry Leaders Section -->
        <section class="py-20 bg-white overflow-hidden dark:bg-gray-900">
            <div class="container mx-auto px-6">
                <div class="text-center mb-16">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4 dark:text-gray-100">Trusted by Industry
                        Leaders</h2>
                    <p class="text-lg text-gray-600 max-w-2xl mx-auto dark:text-gray-400">Join thousands of businesses
                        that have transformed their operations with our AI agents.</p>
                </div>
                <div class="companies-slider relative">
                    <div class="companies-track flex items-center gap-16">
                        <div class="flex items-center gap-16 animate-slide">
                            <div class="flex flex-col items-center flex-shrink-0">
                                <div class="w-16 h-16 flex items-center justify-center mb-4">
                                    <i class="fab fa-microsoft text-3x text-gray-400"></i>
                                </div>
                                <p class="text-gray-600 font-medium dark:text-gray-400">Microsoft</p>
                            </div>
                            <div class="flex flex-col items-center flex-shrink-0">
                                <div class="w-16 h-16 flex items-center justify-center mb-4">
                                    <i class="fab fa-amazon text-3x text-gray-400"></i>
                                </div>
                                <p class="text-gray-600 font-medium dark:text-gray-400">Amazon</p>
                            </div>
                            <div class="flex flex-col items-center flex-shrink-0">
                                <div class="w-16 h-16 flex items-center justify-center mb-4">
                                    <i class="fab fa-netflix text-3x text-gray-400"></i>
                                </div>
                                <p class="text-gray-600 font-medium dark:text-gray-400">Netflix</p>
                            </div>
                            <div class="flex flex-col items-center flex-shrink-0">
                                <div class="w-16 h-16 flex items-center justify-center mb-4">
                                    <i class="fab fa-google text-3x text-gray-400"></i>
                                </div>
                                <p class="text-gray-600 font-medium dark:text-gray-400">Google</p>
                            </div>
                            <div class="flex flex-col items-center flex-shrink-0">
                                <div class="w-16 h-16 flex items-center justify-center mb-4">
                                    <i class="fab fa-apple text-3x text-gray-400"></i>
                                </div>
                                <p class="text-gray-600 font-medium dark:text-gray-400">Apple</p>
                            </div>
                            <div class="flex flex-col items-center flex-shrink-0">
                                <div class="w-16 h-16 flex items-center justify-center mb-4">
                                    <i class="fab fa-facebook text-3x text-gray-400"></i>
                                </div>
                                <p class="text-gray-600 font-medium dark:text-gray-400">Facebook</p>
                            </div>
                            <div class="flex flex-col items-center flex-shrink-0">
                                <div class="w-16 h-16 flex items-center justify-center mb-4">
                                    <i class="fab fa-twitter text-3x text-gray-400"></i>
                                </div>
                                <p class="text-gray-600 font-medium dark:text-gray-400">Twitter</p>
                            </div>
                            <div class="flex flex-col items-center flex-shrink-0">
                                <div class="w-16 h-16 flex items-center justify-center mb-4">
                                    <img src="/osviatech.png" alt="OsviaTech Logo" class="h-10 w-auto" />
                                </div>
                                <p class="text-gray-600 font-medium dark:text-gray-400">OsviaTech</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-16 animate-slide">
                            <div class="flex flex-col items-center flex-shrink-0">
                                <div class="w-16 h-16 flex items-center justify-center mb-4">
                                    <i class="fab fa-microsoft text-3x text-gray-400"></i>
                                </div>
                                <p class="text-gray-600 font-medium dark:text-gray-400">Microsoft</p>
                            </div>
                            <div class="flex flex-col items-center flex-shrink-0">
                                <div class="w-16 h-16 flex items-center justify-center mb-4">
                                    <i class="fab fa-amazon text-3x text-gray-400"></i>
                                </div>
                                <p class="text-gray-600 font-medium dark:text-gray-400">Amazon</p>
                            </div>
                            <div class="flex flex-col items-center flex-shrink-0">
                                <div class="w-16 h-16 flex items-center justify-center mb-4">
                                    <i class="fab fa-netflix text-3x text-gray-400"></i>
                                </div>
                                <p class="text-gray-600 font-medium dark:text-gray-400">Netflix</p>
                            </div>
                            <div class="flex flex-col items-center flex-shrink-0">
                                <div class="w-16 h-16 flex items-center justify-center mb-4">
                                    <i class="fab fa-google text-3x text-gray-400"></i>
                                </div>
                                <p class="text-gray-600 font-medium dark:text-gray-400">Google</p>
                            </div>
                            <div class="flex flex-col items-center flex-shrink-0">
                                <div class="w-16 h-16 flex items-center justify-center mb-4">
                                    <i class="fab fa-apple text-3x text-gray-400"></i>
                                </div>
                                <p class="text-gray-600 font-medium dark:text-gray-400">Apple</p>
                            </div>
                            <div class="flex flex-col items-center flex-shrink-0">
                                <div class="w-16 h-16 flex items-center justify-center mb-4">
                                    <i class="fab fa-facebook text-3x text-gray-400"></i>
                                </div>
                                <p class="text-gray-600 font-medium dark:text-gray-400">Facebook</p>
                            </div>
                            <div class="flex flex-col items-center flex-shrink-0">
                                <div class="w-16 h-16 flex items-center justify-center mb-4">
                                    <i class="fab fa-twitter text-3x text-gray-400"></i>
                                </div>
                                <p class="text-gray-600 font-medium dark:text-gray-400">Twitter</p>
                            </div>
                            <div class="flex flex-col items-center flex-shrink-0">
                                <div class="w-16 h-16 flex items-center justify-center mb-4">
                                    <img src="/osviatech.png" alt="OsviaTech Logo" class="h-10 w-auto" />
                                </div>
                                <p class="text-gray-600 font-medium dark:text-gray-400">OsviaTech</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Industry Use Cases Section -->
        <section id="industries" class="py-16 md:py-24 bg-white dark:bg-gray-900">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <div class="mb-2">
                        <span
                            class="inline-block px-3 py-1 text-xs font-medium text-primary bg-primary/10 rounded-full">Industry
                            Solutions</span>
                    </div>
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4 dark:text-gray-100">Industry Use Cases
                    </h2>
                    <p class="text-lg text-gray-600 max-w-2xl mx-auto dark:text-gray-400">See how AI agents are
                        transforming different sectors and delivering measurable results</p>
                </div>

                <ScrollArea
                    class="w-full whitespace-nowrap rounded-xl border border-gray-100 dark:border-gray-700 p-4 industry-scroll">
                    <div class="flex space-x-6 pb-4">
                        <div v-for="industry in industryCases" :key="industry.id"
                            class="shrink-0 w-[300px] md:w-[350px]">
                            <div
                                class="bg-white rounded-xl shadow-md overflow-hidden border border-gray-100 transition duration-300 hover:shadow-lg hover:border-primary/20 dark:bg-gray-800 dark:border-gray-700 h-full">
                                <div class="relative">
                                    <img :src="industry.image" :alt="industry.title" class="w-full h-auto object-fit">
                                    <div v-if="industry.popular"
                                        class="absolute top-0 right-0 bg-primary/90 text-white text-xs font-medium px-2 py-1 m-2 rounded">
                                        Popular
                                    </div>
                                </div>
                                <div class="p-6">
                                    <div
                                        :class="[industry.iconBg, 'w-12 h-12 rounded-full flex items-center justify-center mb-4 dark:bg-opacity-20']">
                                        <ShoppingCart v-if="industry.icon === 'ShoppingCart'"
                                            :class="[industry.iconColor, 'w-6 h-6 dark:text-opacity-90']" />
                                        <Heart v-else-if="industry.icon === 'Heart'"
                                            :class="[industry.iconColor, 'w-6 h-6 dark:text-opacity-90']" />
                                        <PieChart v-else-if="industry.icon === 'PieChart'"
                                            :class="[industry.iconColor, 'w-6 h-6 dark:text-opacity-90']" />
                                        <Brain v-else-if="industry.icon === 'Brain'"
                                            :class="[industry.iconColor, 'w-6 h-6 dark:text-opacity-90']" />
                                        <Building2 v-else-if="industry.icon === 'Building2'"
                                            :class="[industry.iconColor, 'w-6 h-6 dark:text-opacity-90']" />
                                    </div>
                                    <h3 class="text-xl font-bold text-gray-900 mb-2 dark:text-gray-100">{{
                                        industry.title }}</h3>
                                    <div class="flex items-center text-gray-700 mb-3 dark:text-gray-300">
                                        <span class="font-medium">{{ industry.category }}</span>
                                    </div>
                                    <p class="text-gray-600 mb-6 dark:text-gray-400 text-wrap">{{ industry.description }}</p>
                                    <div class="flex flex-col space-y-2">
                                        <div v-for="(feature, index) in industry.features" :key="index"
                                            class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                                            <div
                                                class="w-5 h-5 bg-green-100 rounded-full flex items-center justify-center mr-2 dark:bg-green-900">
                                                <Check class="text-green-600 w-3 h-3 dark:text-green-400" />
                                            </div>
                                            <span>{{ feature.text }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <ScrollBar orientation="horizontal" />
                </ScrollArea>

                <div class="text-center mt-8">
                    <a href="#" class="inline-flex items-center text-primary font-medium hover:text-primary/80">
                        View all industry solutions
                        <ArrowRight class="ml-2 w-4 h-4" />
                    </a>
                </div>
            </div>
        </section>

        <!-- Testimonials Section -->
        <section class="py-16 md:py-24 bg-gradient-to-br from-background to-secondary/5 dark:bg-gray-900">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 mb-4 dark:text-gray-100">What Our Clients
                        Say</h2>
                    <p class="text-gray-600 dark:text-gray-400">Hear from businesses that have transformed their
                        operations with our AI agents.</p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Testimonial 1 -->
                    <div class="bg-white p-8 rounded-xl shadow-sm dark:bg-gray-800">
                        <div class="flex items-center mb-6">
                            <div class="text-amber-400 flex">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                        <p class="text-gray-600 mb-6 dark:text-gray-300">"Our customer support team was overwhelmed with
                            repetitive questions. After implementing an AI agent, we reduced response time by 85% and
                            improved customer satisfaction scores."</p>
                        <div class="flex items-center">
                            <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center mr-4">
                                <i class="fas fa-user text-gray-500"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold dark:text-gray-100">Sarah Johnson</h4>
                                <p class="text-gray-500 text-sm dark:text-gray-400">CTO, TechSolutions Inc.</p>
                            </div>
                        </div>
                    </div>
                    <!-- Testimonial 2 -->
                    <div class="bg-white p-8 rounded-xl shadow-sm dark:bg-gray-800">
                        <div class="flex items-center mb-6">
                            <div class="text-amber-400 flex">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                        <p class="text-gray-600 mb-6 dark:text-gray-300">"The sales assistant AI has been a game-changer
                            for our e-commerce business. It's like having a knowledgeable salesperson available 24/7,
                            resulting in a 40% increase in conversion rates."</p>
                        <div class="flex items-center">
                            <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center mr-4">
                                <i class="fas fa-user text-gray-500"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold dark:text-gray-100">Michael Chen</h4>
                                <p class="text-gray-500 text-sm dark:text-gray-400">Founder, StyleHub</p>
                            </div>
                        </div>
                    </div>
                    <!-- Testimonial 3 -->
                    <div class="bg-white p-8 rounded-xl shadow-sm dark:bg-gray-800">
                        <div class="flex items-center mb-6">
                            <div class="text-amber-400 flex">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star-half-alt"></i>
                            </div>
                        </div>
                        <p class="text-gray-600 mb-6 dark:text-gray-300">"Setting up our AI agent was surprisingly easy.
                            Within a week, it was handling 70% of our customer inquiries, allowing our team to focus on
                            more complex issues and strategic initiatives."</p>
                        <div class="flex items-center">
                            <div class="w-12 h-12 rounded-full bg-gray-200 flex items-center justify-center mr-4">
                                <i class="fas fa-user text-gray-500"></i>
                            </div>
                            <div>
                                <h4 class="font-semibold dark:text-gray-100">Emily Rodriguez</h4>
                                <p class="text-gray-500 text-sm dark:text-gray-400">COO, HealthPlus</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 bg-white dark:bg-gray-900">
            <div class="container mx-auto px-6">
                <div class="bg-gradient-to-r from-primary to-secondary rounded-2xl overflow-hidden shadow-lg">
                    <div class="p-8 md:p-12 flex flex-col md:flex-row items-center">
                        <div class="w-full md:w-2/3 text-white mb-8 md:mb-0">
                            <h2 class="text-3xl md:text-4xl font-bold mb-4">Ready to Transform Your Business?</h2>
                            <p class="text-white/90 text-lg mb-6">Join thousands of companies using AI agents to improve
                                efficiency, reduce costs, and enhance customer experiences.</p>
                            <div class="flex flex-col sm:flex-row gap-4">
                                <a href="#"
                                    class="bg-white text-primary px-8 py-3 rounded-lg font-medium hover:bg-white/90 whitespace-nowrap text-center">Get
                                    Started Free</a>
                                <a href="#"
                                    class="border border-white/30 text-white px-8 py-3 rounded-lg font-medium hover:bg-white/10 whitespace-nowrap text-center">Schedule
                                    Demo</a>
                            </div>
                        </div>
                        <div class="w-full md:w-1/3">
                            <div class="bg-white/10 backdrop-blur-sm rounded-xl p-6 border border-white/20">
                                <h3 class="text-white font-semibold text-xl mb-4">Start Your 14-Day Free Trial</h3>
                                <div class="space-y-4">
                                    <div>
                                        <input type="text" placeholder="Your Name"
                                            class="w-full bg-white/20 border border-white/30 rounded text-white placeholder-white/70 px-4 py-2 focus:bg-white/30">
                                    </div>
                                    <div>
                                        <input type="email" placeholder="Your Email"
                                            class="w-full bg-white/20 border border-white/30 rounded text-white placeholder-white/70 px-4 py-2 focus:bg-white/30">
                                    </div>
                                    <div>
                                        <input type="text" placeholder="Company Name"
                                            class="w-full bg-white/20 border border-white/30 rounded text-white placeholder-white/70 px-4 py-2 focus:bg-white/30">
                                    </div>
                                    <button
                                        class="w-full bg-white text-primary font-medium py-2 rounded hover:bg-white/90 whitespace-nowrap">Start
                                        Free Trial</button>
                                    <p class="text-white/70 text-xs text-center">No credit card required</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Newsletter Section -->
        <section class="py-16 bg-gray-50 dark:bg-gray-800">
            <div class="container mx-auto px-4 sm:px-6 lg:px-8">
                <div class="max-w-3xl mx-auto text-center">
                    <div class="mb-2">
                        <span
                            class="inline-block px-3 py-1 text-xs font-medium text-primary bg-primary/10 rounded-full">Newsletter</span>
                    </div>
                    <h2 class="text-3xl md:text-4xl font-bold mb-4 text-gray-900 dark:text-gray-100">Stay Updated</h2>
                    <p class="text-gray-600 text-lg mb-8 max-w-xl mx-auto dark:text-gray-400">Get the latest news and
                        updates about AI agent technology and product releases.</p>
                    <div class="flex flex-col sm:flex-row max-w-md mx-auto">
                        <div class="relative flex-grow mb-3 sm:mb-0">
                            <input type="email" placeholder="Your email address"
                                class="w-full px-4 py-3 rounded-lg sm:rounded-r-none border border-gray-200 focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-gray-100">
                        </div>
                        <button
                            class="bg-primary text-white px-6 py-3 rounded-lg sm:rounded-l-none font-medium hover:bg-primary/90 transition-colors">
                            Subscribe
                        </button>
                    </div>
                    <p class="text-gray-500 text-sm mt-4 dark:text-gray-500">We respect your privacy. Unsubscribe at any
                        time.</p>
                </div>
            </div>
        </section>
    </div>

    <!-- Back to Top Button -->
    <button id="back-to-top" @click="scrollToTop"
        class="fixed bottom-24 right-6 z-50 p-2 rounded-full bg-primary text-white shadow-lg transition-opacity duration-300 opacity-0 pointer-events-none"
        aria-label="Back to top">
        <ArrowUp class="h-6 w-6" />
    </button>

    <!-- Agent Chat Interface -->
    <div class="fixed bottom-20 right-6 z-50 w-80 md:w-96 lg:w-[450px] bg-background rounded-lg shadow-xl transition-all duration-300 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden"
        :class="{ 'opacity-0 pointer-events-none translate-y-4': !showAgentChat, 'opacity-100 translate-y-0': showAgentChat }">
        <div class="bg-primary p-4 md:p-5 flex justify-between items-center">
            <div class="flex items-center text-white">
                <img src="/logo.png" alt="Rill Technologies Logo" class="h-5 w-auto md:h-6 mr-2 md:mr-3" />
                <span class="font-medium text-base md:text-lg">AI Assistant</span>
            </div>
            <button @click="toggleAgentChat"
                class="text-white/80 hover:text-white p-1 rounded-full hover:bg-white/10 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 md:h-6 md:w-6" viewBox="0 0 20 20"
                    fill="currentColor">
                    <path fill-rule="evenodd"
                        d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                        clip-rule="evenodd" />
                </svg>
            </button>
        </div>
        <div class="p-4 md:p-6 h-64 md:h-80 overflow-y-auto bg-gray-50 dark:bg-gray-900">
            <div class="flex items-start mb-6">
                <div class="flex-shrink-0 mr-3 md:mr-4">
                    <div
                        class="h-8 w-8 md:h-10 md:w-10 p-1.5 md:p-2 rounded-full bg-primary/10 flex items-center justify-center">
                        <img src="/logo.png" alt="Rill Technologies Logo" class="h-full w-auto" />
                    </div>
                </div>
                <div class="bg-white p-3 md:p-4 rounded-lg shadow-sm max-w-[85%] dark:bg-gray-800 dark:text-gray-100">
                    <p class="text-sm md:text-base">Hello! I'm your AI assistant. How can I help you today?</p>
                </div>
            </div>

            <div class="flex justify-center my-4">
                <span
                    class="text-xs text-gray-400 bg-gray-100 dark:bg-gray-800 dark:text-gray-500 px-3 py-1 rounded-full">Today,
                    2:30 PM</span>
            </div>
        </div>
        <div class="p-3 md:p-4 border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800">
            <div class="flex items-center">
                <button
                    class="p-2 mr-2 text-gray-400 hover:text-primary rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 md:h-6 md:w-6" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </button>
                <div class="flex-grow relative">
                    <input type="text" placeholder="Type your message..."
                        class="w-full px-4 py-2 md:py-3 border border-gray-300 rounded-l-md md:rounded-l-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent dark:bg-gray-700 dark:border-gray-600 dark:text-white" />
                </div>
                <button
                    class="bg-primary text-white px-4 md:px-6 py-2 md:py-3 rounded-r-md md:rounded-r-lg hover:bg-primary/90 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 md:h-6 md:w-6" viewBox="0 0 20 20"
                        fill="currentColor">
                        <path
                            d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z" />
                    </svg>
                </button>
            </div>
            <div class="mt-2 text-xs text-center text-gray-400 dark:text-gray-500">
                AI responses are generated and may not always be accurate
            </div>
        </div>
    </div>

    <!-- Floating Agent Button -->
    <button @click="toggleAgentChat"
        class="fixed bottom-6 right-6 z-50 p-2 rounded-full bg-primary text-white shadow-lg hover:bg-primary/90 transition-all duration-300 flex items-center justify-center"
        aria-label="Chat with AI Agent">
        <img src="/logo.png" alt="Rill Technologies Logo" class="h-7 w-auto" />
    </button>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white pt-16 pb-8 dark:bg-gray-800">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-12">
                <div>
                    <div class="flex items-center mb-6">
                        <img src="/logo.png" alt="Rill Technologies Logo" class="h-8 w-auto mr-2" />
                        <span class="text-2xl font-bold text-white">Rill Technologies</span>
                    </div>
                    <p class="text-gray-400 mb-6 dark:text-gray-500">Empowering businesses with intelligent AI agents
                        tailored to specific needs.</p>
                    <div class="flex space-x-4">
                        <a href="#"
                            class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center hover:bg-primary/20">
                            <i class="fab fa-twitter text-white"></i>
                        </a>
                        <a href="#"
                            class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center hover:bg-primary/20">
                            <i class="fab fa-linkedin text-white"></i>
                        </a>
                        <a href="#"
                            class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center hover:bg-primary/20">
                            <i class="fab fa-facebook text-white"></i>
                        </a>
                        <a href="#"
                            class="w-10 h-10 rounded-full bg-gray-800 flex items-center justify-center hover:bg-primary/20">
                            <i class="fab fa-instagram text-white"></i>
                        </a>
                    </div>
                </div>
                <div>
                    <h3 class="font-semibold text-lg mb-6">Product</h3>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-gray-400 hover:text-white">Features</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Pricing</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Case Studies</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Documentation</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">API Reference</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-semibold text-lg mb-6">Company</h3>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-gray-400 hover:text-white">About Us</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Careers</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Blog</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Press</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-semibold text-lg mb-6">Support</h3>
                    <ul class="space-y-3">
                        <li><a href="#" class="text-gray-400 hover:text-white">Help Center</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Community</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Status</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Tutorials</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-white">Resources</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-500 text-sm mb-4 md:mb-0">© 2025 Rill Technologies. All rights reserved.</p>
                <div class="flex space-x-6">
                    <a href="#" class="text-gray-500 hover:text-white text-sm">Privacy Policy</a>
                    <a href="#" class="text-gray-500 hover:text-white text-sm">Terms of Service</a>
                    <a href="#" class="text-gray-500 hover:text-white text-sm">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>
</template>

<script lang="ts">
    window.addEventListener('scroll', function () {
        // Header shadow effect
        const header = document.querySelector('header');
        if (header) {
            if (window.scrollY > 50) {
                header.classList.add('shadow-md');
            } else {
                header.classList.remove('shadow-md');
            }
        }

        // Back to top button visibility
        const backToTopButton = document.querySelector('#back-to-top');
        if (backToTopButton) {
            if (window.scrollY > 500) {
                backToTopButton.classList.remove('opacity-0', 'pointer-events-none');
                backToTopButton.classList.add('opacity-100');
            } else {
                backToTopButton.classList.add('opacity-0', 'pointer-events-none');
                backToTopButton.classList.remove('opacity-100');
            }
        }
    });
</script>

<style>
.companies-slider {
    width: 100%;
    overflow: hidden;
}

.companies-track {
    display: flex;
    width: fit-content;
}

.animate-slide {
    animation: slide 30s linear infinite;
}

@keyframes slide {
    0% {
        transform: translateX(0);
    }
    100% {
        transform: translateX(-100%);
    }
}

.companies-track:hover .animate-slide {
    animation-play-state: paused;
}

.text-primary {
    color: var(--color-primary);
}

.bg-primary {
    background-color: var(--color-primary);
}

.bg-primary\/10 {
    background-color: rgba(99, 102, 241, 0.1);
}

.bg-primary\/90 {
    background-color: rgba(99, 102, 241, 0.9);
}

.hover\:bg-primary\/90:hover {
    background-color: rgba(99, 102, 241, 0.9);
}

.from-primary\/5 {
    --tw-gradient-from: rgba(99, 102, 241, 0.05);
}

.to-secondary\/5 {
    --tw-gradient-to: rgba(168, 85, 247, 0.05);
}

.industry-scroll ::-webkit-scrollbar {
    height: 8px;
}

.industry-scroll ::-webkit-scrollbar-track {
    background: transparent;
    border-radius: 10px;
    margin: 0 10px;
}

.industry-scroll ::-webkit-scrollbar-thumb {
    background: var(--color-primary);
    border-radius: 10px;
    opacity: 0.7;
}

.industry-scroll ::-webkit-scrollbar-thumb:hover {
    background: var(--color-primary);
    opacity: 1;
}

.pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.7);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(99, 102, 241, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(99, 102, 241, 0);
    }
}

/* Preloader Styles */
.preloader-container {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.preloader-spinner {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 8px;
}

.preloader-circle {
    width: 12px;
    height: 12px;
    background-color: var(--color-primary);
    border-radius: 50%;
    animation: preloader-bounce 1.4s infinite ease-in-out both;
}

.preloader-circle:nth-child(1) {
    animation-delay: -0.32s;
}

.preloader-circle:nth-child(2) {
    animation-delay: -0.16s;
}

@keyframes preloader-bounce {
    0%, 80%, 100% {
        transform: scale(0);
        opacity: 0.5;
    }
    40% {
        transform: scale(1);
        opacity: 1;
    }
}
</style>
