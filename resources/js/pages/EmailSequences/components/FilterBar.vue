<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Search, X } from '@lucide/vue';
import { ref, watch } from 'vue';

interface BrandInfo {
    id: number;
    name: string;
    slug: string;
    color: string | null;
}

const props = defineProps<{
    brands: BrandInfo[];
    currentFilters: Record<string, string | undefined>;
}>();

const emit = defineEmits<{
    filterChange: [filters: Record<string, string>];
}>();

const searchInput = ref(props.currentFilters.search || '');

let debounceTimer: ReturnType<typeof setTimeout> | null = null;

watch(searchInput, (val) => {
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        applyFilter('search', val);
    }, 300);
});

function applyFilter(key: string, value: string | null) {
    const params = new URLSearchParams(window.location.search);
    if (value) {
        params.set(key, value);
    } else {
        params.delete(key);
    }
    // Preserve pagination
    params.delete('page');
    const qs = params.toString();
    const url = qs ? `/email-sequences?${qs}` : '/email-sequences';
    router.get(
        url,
        {},
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

function selectSegment(segment: string | null) {
    applyFilter('segment', segment);
}

function selectApproval(status: string | null) {
    applyFilter('approval', status);
}

function selectProgress(progress: string | null) {
    applyFilter('progress', progress);
}

function clearFilters() {
    router.get(
        '/email-sequences',
        {},
        { preserveScroll: true, preserveState: true, replace: true },
    );
}

const hasActiveFilters = Object.values(props.currentFilters).some(Boolean);

function brandStyle(slug: string): string {
    const brand = props.brands.find((b) => b.slug === slug);
    if (!brand?.color) return '';
    return brand.color;
}
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 py-2"
    >
        <!-- Segment dropdown -->
        <select
            class="rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-sm outline-none focus:border-blue-300 focus:ring-1 focus:ring-blue-200"
            :value="currentFilters.segment || ''"
            @change="
                selectSegment(
                    ($event.target as HTMLSelectElement).value || null,
                )
            "
        >
            <option value="">All Segments</option>
            <option value="rabbit">Rabbit</option>
            <option value="deer">Deer</option>
        </select>

        <!-- Approval dropdown -->
        <select
            class="rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-sm outline-none focus:border-blue-300 focus:ring-1 focus:ring-blue-200"
            :value="currentFilters.approval || ''"
            @change="
                selectApproval(
                    ($event.target as HTMLSelectElement).value || null,
                )
            "
        >
            <option value="">All Approval</option>
            <option value="pending">Pending</option>
            <option value="approved">Approved</option>
            <option value="rejected">Rejected</option>
        </select>

        <!-- Progress dropdown -->
        <select
            class="rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-sm outline-none focus:border-blue-300 focus:ring-1 focus:ring-blue-200"
            :value="currentFilters.progress || ''"
            @change="
                selectProgress(
                    ($event.target as HTMLSelectElement).value || null,
                )
            "
        >
            <option value="">All Progress</option>
            <option value="not_started">Not started</option>
            <option value="in_progress">In progress</option>
            <option value="completed">Completed</option>
        </select>

        <!-- Search input -->
        <div class="relative ml-auto min-w-[180px]">
            <Search
                class="pointer-events-none absolute top-1/2 left-2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400"
            />
            <input
                v-model="searchInput"
                type="text"
                placeholder="Search leads, subjects..."
                class="w-full rounded-md border border-gray-200 py-1.5 pr-2 pl-7 text-xs outline-none placeholder:text-gray-400 focus:border-blue-300 focus:ring-1 focus:ring-blue-200"
            />
        </div>

        <!-- Clear filters -->
        <button
            v-if="hasActiveFilters"
            class="flex items-center gap-1 rounded-md px-2 py-1.5 text-xs font-medium text-gray-500 hover:bg-gray-100"
            @click="clearFilters"
        >
            <X class="h-3 w-3" />
            Clear
        </button>
    </div>
</template>
