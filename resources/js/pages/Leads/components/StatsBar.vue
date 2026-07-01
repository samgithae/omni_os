<script setup lang="ts">
import { router } from '@inertiajs/vue3';

export interface LeadStats {
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

const props = defineProps<{
    stats: LeadStats;
    activeTier?: string;
}>();

const emit = defineEmits<{
    filterByTier: [tier: string];
}>();

interface StatItem {
    label: string;
    key: string;
    value: number | string;
    color: string;
    clickable: boolean;
}

const tierMeta: Record<string, { color: string; bg: string }> = {
    hot: { color: 'text-red-600', bg: 'bg-red-50' },
    warm: { color: 'text-orange-600', bg: 'bg-orange-50' },
    moderate: { color: 'text-amber-600', bg: 'bg-amber-50' },
    cold: { color: 'text-blue-600', bg: 'bg-blue-50' },
    frigid: { color: 'text-gray-500', bg: 'bg-gray-50' },
};

function clickStat(key: string) {
    emit('filterByTier', key);
}
</script>

<template>
    <div
        class="flex items-center gap-0 border-b border-gray-200 px-4 py-2.5 text-xs"
    >
        <!-- Total -->
        <div class="flex items-center gap-1 px-3 py-1">
            <span class="text-lg leading-none font-semibold text-gray-900">{{
                stats.total.toLocaleString()
            }}</span>
            <span class="text-gray-500">Total Leads</span>
        </div>

        <div class="h-6 w-px bg-gray-200" />

        <!-- Avg Score -->
        <div class="flex items-center gap-1 px-3 py-1">
            <span class="text-lg leading-none font-semibold text-indigo-600">{{
                stats.avg_score
            }}</span>
            <span class="text-gray-500">Avg Score</span>
        </div>

        <div class="h-6 w-px bg-gray-200" />

        <!-- Tier pills -->
        <button
            v-for="tier in [
                'hot',
                'warm',
                'moderate',
                'cold',
                'frigid',
            ] as const"
            :key="tier"
            class="flex cursor-pointer items-center gap-1 rounded px-3 py-1 transition-colors hover:bg-gray-50"
            :class="{ [tierMeta[tier].bg]: activeTier === tier }"
            @click="clickStat(tier)"
        >
            <span
                class="text-lg leading-none font-semibold"
                :class="tierMeta[tier].color"
            >
                {{ stats.tier_counts[tier] }}
            </span>
            <span class="text-gray-500 capitalize">{{ tier }}</span>
        </button>

        <div class="h-6 w-px bg-gray-200" />

        <!-- With Email -->
        <div class="flex items-center gap-1 px-3 py-1">
            <span class="text-lg leading-none font-semibold text-emerald-600">{{
                stats.with_email
            }}</span>
            <span class="text-gray-500">With Email</span>
        </div>

        <!-- Enriched -->
        <div class="flex items-center gap-1 px-3 py-1">
            <span class="text-lg leading-none font-semibold text-blue-600">{{
                stats.enriched
            }}</span>
            <span class="text-gray-500">Enriched</span>
        </div>
    </div>
</template>
