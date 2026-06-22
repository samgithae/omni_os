<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Building2, Users, Ban, MapPin, LayoutGrid, FolderGit2, Mail, Activity, Clock, BarChart3, Inbox, Settings } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarGroup,
    SidebarGroupLabel,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

interface NavGroup {
    label: string
    items: NavItem[]
}

const page = usePage();
const unreadReplyCount = computed(() => (page.props.unreadReplyCount as number) || 0);

const navGroups: NavGroup[] = [
    {
        label: 'Overview',
        items: [
            { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
        ],
    },
    {
        label: 'CRM',
        items: [
            { title: 'Leads', href: '/leads', icon: Users },
            { title: 'Inbox', href: '/inbox', icon: Inbox, badge: unreadReplyCount.value },
        ],
    },
    {
        label: 'Analytics',
        items: [
            { title: 'Analytics', href: '/analytics', icon: BarChart3 },
            { title: 'Activity Feed', href: '/activity', icon: Activity },
        ],
    },
    {
        label: 'Configuration',
        items: [
            { title: 'Brand Settings', href: '/brands/ujuziplus/settings', icon: Settings },
        ],
    },
    {
        label: 'Email',
        items: [
            { title: 'Email Sequences', href: '/email-sequences', icon: Mail },
            { title: 'Sequence Schedules', href: '/admin/sequence-schedules', icon: Clock },
            { title: 'Suppressions', href: '/admin/suppressions', icon: Ban },
            { title: 'Mining Targets', href: '/admin/mining-targets', icon: MapPin },
        ],
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];

function isExternal(href: string | { name: string; params?: Record<string, string | number> }): boolean {
    const str = String(href);
    return str.startsWith('/admin') || str.startsWith('http');
}
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <SidebarGroup v-for="group in navGroups" :key="group.label" class="px-2 py-0">
                <SidebarGroupLabel>{{ group.label }}</SidebarGroupLabel>
                <SidebarMenu>
                    <SidebarMenuItem v-for="item in group.items" :key="item.title">
                        <SidebarMenuButton as-child :tooltip="item.title">
                            <a v-if="isExternal(item.href)" :href="String(item.href)">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                                <span v-if="item.badge && item.badge > 0" class="ml-auto rounded-full bg-blue-600 px-1.5 py-0.5 text-[10px] font-bold text-white">{{ item.badge }}</span>
                            </a>
                            <Link v-else :href="item.href">
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                                <span v-if="item.badge && item.badge > 0" class="ml-auto rounded-full bg-blue-600 px-1.5 py-0.5 text-[10px] font-bold text-white">{{ item.badge }}</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
