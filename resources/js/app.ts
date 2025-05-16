import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import { initializeTheme } from './composables/useAppearance';

// Import Echo for real-time notifications
import './echo';

// Import global Lucide icons component
import LucideIcons from './components/global/LucideIcons.vue';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({
            render: () => [
                h(App, props),
                // h(LucideIcons) // Add the global Lucide icons component
            ]
        });

        app.use(plugin)
           .use(ZiggyVue)
           .mount(el);
    },
    progress: {
        color: '#FBBF24', // Amber-400 yellow color
        showSpinner: false, // Default Inertia progress bar doesn't have a spinner
        delay: 0, // Start showing immediately
        includeCSS: true, // Include default CSS
        // Add custom CSS to increase z-index
        css: `
            #nprogress {
                pointer-events: none;
                z-index: 9999 !important; /* Higher z-index to appear above all elements */
            }

            #nprogress .bar {
                background: #FBBF24;
                position: fixed;
                z-index: 9999 !important;
                top: 0;
                left: 0;
                width: 100%;
                height: 3px;
            }
        `,
    },
});

// This will set light / dark mode on page load...
initializeTheme();
