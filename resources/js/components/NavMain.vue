<script setup lang="ts">
import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps<{
    items: NavItem[];
}>();

const page = usePage<SharedData>();

// Improved active state detection that checks if the current URL starts with the item's href
// This ensures that sub-routes also highlight the parent menu item
const isActive = computed(() => (href: string) => {
    const currentPath = page.url;
    // For exact matches (like /dashboard)
    if (href === currentPath) return true;

    // For sub-routes (like /agents/123 should highlight /agents)
    // But make sure we don't match /agents-something when checking /agents
    if (href !== '/' && currentPath.startsWith(href) &&
        (currentPath.length === href.length || currentPath[href.length] === '/')) {
        return true;
    }

    return false;
});
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Platform</SidebarGroupLabel>
        <SidebarMenu>
            <template v-for="item in items" :key="item.title">
                <!-- Regular menu item without children -->
                <SidebarMenuItem v-if="!item.children">
                    <SidebarMenuButton
                        as-child
                        :is-active="isActive(item.href)"
                        :tooltip="item.title"
                        :class="isActive(item.href) ? 'active-sidebar-item' : ''"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>

                <!-- Menu item with children -->
                <SidebarMenuItem v-else>
                    <SidebarMenuButton
                        as-child
                        :is-active="item.children.some(child => isActive(child.href))"
                        :tooltip="item.title"
                        :class="item.children.some(child => isActive(child.href)) ? 'active-sidebar-item' : ''"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>

                    <!-- Submenu items -->
                    <SidebarMenu v-if="item.children && item.children.length > 0" class="pl-6 mt-1">
                        <SidebarMenuItem v-for="child in item.children" :key="child.title">
                            <SidebarMenuButton
                                as-child
                                :is-active="isActive(child.href)"
                                :tooltip="child.title"
                                :class="isActive(child.href) ? 'active-sidebar-item' : ''"
                            >
                                <Link :href="child.href">
                                    <span>{{ child.title }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarMenuItem>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
