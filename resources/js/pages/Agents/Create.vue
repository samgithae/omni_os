<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import { Bot, ArrowLeft, Save, Loader } from '@lucide/vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Agents', href: '/agents' },
            { title: 'Create Agent', href: '/agents/create' },
        ],
    },
});

const form = ref({
    codename: '',
    display_name: '',
    role: '',
    function_area: '',
    description: '',
    status: 'active',
    is_active: true,
    sort_order: 0,
});

const saving = ref(false);
const errors = ref<Record<string, string>>({});
const flashMessage = ref<string | null>(null);

function submit() {
    saving.value = true;
    errors.value = {};
    flashMessage.value = null;

    router.post('/agents', form.value, {
        preserveScroll: true,
        onSuccess: () => {
            // Redirect handled by server
        },
        onError: (errs) => {
            errors.value = errs as Record<string, string>;
            saving.value = false;
        },
        onFinish: () => {
            saving.value = false;
        },
    });
}
</script>

<template>
    <Head title="Create Agent" />

    <div class="mx-auto max-w-2xl pb-16">
        <!-- Header -->
        <div class="flex items-center gap-3 border-b border-gray-200 px-4 py-3">
            <Link href="/agents" class="text-gray-400 hover:text-gray-600">
                <ArrowLeft class="h-4 w-4" />
            </Link>
            <Bot class="h-4 w-4 text-gray-400" />
            <h1 class="text-sm font-semibold text-gray-900">Create Agent</h1>
        </div>

        <!-- Flash message -->
        <div
            v-if="flashMessage"
            class="mx-4 mt-3 rounded-md bg-green-50 px-3 py-2 text-xs text-green-700"
        >
            {{ flashMessage }}
        </div>

        <!-- Form -->
        <form @submit.prevent="submit" class="space-y-4 px-4 pt-4">
            <!-- Codename -->
            <div>
                <label class="block text-xs font-medium text-gray-700"
                    >Codename</label
                >
                <input
                    v-model="form.codename"
                    type="text"
                    placeholder="e.g. the_professor"
                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-300"
                />
                <p v-if="errors.codename" class="mt-1 text-[10px] text-red-500">
                    {{ errors.codename }}
                </p>
                <p v-else class="mt-1 text-[10px] text-gray-400">
                    Machine handle — lowercase, underscores.
                </p>
            </div>

            <!-- Display Name -->
            <div>
                <label class="block text-xs font-medium text-gray-700"
                    >Display Name</label
                >
                <input
                    v-model="form.display_name"
                    type="text"
                    placeholder="e.g. The Professor"
                    class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-300"
                />
                <p
                    v-if="errors.display_name"
                    class="mt-1 text-[10px] text-red-500"
                >
                    {{ errors.display_name }}
                </p>
            </div>

            <!-- Role + Function Area -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700"
                        >Role</label
                    >
                    <input
                        v-model="form.role"
                        type="text"
                        placeholder="e.g. Orchestration / control tower"
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-300"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700"
                        >Function Area</label
                    >
                    <select
                        v-model="form.function_area"
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-300"
                    >
                        <option value="">Select...</option>
                        <option value="orchestration">Orchestration</option>
                        <option value="mining">Mining</option>
                        <option value="enrichment">Enrichment</option>
                        <option value="drafting">Drafting</option>
                        <option value="triage">Triage</option>
                        <option value="research">Research</option>
                    </select>
                </div>
            </div>

            <!-- Description -->
            <div>
                <label class="block text-xs font-medium text-gray-700"
                    >Description</label
                >
                <textarea
                    v-model="form.description"
                    rows="3"
                    placeholder="Describe this agent's role and responsibilities..."
                    class="mt-1 block w-full resize-none rounded-lg border border-gray-300 px-3 py-2 text-xs outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-300"
                ></textarea>
            </div>

            <!-- Status + Active + Sort -->
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-700"
                        >Status</label
                    >
                    <select
                        v-model="form.status"
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-300"
                    >
                        <option value="active">Active</option>
                        <option value="paused">Paused</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700"
                        >Sort Order</label
                    >
                    <input
                        v-model.number="form.sort_order"
                        type="number"
                        min="0"
                        class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs outline-none focus:border-blue-400 focus:ring-1 focus:ring-blue-300"
                    />
                </div>
                <div class="flex items-end pb-2">
                    <label
                        class="flex cursor-pointer items-center gap-2 text-xs text-gray-700"
                    >
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300"
                        />
                        Enabled
                    </label>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex items-center gap-3 border-t border-gray-100 pt-4">
                <button
                    type="submit"
                    :disabled="saving"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-50"
                >
                    <Save v-if="!saving" class="h-3.5 w-3.5" />
                    <Loader v-else class="h-3.5 w-3.5 animate-spin" />
                    {{ saving ? 'Creating...' : 'Create Agent' }}
                </button>
                <Link
                    href="/agents"
                    class="text-xs font-medium text-gray-500 hover:text-gray-700"
                    >Cancel</Link
                >
            </div>
        </form>
    </div>
</template>
