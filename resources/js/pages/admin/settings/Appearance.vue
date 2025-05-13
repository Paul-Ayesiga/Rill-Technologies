<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

import AdminLayout from '@/layouts/AdminLayout.vue';
import AdminSettingsLayout from '@/layouts/settings/AdminSettingsLayout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Admin Appearance settings',
        href: 'admin/settings/appearance',
    },
];
</script>

<template>
    <AdminLayout :breadcrumbs="breadcrumbItems">
        <Head title="Admin Appearance settings" />

        <AdminSettingsLayout>
            <div class="space-y-6">
                <HeadingSmall title="Appearance settings" description="Update your account's appearance settings" />
                <AppearanceTabs />
            </div>
        </AdminSettingsLayout>
    </AdminLayout>
</template>
