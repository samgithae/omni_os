<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { FileText, ArrowLeft, Save } from '@lucide/vue';
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
    sequence_steps: number;
    prompt_text: string;
    is_active: boolean;
}

const props = defineProps<{
    config: ConfigData | null;
    brands: BrandInfo[];
}>();

const isEditing = props.config !== null;

const form = ref({
    brand_id: props.config?.brand_id || props.brands[0]?.id || '',
    segment: props.config?.segment || 'all',
    sequence_steps: props.config?.sequence_steps || 4,
    prompt_text: props.config?.prompt_text || '',
    is_active: props.config?.is_active ?? true,
});

const errors = ref<Record<string, string>>({});
const saving = ref(false);

function submit() {
    saving.value = true;
    errors.value = {};

    const url = isEditing
        ? `/sequence-configs/${props.config!.id}`
        : '/sequence-configs';
    const method = isEditing ? 'put' : 'post';

    router[method](url, form.value, {
        preserveScroll: true,
        preserveState: true,
        onError: (errs) => {
            errors.value = errs as Record<string, string>;
            saving.value = false;
        },
        onSuccess: () => {
            saving.value = false;
        },
    });
}
</script>

<template>
    <Head :title="isEditing ? 'Edit Sequence Config' : 'New Sequence Config'" />

    <div>
        <div class="flex items-center gap-2 border-b border-gray-200 px-4 py-3">
            <Link
                href="/sequence-configs"
                class="rounded p-1 text-gray-400 hover:text-gray-600"
            >
                <ArrowLeft class="h-4 w-4" />
            </Link>
            <FileText class="h-4 w-4 text-gray-400" />
            <h1 class="text-sm font-semibold text-gray-900">
                {{
                    isEditing
                        ? `Edit: ${config?.brand?.name || ''} / ${config?.segment || ''}`
                        : 'New Sequence Config'
                }}
            </h1>
        </div>

        <form @submit.prevent="submit" class="mx-auto max-w-3xl space-y-6 p-6">
            <!-- Brand & Segment -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700"
                        >Brand</label
                    >
                    <select
                        v-model="form.brand_id"
                        class="w-full rounded border-gray-200 text-sm"
                        :class="{ 'border-red-400': errors.brand_id }"
                    >
                        <option v-for="b in brands" :key="b.id" :value="b.id">
                            {{ b.name }}
                        </option>
                    </select>
                    <p v-if="errors.brand_id" class="mt-1 text-xs text-red-500">
                        {{ errors.brand_id }}
                    </p>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700"
                        >Segment</label
                    >
                    <select
                        v-model="form.segment"
                        class="w-full rounded border-gray-200 text-sm"
                    >
                        <option value="all">All Segments (fallback)</option>
                        <option value="rabbit">Rabbit</option>
                        <option value="deer">Deer</option>
                        <option value="mouse">Mouse</option>
                        <option value="elephant">Elephant</option>
                    </select>
                </div>
            </div>

            <!-- Steps + Active -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-gray-700"
                        >Number of Emails</label
                    >
                    <input
                        type="number"
                        v-model.number="form.sequence_steps"
                        min="1"
                        max="10"
                        class="w-full rounded border-gray-200 text-sm"
                    />
                </div>
                <div class="flex items-end pb-2">
                    <label class="flex items-center gap-2">
                        <input
                            type="checkbox"
                            v-model="form.is_active"
                            class="rounded border-gray-300 text-blue-600"
                        />
                        <span class="text-xs font-medium text-gray-700"
                            >Active</span
                        >
                    </label>
                </div>
            </div>

            <!-- Prompt Text -->
            <div>
                <label class="mb-1 block text-xs font-medium text-gray-700">
                    Generation Prompt / Playbook
                    <span class="font-normal text-gray-400">
                        — Full instructions Hermes uses to generate emails</span
                    >
                </label>
                <textarea
                    v-model="form.prompt_text"
                    rows="30"
                    class="w-full rounded border-gray-200 font-mono text-xs leading-relaxed"
                    :class="{ 'border-red-400': errors.prompt_text }"
                ></textarea>
                <p v-if="errors.prompt_text" class="mt-1 text-xs text-red-500">
                    {{ errors.prompt_text }}
                </p>
            </div>

            <!-- Submit -->
            <div
                class="flex items-center justify-end gap-2 border-t border-gray-100 pt-4"
            >
                <Link
                    href="/sequence-configs"
                    class="rounded px-3 py-1.5 text-xs font-medium text-gray-600 ring-1 ring-gray-300 ring-inset hover:bg-gray-50"
                >
                    Cancel
                </Link>
                <button
                    type="submit"
                    class="inline-flex items-center gap-1 rounded bg-blue-600 px-4 py-1.5 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-40"
                    :disabled="saving"
                >
                    <Save class="h-3.5 w-3.5" />
                    {{
                        saving
                            ? 'Saving...'
                            : isEditing
                              ? 'Update Config'
                              : 'Create Config'
                    }}
                </button>
            </div>
        </form>
    </div>
</template>
