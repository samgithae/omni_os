<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Users, ArrowUpDown } from '@lucide/vue';
import { dashboard } from '@/routes';
import StatsBar from './components/StatsBar.vue';
import FilterBar from './components/FilterBar.vue';
import LeadRow from './components/LeadRow.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Leads', href: '/leads' },
        ],
    },
});

interface BrandInfo {
    id: number;
    name: string;
    slug: string;
    color: string | null;
}

interface LeadData {
    id: number;
    company_name: string;
    contact_name: string | null;
    email: string | null;
    phone: string | null;
    website: string | null;
    segment: string;
    status: string;
    category: string | null;
    subcategory: string | null;
    city: string | null;
    country: string;
    score: number;
    score_tier: string;
    email_confidence: string | null;
    enrichment_attempts: number;
    email_verified: boolean;
    emails_sent: number;
    emails_opened: number;
    emails_clicked: number;
    total_emails: number;
    brand: BrandInfo | null;
    created_at: string | null;
}

interface LeadStats {
    total: number;
    with_email: number;
    enriched: number;
    suppressed: number;
    avg_score: number;
    max_score: number;
    tier_counts: {
        hot: number;
        warm: number;
        moderate: number;
        cold: number;
        frigid: number;
    };
    segment_counts: Record<string, number>;
    status_counts: Record<string, number>;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    leads: PaginatedData<LeadData>;
    stats: LeadStats;
    filters: Record<string, string | undefined>;
    brands: BrandInfo[];
    cities: string[];
}>();

const activeTierFilter = ref<string | undefined>(props.filters.tier);

function handleTierFilter(tier: string) {
    if (tier === activeTierFilter.value) {
        activeTierFilter.value = undefined;
        const params = new URLSearchParams(window.location.search);
        params.delete('tier');
        params.delete('page');
        const qs = params.toString();
        router.get(
            qs ? `/leads?${qs}` : '/leads',
            {},
            { preserveScroll: true, preserveState: true, replace: true },
        );
    } else {
        activeTierFilter.value = tier;
        const params = new URLSearchParams(window.location.search);
        params.set('tier', tier);
        params.delete('page');
        router.get(
            `/leads?${params.toString()}`,
            {},
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }
}

function sortBy(column: string) {
    const currentSort = props.filters.sort || 'score';
    const currentDir = props.filters.direction || 'desc';

    let direction = 'desc';
    if (currentSort === column && currentDir === 'desc') {
        direction = 'asc';
    }

    const params = new URLSearchParams(window.location.search);
    params.set('sort', column);
    params.set('direction', direction);
    params.delete('page');
    router.get(
        `/leads?${params.toString()}`,
        {},
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

function goToPage(page: number) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', String(page));
    router.get(
        `/leads?${params.toString()}`,
        {},
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

const sortColumn = computed(() => props.filters.sort || 'score');
const sortDirection = computed(() => props.filters.direction || 'desc');
</script>

<template>
    <Head title="Leads" />

    <div class="pb-8">
        <!-- Page header -->
        <div
            class="flex items-center justify-between border-b border-gray-200 px-4 py-3"
        >
            <div class="flex items-center gap-2">
                <Users class="h-4 w-4 text-gray-400" />
                <h1 class="text-sm font-semibold text-gray-900">Leads</h1>
                <span
                    class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-500"
                >
                    {{ stats.total.toLocaleString() }} total
                </span>
            </div>
            <a
                href="/admin/leads"
                class="text-xs text-gray-500 underline-offset-2 hover:text-gray-700 hover:underline"
            >
                Full admin table →
            </a>
        </div>

        <!-- Stats bar -->
        <StatsBar
            :stats="stats"
            :active-tier="activeTierFilter"
            @filter-by-tier="handleTierFilter"
        />

        <!-- Filter bar -->
        <FilterBar
            :brands="brands"
            :cities="cities"
            :current-filters="filters"
        />

        <!-- Column headers -->
        <div
            class="flex items-center gap-3 border-b border-gray-100 px-4 py-1.5 text-[10px] font-medium tracking-wider text-gray-400 uppercase"
        >
            <div class="w-4" />
            <div class="w-1" />
            <!-- Sortable score header -->
            <button
                class="flex w-10 cursor-pointer items-center gap-0.5 hover:text-gray-600"
                @click="sortBy('score')"
            >
                Score
                <ArrowUpDown
                    class="h-3 w-3"
                    :class="{ 'text-blue-500': sortColumn === 'score' }"
                />
            </button>
            <div class="flex-1">
                <button
                    class="flex cursor-pointer items-center gap-0.5 hover:text-gray-600"
                    @click="sortBy('company_name')"
                >
                    Company
                    <ArrowUpDown
                        class="h-3 w-3"
                        :class="{
                            'text-blue-500': sortColumn === 'company_name',
                        }"
                    />
                </button>
            </div>
            <div class="w-28 truncate">Subcategory</div>
            <button
                class="flex w-20 cursor-pointer items-center justify-end gap-0.5 hover:text-gray-600"
                @click="sortBy('status')"
            >
                Status
                <ArrowUpDown
                    class="h-3 w-3"
                    :class="{ 'text-blue-500': sortColumn === 'status' }"
                />
            </button>
            <div class="w-28 text-center">Engagement</div>
            <div class="w-20 text-right">Brand</div>
        </div>

        <!-- Lead list -->
        <div
            v-if="leads.data.length === 0"
            class="flex flex-col items-center justify-center py-16 text-center"
        >
            <Users class="mb-3 h-10 w-10 text-gray-300" />
            <p class="text-sm font-medium text-gray-600">No leads found</p>
            <p class="mt-1 text-xs text-gray-400">
                Try adjusting your filters or import leads first.
            </p>
        </div>

        <div v-else>
            <LeadRow v-for="lead in leads.data" :key="lead.id" :lead="lead" />
        </div>

        <!-- Pagination -->
        <div
            v-if="leads.last_page > 1"
            class="flex items-center justify-between border-t border-gray-100 px-4 py-3"
        >
            <span class="text-xs text-gray-500">
                Showing {{ leads.from }}–{{ leads.to }} of {{ leads.total }}
            </span>
            <div class="flex items-center gap-1">
                <button
                    v-for="link in leads.links"
                    :key="link.label"
                    class="rounded px-2.5 py-1 text-xs font-medium transition-colors"
                    :class="{
                        'bg-blue-600 text-white': link.active,
                        'text-gray-600 hover:bg-gray-100':
                            !link.active && link.url,
                        'text-gray-300': !link.url,
                    }"
                    :disabled="!link.url"
                    @click="link.url && goToPage(Number(link.label))"
                    v-html="link.label"
                />
            </div>
        </div>
    </div>
</template>
