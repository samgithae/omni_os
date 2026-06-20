<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl } = useCurrentUrl();

// Check if a URL is an external/non-Inertia route (e.g. Filament admin)
function isExternal(href: NavItem['href']): boolean {
    const str = String(href);
    return str.startsWith('/admin') || str.startsWith('http');
}
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>Omni OS</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentUrl(item.href)"
                    :tooltip="item.title"
                >
                    <!-- Use regular <a> for external/Filament links, Inertia <Link> for app routes -->
                    <a v-if="isExternal(item.href)" :href="String(item.href)">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </a>
                    <Link v-else :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>