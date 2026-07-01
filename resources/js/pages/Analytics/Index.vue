<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    BarChart3,
    TrendingUp,
    Mail,
    Eye,
    MousePointerClick,
    MessageSquare,
    Users,
    MapPin,
    Award,
    Filter,
} from '@lucide/vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Analytics', href: '/analytics' },
        ],
    },
});

interface Funnel {
    leads: number;
    with_email: number;
    enriched: number;
    emailed: number;
    replied: number;
    interested: number;
    enrichment_rate: number;
    email_coverage: number;
    reply_rate: number;
    interest_rate: number;
    overall_conversion: number;
}

interface Rates {
    sent: number;
    opened: number;
    clicked: number;
    replied: number;
    open_rate: number;
    click_rate: number;
    reply_rate: number;
    click_to_open_rate: number;
}

interface DimensionRow {
    dimension: string;
    leads: number;
    with_email: number;
    enriched: number;
    emailed: number;
    replied: number;
    interested: number;
    enrichment_rate: number;
    reply_rate: number;
    interest_rate: number;
}

interface StepRow {
    step: number;
    total: number;
    sent: number;
    opened: number;
    clicked: number;
    open_rate: number;
    click_rate: number;
}

interface ReplyOutcomes {
    counts: Record<string, number>;
    total: number;
    percentages: Record<string, number>;
}

interface BrandRow {
    name: string;
    slug: string;
    color: string | null;
    leads_count: number;
    suppressions_count: number;
    enriched: number;
    sent: number;
    opened: number;
    interested: number;
}

const props = defineProps<{
    funnel: Funnel;
    rates: Rates;
    byCategory: DimensionRow[];
    byCity: DimensionRow[];
    bySegment: DimensionRow[];
    byStep: StepRow[];
    replyOutcomes: ReplyOutcomes;
    scoreDistribution: Record<string, number>;
    avgScore: number;
    brands: BrandRow[];
    generatedAt: string;
}>();

const tierColors: Record<string, string> = {
    hot: '#ef4444',
    warm: '#f97316',
    moderate: '#f59e0b',
    cold: '#3b82f6',
    frigid: '#9ca3af',
};

const tierLabels: Record<string, string> = {
    hot: 'Hot (80+)',
    warm: 'Warm (60-79)',
    moderate: 'Moderate (40-59)',
    cold: 'Cold (20-39)',
    frigid: 'Frigid (<20)',
};

const outcomeColors: Record<string, string> = {
    interested: '#10b981',
    not_interested: '#ef4444',
    unsubscribe: '#f59e0b',
    out_of_office: '#3b82f6',
    bounce: '#7f1d1d',
};

const outcomeLabels: Record<string, string> = {
    interested: 'Interested',
    not_interested: 'Not Interested',
    unsubscribe: 'Unsubscribe',
    out_of_office: 'Out of Office',
    bounce: 'Bounce',
};

function maxScoreTier(): number {
    return Math.max(...Object.values(props.scoreDistribution), 1);
}

function maxFunnel(): number {
    return Math.max(props.funnel.leads, 1);
}

// Active tab for dimension tables
import { ref } from 'vue';
const activeTab = ref<'category' | 'city' | 'segment'>('category');

const activeDimension = ref<DimensionRow[]>([]);
function updateDimension() {
    if (activeTab.value === 'category')
        activeDimension.value = props.byCategory;
    else if (activeTab.value === 'city') activeDimension.value = props.byCity;
    else activeDimension.value = props.bySegment;
}
updateDimension();
</script>

<template>
    <Head title="Analytics" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Analytics</h1>
                <p class="text-sm text-muted-foreground">
                    Pipeline funnel, engagement rates, win-loss breakdowns
                </p>
            </div>
            <span class="text-xs text-muted-foreground">
                Generated: {{ new Date(generatedAt).toLocaleString() }}
            </span>
        </div>

        <!-- Funnel -->
        <div class="rounded-xl border bg-card p-5 shadow-sm">
            <div class="mb-4 flex items-center gap-2">
                <Filter class="h-4 w-4 text-indigo-500" />
                <h3 class="text-sm font-semibold">Conversion Funnel</h3>
            </div>
            <div class="grid grid-cols-2 gap-3 md:grid-cols-5">
                <div
                    v-for="(stage, idx) in [
                        {
                            label: 'Leads',
                            value: funnel.leads,
                            pct: 100,
                            color: '#3b82f6',
                        },
                        {
                            label: 'With Email',
                            value: funnel.with_email,
                            pct: funnel.enrichment_rate,
                            color: '#10b981',
                        },
                        {
                            label: 'Emailed',
                            value: funnel.emailed,
                            pct: funnel.email_coverage,
                            color: '#6366f1',
                        },
                        {
                            label: 'Replied',
                            value: funnel.replied,
                            pct: funnel.reply_rate,
                            color: '#8b5cf6',
                        },
                        {
                            label: 'Interested',
                            value: funnel.interested,
                            pct: funnel.interest_rate,
                            color: '#059669',
                        },
                    ]"
                    :key="idx"
                    class="text-center"
                >
                    <div
                        class="text-2xl font-bold"
                        :style="{ color: stage.color }"
                    >
                        {{ stage.value }}
                    </div>
                    <div class="mt-1 text-xs text-muted-foreground">
                        {{ stage.label }}
                    </div>
                    <div
                        class="mt-0.5 text-xs font-medium"
                        :style="{ color: stage.color }"
                    >
                        {{ stage.pct }}%
                    </div>
                </div>
            </div>
            <!-- Funnel bars -->
            <div class="mt-4 space-y-1.5">
                <div
                    v-for="(stage, idx) in [
                        {
                            label: 'Leads',
                            value: funnel.leads,
                            color: '#3b82f6',
                        },
                        {
                            label: 'With Email',
                            value: funnel.with_email,
                            color: '#10b981',
                        },
                        {
                            label: 'Emailed',
                            value: funnel.emailed,
                            color: '#6366f1',
                        },
                        {
                            label: 'Replied',
                            value: funnel.replied,
                            color: '#8b5cf6',
                        },
                        {
                            label: 'Interested',
                            value: funnel.interested,
                            color: '#059669',
                        },
                    ]"
                    :key="idx"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="w-24 shrink-0 text-xs text-muted-foreground"
                            >{{ stage.label }}</span
                        >
                        <div
                            class="h-3 flex-1 overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full transition-all"
                                :style="{
                                    width:
                                        (stage.value / maxFunnel()) * 100 + '%',
                                    backgroundColor: stage.color,
                                }"
                            />
                        </div>
                        <span class="w-12 text-right text-xs font-medium">{{
                            stage.value
                        }}</span>
                    </div>
                </div>
            </div>
            <!-- Overall conversion -->
            <div class="mt-4 flex items-center justify-between border-t pt-3">
                <span class="text-sm font-medium">Overall conversion rate</span>
                <span class="text-lg font-bold text-emerald-600"
                    >{{ funnel.overall_conversion }}%</span
                >
            </div>
        </div>

        <!-- Email Engagement Rates -->
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <Mail class="h-4 w-4 text-blue-500" />
                    <h3 class="text-sm font-semibold">Email Engagement</h3>
                </div>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <div class="rounded-lg border p-3 text-center">
                        <Mail class="mx-auto mb-1 h-4 w-4 text-gray-400" />
                        <div class="text-xl font-bold">{{ rates.sent }}</div>
                        <div class="text-xs text-muted-foreground">Sent</div>
                    </div>
                    <div class="rounded-lg border p-3 text-center">
                        <Eye class="mx-auto mb-1 h-4 w-4 text-blue-500" />
                        <div class="text-xl font-bold text-blue-600">
                            {{ rates.opened }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            Opened ({{ rates.open_rate }}%)
                        </div>
                    </div>
                    <div class="rounded-lg border p-3 text-center">
                        <MousePointerClick
                            class="mx-auto mb-1 h-4 w-4 text-purple-500"
                        />
                        <div class="text-xl font-bold text-purple-600">
                            {{ rates.clicked }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            Clicked ({{ rates.click_rate }}%)
                        </div>
                    </div>
                    <div class="rounded-lg border p-3 text-center">
                        <MessageSquare
                            class="mx-auto mb-1 h-4 w-4 text-emerald-500"
                        />
                        <div class="text-xl font-bold text-emerald-600">
                            {{ rates.replied }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            Replied ({{ rates.reply_rate }}%)
                        </div>
                    </div>
                </div>
                <div class="mt-4 space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Open rate</span>
                        <div class="flex items-center gap-2">
                            <div
                                class="h-2 w-32 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-blue-500"
                                    :style="{ width: rates.open_rate + '%' }"
                                />
                            </div>
                            <span class="w-12 text-right font-medium"
                                >{{ rates.open_rate }}%</span
                            >
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Click rate</span>
                        <div class="flex items-center gap-2">
                            <div
                                class="h-2 w-32 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-purple-500"
                                    :style="{ width: rates.click_rate + '%' }"
                                />
                            </div>
                            <span class="w-12 text-right font-medium"
                                >{{ rates.click_rate }}%</span
                            >
                        </div>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Reply rate</span>
                        <div class="flex items-center gap-2">
                            <div
                                class="h-2 w-32 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-emerald-500"
                                    :style="{ width: rates.reply_rate + '%' }"
                                />
                            </div>
                            <span class="w-12 text-right font-medium"
                                >{{ rates.reply_rate }}%</span
                            >
                        </div>
                    </div>
                    <div
                        class="flex items-center justify-between border-t pt-2 text-sm"
                    >
                        <span class="text-muted-foreground"
                            >Click-to-open rate</span
                        >
                        <span class="font-medium text-purple-600"
                            >{{ rates.click_to_open_rate }}%</span
                        >
                    </div>
                </div>
            </div>

            <!-- Reply Outcomes -->
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <MessageSquare class="h-4 w-4 text-emerald-500" />
                    <h3 class="text-sm font-semibold">Reply Outcomes</h3>
                    <span class="ml-auto text-xs text-muted-foreground"
                        >{{ replyOutcomes.total }} total</span
                    >
                </div>
                <div v-if="replyOutcomes.total > 0" class="space-y-3">
                    <div
                        v-for="(count, type) in replyOutcomes.counts"
                        :key="type"
                    >
                        <div
                            class="mb-1 flex items-center justify-between text-sm"
                        >
                            <span class="flex items-center gap-2 font-medium">
                                <span
                                    class="h-2 w-2 rounded-full"
                                    :style="{
                                        backgroundColor:
                                            outcomeColors[type] || '#999',
                                    }"
                                />
                                {{ outcomeLabels[type] || type }}
                            </span>
                            <span class="text-muted-foreground"
                                >{{ count }} ({{
                                    replyOutcomes.percentages[type]
                                }}%)</span
                            >
                        </div>
                        <div
                            class="h-2 w-full overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full transition-all"
                                :style="{
                                    width:
                                        (replyOutcomes.percentages[type] || 0) +
                                        '%',
                                    backgroundColor:
                                        outcomeColors[type] || '#999',
                                }"
                            />
                        </div>
                    </div>
                </div>
                <div
                    v-else
                    class="flex flex-col items-center justify-center py-8 text-center"
                >
                    <MessageSquare class="mb-2 h-8 w-8 text-gray-300" />
                    <p class="text-sm text-muted-foreground">
                        No replies classified yet
                    </p>
                    <p class="mt-1 text-xs text-gray-400">
                        Replies will appear here once Hermes starts classifying
                        them
                    </p>
                </div>
            </div>
        </div>

        <!-- Dimension Breakdown Table -->
        <div class="rounded-xl border bg-card p-5 shadow-sm">
            <div class="mb-4 flex items-center gap-2">
                <TrendingUp class="h-4 w-4 text-indigo-500" />
                <h3 class="text-sm font-semibold">Performance by Dimension</h3>
            </div>
            <!-- Tabs -->
            <div class="mb-3 flex gap-1">
                <button
                    v-for="tab in ['category', 'city', 'segment'] as const"
                    :key="tab"
                    class="rounded-md px-3 py-1.5 text-xs font-medium capitalize transition-colors"
                    :class="
                        activeTab === tab
                            ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400'
                            : 'text-gray-500 hover:bg-gray-100'
                    "
                    @click="
                        activeTab = tab;
                        updateDimension();
                    "
                >
                    {{
                        tab === 'category'
                            ? 'Category'
                            : tab === 'city'
                              ? 'City'
                              : 'Segment'
                    }}
                </button>
            </div>
            <!-- Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr
                            class="border-b text-left text-[10px] tracking-wider text-gray-400 uppercase"
                        >
                            <th class="py-2 pr-4 font-medium">
                                {{
                                    activeTab === 'category'
                                        ? 'Category'
                                        : activeTab === 'city'
                                          ? 'City'
                                          : 'Segment'
                                }}
                            </th>
                            <th class="px-2 py-2 text-right font-medium">
                                Leads
                            </th>
                            <th class="px-2 py-2 text-right font-medium">
                                Email
                            </th>
                            <th class="px-2 py-2 text-right font-medium">
                                Emailed
                            </th>
                            <th class="px-2 py-2 text-right font-medium">
                                Replied
                            </th>
                            <th class="px-2 py-2 text-right font-medium">
                                Interested
                            </th>
                            <th class="px-2 py-2 text-right font-medium">
                                Enrich%
                            </th>
                            <th class="px-2 py-2 text-right font-medium">
                                Reply%
                            </th>
                            <th class="px-2 py-2 text-right font-medium">
                                Interest%
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in activeDimension"
                            :key="row.dimension"
                            class="border-b border-gray-50 hover:bg-muted/30"
                        >
                            <td
                                class="max-w-[200px] truncate py-2 pr-4 font-medium"
                            >
                                {{ row.dimension }}
                            </td>
                            <td class="px-2 py-2 text-right">
                                {{ row.leads }}
                            </td>
                            <td
                                class="px-2 py-2 text-right text-muted-foreground"
                            >
                                {{ row.with_email }}
                            </td>
                            <td class="px-2 py-2 text-right">
                                {{ row.emailed }}
                            </td>
                            <td
                                class="px-2 py-2 text-right"
                                :class="
                                    row.replied > 0
                                        ? 'font-medium text-purple-600'
                                        : ''
                                "
                            >
                                {{ row.replied }}
                            </td>
                            <td
                                class="px-2 py-2 text-right"
                                :class="
                                    row.interested > 0
                                        ? 'font-medium text-emerald-600'
                                        : ''
                                "
                            >
                                {{ row.interested }}
                            </td>
                            <td
                                class="px-2 py-2 text-right text-muted-foreground"
                            >
                                {{ row.enrichment_rate }}%
                            </td>
                            <td
                                class="px-2 py-2 text-right text-muted-foreground"
                            >
                                {{ row.reply_rate }}%
                            </td>
                            <td
                                class="px-2 py-2 text-right text-muted-foreground"
                            >
                                {{ row.interest_rate }}%
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Email by Sequence Step + Score Distribution -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- By Step -->
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <Mail class="h-4 w-4 text-blue-500" />
                    <h3 class="text-sm font-semibold">
                        Email Performance by Sequence Step
                    </h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr
                                class="border-b text-left text-[10px] tracking-wider text-gray-400 uppercase"
                            >
                                <th class="py-2 pr-4 font-medium">Step</th>
                                <th class="px-2 py-2 text-right font-medium">
                                    Total
                                </th>
                                <th class="px-2 py-2 text-right font-medium">
                                    Sent
                                </th>
                                <th class="px-2 py-2 text-right font-medium">
                                    Opened
                                </th>
                                <th class="px-2 py-2 text-right font-medium">
                                    Clicked
                                </th>
                                <th class="px-2 py-2 text-right font-medium">
                                    Open%
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="step in byStep"
                                :key="step.step"
                                class="border-b border-gray-50 hover:bg-muted/30"
                            >
                                <td class="py-2 pr-4 font-medium">
                                    Step {{ step.step }}
                                </td>
                                <td class="px-2 py-2 text-right">
                                    {{ step.total }}
                                </td>
                                <td class="px-2 py-2 text-right">
                                    {{ step.sent }}
                                </td>
                                <td class="px-2 py-2 text-right text-blue-600">
                                    {{ step.opened }}
                                </td>
                                <td
                                    class="px-2 py-2 text-right text-purple-600"
                                >
                                    {{ step.clicked }}
                                </td>
                                <td
                                    class="px-2 py-2 text-right text-muted-foreground"
                                >
                                    {{ step.open_rate }}%
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Score Distribution -->
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <Award class="h-4 w-4 text-amber-500" />
                    <h3 class="text-sm font-semibold">Score Distribution</h3>
                    <span class="ml-auto text-xs text-muted-foreground"
                        >Avg:
                        <span class="font-bold text-indigo-600">{{
                            avgScore
                        }}</span></span
                    >
                </div>
                <div class="space-y-3">
                    <div v-for="(count, tier) in scoreDistribution" :key="tier">
                        <div
                            class="mb-1 flex items-center justify-between text-sm"
                        >
                            <span class="font-medium">{{
                                tierLabels[tier] || tier
                            }}</span>
                            <span class="text-muted-foreground">{{
                                count
                            }}</span>
                        </div>
                        <div
                            class="h-2 w-full overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full transition-all"
                                :style="{
                                    width: (count / maxScoreTier()) * 100 + '%',
                                    backgroundColor: tierColors[tier] || '#999',
                                }"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Per-Brand Summary -->
        <div class="rounded-xl border bg-card p-5 shadow-sm">
            <div class="mb-4 flex items-center gap-2">
                <Users class="h-4 w-4 text-indigo-500" />
                <h3 class="text-sm font-semibold">Per-Brand Summary</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr
                            class="border-b text-left text-[10px] tracking-wider text-gray-400 uppercase"
                        >
                            <th class="py-2 pr-4 font-medium">Brand</th>
                            <th class="px-2 py-2 text-right font-medium">
                                Leads
                            </th>
                            <th class="px-2 py-2 text-right font-medium">
                                Enriched
                            </th>
                            <th class="px-2 py-2 text-right font-medium">
                                Sent
                            </th>
                            <th class="px-2 py-2 text-right font-medium">
                                Opened
                            </th>
                            <th class="px-2 py-2 text-right font-medium">
                                Interested
                            </th>
                            <th class="px-2 py-2 text-right font-medium">
                                Suppressed
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="brand in brands"
                            :key="brand.slug"
                            class="border-b border-gray-50 hover:bg-muted/30"
                        >
                            <td class="py-2 pr-4 font-medium">
                                <span class="inline-flex items-center gap-2">
                                    <span
                                        class="h-2.5 w-2.5 rounded-full"
                                        :style="{
                                            backgroundColor:
                                                brand.color || '#ccc',
                                        }"
                                    />
                                    {{ brand.name }}
                                </span>
                            </td>
                            <td class="px-2 py-2 text-right">
                                {{ brand.leads_count }}
                            </td>
                            <td class="px-2 py-2 text-right text-emerald-600">
                                {{ brand.enriched }}
                            </td>
                            <td class="px-2 py-2 text-right">
                                {{ brand.sent }}
                            </td>
                            <td class="px-2 py-2 text-right text-blue-600">
                                {{ brand.opened }}
                            </td>
                            <td
                                class="px-2 py-2 text-right font-medium text-green-600"
                            >
                                {{ brand.interested }}
                            </td>
                            <td class="px-2 py-2 text-right text-red-600">
                                {{ brand.suppressions_count }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
