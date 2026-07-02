<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { FileText, Plus, Pencil, Trash2 } from '@lucide/vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Sequence Configs', href: '/sequence-configs' },
        ],
    },
});

interface BrandInfo {
    id: number;
    name: string;
    slug: string;
}

interface ConfigData {
    id: number;
    brand_id: number;
    brand: BrandInfo | null;
    segment: string;
    subcategory: string;
    sequence_steps: number;
    prompt_text: string;
    is_active: boolean;
    created_at: string | null;
    updated_at: string | null;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    configs: PaginatedData<ConfigData>;
    brands: BrandInfo[];
    filters: Record<string, string | undefined>;
}>();

function confirmDelete(id: number) {
    if (confirm('Delete this sequence config?')) {
        router.delete(`/sequence-configs/${id}`, {
            preserveScroll: true,
            preserveState: true,
        });
    }
}

function goToPage(page: number) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', String(page));
    router.get(`/sequence-configs?${params.toString()}`, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function applyFilter(key: string, value: string) {
    const params = new URLSearchParams(window.location.search);
    if (value) {
        params.set(key, value);
    } else {
        params.delete(key);
    }
    params.delete('page');
    router.get(`/sequence-configs?${params.toString()}`, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function segmentColor(seg: string): string {
    const colors: Record<string, string> = {
        all: 'bg-gray-100 text-gray-700',
        rabbit: 'bg-emerald-100 text-emerald-700',
        deer: 'bg-amber-100 text-amber-700',
        mouse: 'bg-gray-100 text-gray-500',
        elephant: 'bg-red-100 text-red-700',
    };
    return colors[seg] || 'bg-gray-100 text-gray-500';
}
</script>

<template>
    <Head title="Sequence Configs" />

    <div>
        <div
            class="flex items-center justify-between border-b border-gray-200 px-4 py-3"
        >
            <div class="flex items-center gap-2">
                <FileText class="h-4 w-4 text-gray-400" />
                <h1 class="text-sm font-semibold text-gray-900">
                    Sequence Configs
                </h1>
                <span
                    class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-500"
                >
                    {{ configs.total }} configs
                </span>
            </div>
            <Link
                href="/sequence-configs/create"
                class="inline-flex items-center gap-1 rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
            >
                <Plus class="h-3.5 w-3.5" />
                New Config
            </Link>
        </div>

        <!-- Filters -->
        <div class="flex items-center gap-2 border-b border-gray-100 px-4 py-2">
            <select
                class="rounded border-gray-200 text-xs text-gray-600"
                :value="filters.brand_id || ''"
                @change="
                    applyFilter(
                        'brand_id',
                        ($event.target as HTMLSelectElement).value,
                    )
                "
            >
                <option value="">All Brands</option>
                <option v-for="b in brands" :key="b.id" :value="String(b.id)">
                    {{ b.name }}
                </option>
            </select>
            <select
                class="rounded border-gray-200 text-xs text-gray-600"
                :value="filters.segment || ''"
                @change="
                    applyFilter(
                        'segment',
                        ($event.target as HTMLSelectElement).value,
                    )
                "
            >
                <option value="">All Segments</option>
                <option value="all">All (fallback)</option>
                <option value="rabbit">Rabbit</option>
                <option value="deer">Deer</option>
            </select>
        </div>

        <!-- Table -->
        <div
            v-if="configs.data.length === 0"
            class="flex flex-col items-center justify-center py-16 text-center"
        >
            <FileText class="mb-3 h-10 w-10 text-gray-300" />
            <p class="text-sm font-medium text-gray-600">No sequence configs</p>
            <p class="mt-1 text-xs text-gray-400">
                Create one to start generating email sequences.
            </p>
        </div>

        <div v-else class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr
                        class="border-b border-gray-100 text-[10px] font-medium tracking-wider text-gray-400 uppercase"
                    >
                        <th class="px-4 py-2">Brand</th>
                        <th class="px-4 py-2">Segment</th>
                        <th class="px-4 py-2">Subcategory</th>
                        <th class="px-4 py-2">Steps</th>
                        <th class="px-4 py-2">Active</th>
                        <th class="px-4 py-2">Updated</th>
                        <th class="px-4 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="cfg in configs.data"
                        :key="cfg.id"
                        class="border-b border-gray-50 hover:bg-gray-50/50"
                    >
                        <td class="px-4 py-2.5 font-medium text-gray-900">
                            {{ cfg.brand?.name || '-' }}
                        </td>
                        <td class="px-4 py-2.5">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium"
                                :class="segmentColor(cfg.segment)"
                            >
                                {{ cfg.segment }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-gray-600">
                            {{ cfg.subcategory }}
                        </td>
                        <td class="px-4 py-2.5 text-gray-600">
                            {{ cfg.sequence_steps }}
                        </td>
                        <td class="px-4 py-2.5">
                            <span
                                class="text-xs"
                                :class="
                                    cfg.is_active
                                        ? 'text-green-600'
                                        : 'text-gray-400'
                                "
                            >
                                {{ cfg.is_active ? 'Yes' : 'No' }}
                            </span>
                        </td>
                        <td class="px-4 py-2.5 text-xs text-gray-400">
                            {{
                                cfg.updated_at
                                    ? new Date(
                                          cfg.updated_at,
                                      ).toLocaleDateString()
                                    : '-'
                            }}
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="flex items-center gap-1">
                                <Link
                                    :href="`/sequence-configs/${cfg.id}/edit`"
                                    class="rounded p-1 text-gray-400 hover:bg-blue-50 hover:text-blue-600"
                                    title="Edit"
                                >
                                    <Pencil class="h-3.5 w-3.5" />
                                </Link>
                                <button
                                    class="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600"
                                    title="Delete"
                                    @click="confirmDelete(cfg.id)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div
            v-if="configs.last_page > 1"
            class="flex items-center justify-between border-t border-gray-100 px-4 py-3"
        >
            <span class="text-xs text-gray-500"
                >Page {{ configs.current_page }} of
                {{ configs.last_page }}</span
            >
            <div class="flex items-center gap-1">
                <button
                    v-for="page in configs.last_page"
                    :key="page"
                    class="rounded px-2.5 py-1 text-xs font-medium"
                    :class="{
                        'bg-blue-600 text-white': page === configs.current_page,
                        'text-gray-600 hover:bg-gray-100':
                            page !== configs.current_page,
                    }"
                    @click="goToPage(page)"
                >
                    {{ page }}
                </button>
            </div>
        </div>
    </div>
</template>
