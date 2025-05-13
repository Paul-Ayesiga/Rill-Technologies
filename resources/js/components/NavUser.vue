<script setup lang="ts">
import {
  Avatar,
  AvatarFallback,
  AvatarImage,
} from '@/components/ui/avatar';
import { DropdownMenu, DropdownMenuContent, DropdownMenuGroup, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { SidebarMenu, SidebarMenuButton, SidebarMenuItem, useSidebar } from '@/components/ui/sidebar';
import { type SharedData, type User } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { BadgeCheck, Bell, ChevronsUpDown, CreditCard, Sun, Moon, Laptop } from 'lucide-vue-next';
import UserMenuContent from './UserMenuContent.vue';
import UserInfo from '@/components/UserInfo.vue';
import { useColorMode } from '@vueuse/core';
import { ref, watch } from 'vue';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

const page = usePage<SharedData>();
const user = page.props.auth.user as User;
const { isMobile, state } = useSidebar();

const colorMode = useColorMode();

// Create a ref to track the selected theme
const selectedTheme = ref(colorMode.value);

// Watch for changes to selectedTheme and update colorMode
watch(selectedTheme, (newTheme) => {
  if (newTheme === 'light' || newTheme === 'dark' || newTheme === 'auto') {
    colorMode.value = newTheme;
  }
});

// Watch for external changes to colorMode
watch(colorMode, (newMode) => {
  selectedTheme.value = newMode;
});
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <SidebarMenuButton size="lg" class="data-[state=open]:bg-sidebar-primary data-[state=open]:text-sidebar-navy">
                        <UserInfo :user="user" :show-email="true" />
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    class="w-[--reka-dropdown-menu-trigger-width] min-w-56 rounded-lg dark:shadow-blue-300 shadow-sm"
                    :side="isMobile ? 'bottom' : state === 'collapsed' ? 'left' : 'left'"
                    align="end"
                    :side-offset="4"
                >
                    <div class="p-2">
                        <ToggleGroup
                            type="single"
                            variant="outline"
                            size="sm"
                            class="w-full grid grid-cols-3 gap-1"
                            v-model="selectedTheme"
                        >
                            <ToggleGroupItem value="light" class="flex items-center justify-center gap-1">
                                <Sun class="h-3.5 w-3.5" />
                                <span class="text-xs">Light</span>
                            </ToggleGroupItem>
                            <ToggleGroupItem value="dark" class="flex items-center justify-center gap-1">
                                <Moon class="h-3.5 w-3.5" />
                                <span class="text-xs">Dark</span>
                            </ToggleGroupItem>
                            <ToggleGroupItem value="auto" class="flex items-center justify-center gap-1">
                                <Laptop class="h-3.5 w-3.5" />
                                <span class="text-xs">System</span>
                            </ToggleGroupItem>
                        </ToggleGroup>
                    </div>
                    <DropdownMenuSeparator />

                    <UserMenuContent :user="user" />
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    </SidebarMenu>
</template>
