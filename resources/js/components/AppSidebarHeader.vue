<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import BrandSwitcher from '@/components/BrandSwitcher.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();
</script>

<template>
    <header
        class="relative flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <!-- Brand Switcher — right-aligned -->
        <div class="ml-auto">
            <BrandSwitcher
                :active-brand-id="(page.props.activeBrandId as number | null)"
                :brands="(page.props.brands as any[])"
            />
        </div>
    </header>
</template>
