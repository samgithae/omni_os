<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { MapPin, Trash2 } from '@lucide/vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Mining Targets', href: '/mining-targets' },
        ],
    },
});

interface BrandInfo {
    id: number;
    name: string;
    slug: string;
}
interface TargetData {
    id: number;
    brand_id: number;
    brand: BrandInfo | null;
    country: string;
    city: string | null;
    category: string;
    search_template: string | null;
    segment: string;
    cadence: string;
    is_active: boolean;
    last_mined_at: string | null;
}
interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

const props = defineProps<{
    targets: PaginatedData<TargetData>;
    brands: BrandInfo[];
    filters: Record<string, string | undefined>;
}>();

const showAddForm = ref(false);
const form = ref({
    brand_id: props.brands[0]?.id || '',
    country: 'Kenya',
    city: '',
    category: '',
    search_template: '',
    segment: 'rabbit',
    cadence: 'weekly',
    is_active: true,
});
const errors = ref<Record<string, string>>({});

function submitAdd() {
    router.post('/mining-targets', form.value, {
        preserveScroll: true,
        preserveState: true,
        onError: (e) => {
            errors.value = e as Record<string, string>;
        },
        onSuccess: () => {
            showAddForm.value = false;
        },
    });
}

function confirmDelete(id: number) {
    if (confirm('Delete this target?'))
        router.delete(`/mining-targets/${id}`, {
            preserveScroll: true,
            preserveState: true,
        });
}

function toggleActive(id: number) {
    router.post(
        `/mining-targets/${id}/toggle`,
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function goToPage(page: number) {
    const params = new URLSearchParams(window.location.search);
    params.set('page', String(page));
    router.get(`/mining-targets?${params.toString()}`, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function applyFilter(key: string, value: string) {
    const params = new URLSearchParams(window.location.search);
    if (value) params.set(key, value);
    else params.delete(key);
    params.delete('page');
    router.get(`/mining-targets?${params.toString()}`, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
}

function segmentColor(seg: string): string {
    const c: Record<string, string> = {
        rabbit: 'bg-emerald-100 text-emerald-700',
        deer: 'bg-amber-100 text-amber-700',
        mouse: 'bg-gray-100 text-gray-500',
        elephant: 'bg-red-100 text-red-700',
    };
    return c[seg] || 'bg-gray-100 text-gray-500';
}
function cadenceColor(cad: string): string {
    return cad === 'daily'
        ? 'bg-blue-100 text-blue-700'
        : cad === 'weekly'
          ? 'bg-gray-100 text-gray-700'
          : 'bg-gray-100 text-gray-400';
}
</script>

<template>
    <Head title="Mining Targets" />
    <div>
        <div
            class="flex items-center justify-between border-b border-gray-200 px-4 py-3"
        >
            <div class="flex items-center gap-2">
                <MapPin class="h-4 w-4 text-gray-400" />
                <h1 class="text-sm font-semibold text-gray-900">
                    Mining Targets
                </h1>
                <span
                    class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-500"
                    >{{ targets.total }}</span
                >
            </div>
            <button
                @click="showAddForm = !showAddForm"
                class="inline-flex items-center gap-1 rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
            >
                + Add Target
            </button>
        </div>

        <div
            v-if="showAddForm"
            class="border-b border-gray-100 bg-gray-50/50 px-4 py-3"
        >
            <form
                @submit.prevent="submitAdd"
                class="flex flex-wrap items-end gap-2"
            >
                <div>
                    <label
                        class="mb-0.5 block text-[10px] font-medium text-gray-500"
                        >Brand</label
                    ><select
                        v-model="form.brand_id"
                        class="rounded border-gray-200 text-xs"
                    >
                        <option v-for="b in brands" :key="b.id" :value="b.id">
                            {{ b.name }}
                        </option>
                    </select>
                </div>
                <div>
                    <label
                        class="mb-0.5 block text-[10px] font-medium text-gray-500"
                        >Country</label
                    ><input
                        v-model="form.country"
                        class="w-24 rounded border-gray-200 text-xs"
                    />
                </div>
                <div>
                    <label
                        class="mb-0.5 block text-[10px] font-medium text-gray-500"
                        >City</label
                    ><input
                        v-model="form.city"
                        class="w-24 rounded border-gray-200 text-xs"
                        placeholder="Optional"
                    />
                </div>
                <div>
                    <label
                        class="mb-0.5 block text-[10px] font-medium text-gray-500"
                        >Category</label
                    ><input
                        v-model="form.category"
                        required
                        class="w-32 rounded border-gray-200 text-xs"
                    />
                </div>
                <div>
                    <label
                        class="mb-0.5 block text-[10px] font-medium text-gray-500"
                        >Segment</label
                    ><select
                        v-model="form.segment"
                        class="rounded border-gray-200 text-xs"
                    >
                        <option value="rabbit">Rabbit</option>
                        <option value="deer">Deer</option>
                    </select>
                </div>
                <div>
                    <label
                        class="mb-0.5 block text-[10px] font-medium text-gray-500"
                        >Cadence</label
                    ><select
                        v-model="form.cadence"
                        class="rounded border-gray-200 text-xs"
                    >
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <button
                    type="submit"
                    class="rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
                >
                    Save
                </button>
            </form>
        </div>

        <div class="flex items-center gap-2 border-b border-gray-100 px-4 py-2">
            <select
                class="rounded border-gray-200 text-xs"
                :value="filters.segment || ''"
                @change="
                    applyFilter(
                        'segment',
                        ($event.target as HTMLSelectElement).value,
                    )
                "
            >
                <option value="">All</option>
                <option value="rabbit">Rabbit</option>
                <option value="deer">Deer</option>
            </select>
            <select
                class="rounded border-gray-200 text-xs"
                :value="filters.active || ''"
                @change="
                    applyFilter(
                        'active',
                        ($event.target as HTMLSelectElement).value,
                    )
                "
            >
                <option value="">All Status</option>
                <option value="true">Active</option>
                <option value="false">Inactive</option>
            </select>
        </div>

        <div
            v-if="targets.data.length === 0"
            class="flex flex-col items-center justify-center py-16 text-center"
        >
            <MapPin class="mb-3 h-10 w-10 text-gray-300" />
            <p class="text-sm font-medium text-gray-600">No mining targets</p>
        </div>
        <div v-else class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr
                        class="border-b border-gray-100 text-[10px] font-medium tracking-wider text-gray-400 uppercase"
                    >
                        <th class="px-4 py-2">Brand</th>
                        <th class="px-4 py-2">Country</th>
                        <th class="px-4 py-2">City</th>
                        <th class="px-4 py-2">Category</th>
                        <th class="px-4 py-2">Segment</th>
                        <th class="px-4 py-2">Cadence</th>
                        <th class="px-4 py-2">Active</th>
                        <th class="px-4 py-2">Last Mined</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="t in targets.data"
                        :key="t.id"
                        class="border-b border-gray-50 hover:bg-gray-50/50"
                    >
                        <td class="px-4 py-2.5 text-xs text-gray-500">
                            {{ t.brand?.name || '-' }}
                        </td>
                        <td class="px-4 py-2.5 text-xs text-gray-800">
                            {{ t.country }}
                        </td>
                        <td class="px-4 py-2.5 text-xs text-gray-500">
                            {{ t.city || '-' }}
                        </td>
                        <td
                            class="max-w-[200px] truncate px-4 py-2.5 text-xs text-gray-600"
                        >
                            {{ t.category }}
                        </td>
                        <td class="px-4 py-2.5">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium"
                                :class="segmentColor(t.segment)"
                                >{{ t.segment }}</span
                            >
                        </td>
                        <td class="px-4 py-2.5">
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium"
                                :class="cadenceColor(t.cadence)"
                                >{{ t.cadence }}</span
                            >
                        </td>
                        <td class="px-4 py-2.5">
                            <button
                                @click="toggleActive(t.id)"
                                :title="t.is_active ? 'Deactivate' : 'Activate'"
                                class="rounded p-1"
                            >
                                {{ t.is_active ? '✓' : '○' }}
                            </button>
                        </td>
                        <td class="px-4 py-2.5 text-xs text-gray-400">
                            {{
                                t.last_mined_at
                                    ? new Date(
                                          t.last_mined_at,
                                      ).toLocaleDateString()
                                    : '-'
                            }}
                        </td>
                        <td class="px-4 py-2.5">
                            <button
                                class="rounded p-1 text-gray-400 hover:text-red-600"
                                @click="confirmDelete(t.id)"
                            >
                                <Trash2 class="h-3.5 w-3.5" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div
            v-if="targets.last_page > 1"
            class="flex items-center justify-between border-t border-gray-100 px-4 py-3"
        >
            <span class="text-xs text-gray-500"
                >Page {{ targets.current_page }} of
                {{ targets.last_page }}</span
            >
            <div class="flex items-center gap-1">
                <button
                    v-for="page in targets.last_page"
                    :key="page"
                    class="rounded px-2.5 py-1 text-xs font-medium"
                    :class="{
                        'bg-blue-600 text-white': page === targets.current_page,
                        'text-gray-600 hover:bg-gray-100':
                            page !== targets.current_page,
                    }"
                    @click="goToPage(page)"
                >
                    {{ page }}
                </button>
            </div>
        </div>
    </div>
</template>
