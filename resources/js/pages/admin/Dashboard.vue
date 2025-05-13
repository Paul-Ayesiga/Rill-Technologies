<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import CommandMenu from '@/components/CommandMenu.vue';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, watchEffect, onBeforeUnmount } from 'vue';
import { toast } from 'vue-sonner';

// No Stripe declaration needed
import {
  Card, CardContent, CardDescription, CardHeader, CardTitle
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import {
  Bot, Plus, LineChart, ArrowUpRight, ArrowDownRight,
  Command as CommandIcon, ArrowRight, Users, CreditCard,
  Check, X
} from 'lucide-vue-next';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Admin Dashboard',
        href: '/admin/dashboard',
    },
];

</script>

<template>
  <Head title="Dashboard" />

  <AdminLayout :breadcrumbs="breadcrumbs">

    <div class="flex h-full flex-1 flex-col gap-4 p-4">

    </div>
     
  </AdminLayout>
</template>
