
<script setup lang="ts">
import { Head, Link, usePage, router } from '@inertiajs/vue3';
import { ref } from 'vue';

import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
    Monitor,
    Smartphone,
    Tablet,
    Info,
    Trash
} from 'lucide-vue-next';

import AdminLayout from '@/layouts/AdminLayout.vue';
import AdminSettingsLayout from '@/layouts/settings/AdminSettingsLayout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Admin Session settings',
        href: 'admin/settings/session',
    },
];

const props = defineProps<{
    sessions: Array<{
        id: string;
        ip_address: string;
        last_active: string;
        user_agent: string;
        device: string;
        is_current_device: boolean;
    }>;
    current_session_id: string;
}>();

const getDeviceIcon = (userAgent: string) => {
    if (/mobile/i.test(userAgent)) return Smartphone;
    if (/tablet/i.test(userAgent)) return Tablet;
    return Monitor;
};

const formatLastActive = (timestamp: string) => {
    const date = new Date(timestamp);
    return date.toLocaleString();
};

// Session termination state
const sessionToTerminate = ref<string | null>(null);
const isCurrentSession = ref(false);
const showTerminateDialog = ref(false);

// Logout from all other devices state
const showLogoutOthersDialog = ref(false);

/**
 * Opens the terminate session dialog
 */
const openTerminateDialog = (sessionId: string) => {
    sessionToTerminate.value = sessionId;
    isCurrentSession.value = sessionId === props.current_session_id;
    showTerminateDialog.value = true;
};

/**
 * Handles the session termination action
 */
const handleTerminateSession = () => {
    showTerminateDialog.value = false;

    const sessionId = sessionToTerminate.value;
    if (!sessionId) return;

    if (sessionId === props.current_session_id) {
        router.post(route('logout'));
    } else {
        router.delete(route('admin.session.destroya', sessionId));
    }

    // Reset state
    sessionToTerminate.value = null;
};

/**
 * Terminates all sessions except the current one
 */
const terminateAllOtherSessions = () => {
    router.post(route('admin.session.logout.others'));
    showLogoutOthersDialog.value = false;
};
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbItems">
        <Head title="Admin Session settings" />

        <AdminSettingsLayout>
            <div class="space-y-6">
                <HeadingSmall
                    title="Active sessions"
                    description="Manage and monitor your active sessions across different devices"
                />

                <Card>
                    <CardHeader>
                        <CardTitle>Your sessions</CardTitle>
                        <CardDescription>
                            These are the devices that are currently logged into your account.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="sessions.length === 0" class="py-4 text-center text-muted-foreground">
                            No active sessions found.
                        </div>

                        <div v-else class="space-y-4">
                            <div
                                v-for="session in sessions"
                                :key="session.id"
                                class="flex items-start justify-between p-3 rounded-md border transition-colors duration-200 hover:bg-muted/50 group"
                            >
                                <div class="flex gap-3">
                                    <component :is="getDeviceIcon(session.user_agent)" class="h-5 w-5 mt-1 group-hover:text-primary transition-colors duration-200" />
                                    <div>
                                        <div class="font-medium flex items-center gap-2">
                                            {{ session.device }}
                                            <span v-if="session.is_current_device" class="text-xs bg-primary/10 text-primary px-2 py-0.5 rounded-full">
                                                Current
                                            </span>
                                        </div>
                                        <div class="text-sm text-muted-foreground">
                                            {{ session.ip_address }}
                                        </div>
                                        <div class="text-xs text-muted-foreground mt-1">
                                            Last active: {{ formatLastActive(session.last_active) }}
                                        </div>
                                    </div>
                                </div>

                                <Button
                                    variant="ghost"
                                    size="sm"
                                    @click="openTerminateDialog(session.id)"
                                    class="text-destructive hover:text-destructive/90 hover:bg-destructive/10 opacity-70 group-hover:opacity-100 transition-opacity duration-200"
                                >
                                    <Trash class="h-4 w-4" />
                                </Button>
                            </div>

                            <Separator />

                            <div class="flex justify-end">
                                <Button
                                    variant="destructive"
                                    @click="showLogoutOthersDialog = true"
                                    :disabled="sessions.length <= 1"
                                >
                                    Log out from all other devices
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Session security</CardTitle>
                        <CardDescription>
                            Tips to keep your account secure
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="flex gap-3 text-sm">
                            <Info class="h-5 w-5 text-primary shrink-0 mt-0.5" />
                            <div>
                                <p class="mb-2">
                                    If you notice any suspicious activity, we recommend that you change your password immediately and log out of all sessions.
                                </p>
                                <p>
                                    For enhanced security, make sure to use a strong, unique password and enable two-factor authentication if available.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AdminSettingsLayout>
    </AdminLayout>

    <!-- Terminate Session Dialog -->
    <AlertDialog v-model:open="showTerminateDialog">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>
                    {{ isCurrentSession ? 'Log out from this device?' : 'Terminate session?' }}
                </AlertDialogTitle>
                <AlertDialogDescription>
                    {{ isCurrentSession
                        ? 'You are about to log out from your current session. You will need to log in again to access your account.'
                        : 'This will terminate the selected session. The user on that device will need to log in again to access their account.'
                    }}
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction
                    @click="handleTerminateSession"
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                >
                    {{ isCurrentSession ? 'Log out' : 'Terminate' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>

    <!-- Logout from All Other Devices Dialog -->
    <AlertDialog v-model:open="showLogoutOthersDialog">
        <AlertDialogContent>
            <AlertDialogHeader>
                <AlertDialogTitle>Log out from all other devices?</AlertDialogTitle>
                <AlertDialogDescription>
                    This will terminate all sessions except your current one. Users on other devices will need to log in again to access their accounts.
                </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
                <AlertDialogCancel>Cancel</AlertDialogCancel>
                <AlertDialogAction
                    @click="terminateAllOtherSessions"
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                >
                    Log out all other devices
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
