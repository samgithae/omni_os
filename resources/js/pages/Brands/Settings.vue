<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import {
    Building2,
    Settings,
    Mail,
    Send,
    Plus,
    Trash2,
    Save,
    Check,
    X,
    Globe,
    MessageSquare,
    Smartphone,
} from '@lucide/vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Brand Settings', href: '/brands' },
        ],
    },
});

interface BrandData {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    primary_market: string | null;
    primary_kpi: string | null;
    brand_voice: string | null;
    color: string | null;
    is_active: boolean;
    sender_name: string | null;
    sender_emails: string[];
    settings: Record<string, any>;
}

const props = defineProps<{
    brand: BrandData;
    brands: BrandData[];
}>();

const activeTab = ref<'email' | 'social' | 'general'>('email');
const successMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);
const saving = ref(false);

// Sender emails
const senderEmails = ref<string[]>([...props.brand.sender_emails]);
const newEmail = ref('');
const senderName = ref(props.brand.sender_name || '');

// Settings (social credentials etc)
const settings = ref<Record<string, any>>({ ...props.brand.settings });

function addEmail() {
    const email = newEmail.value.trim().toLowerCase();
    if (!email) return;
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        errorMessage.value = 'Invalid email format';
        setTimeout(() => {
            errorMessage.value = null;
        }, 3000);
        return;
    }
    if (senderEmails.value.includes(email)) {
        errorMessage.value = 'Email already in list';
        setTimeout(() => {
            errorMessage.value = null;
        }, 3000);
        return;
    }
    senderEmails.value.push(email);
    newEmail.value = '';
}

function removeEmail(idx: number) {
    senderEmails.value.splice(idx, 1);
}

function saveSettings() {
    saving.value = true;
    successMessage.value = null;
    errorMessage.value = null;

    router.put(
        `/brands/${props.brand.slug}/settings`,
        {
            sender_name: senderName.value,
            sender_emails: senderEmails.value,
            settings: settings.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                successMessage.value = 'Settings saved successfully';
                setTimeout(() => {
                    successMessage.value = null;
                }, 3000);
            },
            onError: (errors) => {
                errorMessage.value = Object.values(errors).flat().join(', ');
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

const brandColors: Record<string, string> = {
    hudutech: '#1a56db',
    ujuziplus: '#059669',
    phantomflix: '#7c3aed',
    'phantom-tutors': '#dc2626',
};

const tabIcon: Record<string, any> = {
    email: Mail,
    social: Globe,
    general: Settings,
};
</script>

<template>
    <Head :title="brand.name + ' Settings'" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div
                    class="h-8 w-1 rounded-full"
                    :style="{
                        backgroundColor:
                            brand.color || brandColors[brand.slug] || '#999',
                    }"
                />
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">
                        {{ brand.name }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        Brand settings &amp; channel configuration
                    </p>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <!-- Brand switcher -->
                <select
                    class="rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 outline-none focus:border-blue-300"
                    @change="
                        router.get(
                            '/brands/' +
                                ($event.target as HTMLSelectElement).value +
                                '/settings',
                        )
                    "
                >
                    <option
                        v-for="b in brands"
                        :key="b.slug"
                        :value="b.slug"
                        :selected="b.slug === brand.slug"
                    >
                        {{ b.name }}
                    </option>
                </select>
                <a
                    :href="`/admin/brands/${brand.id}/edit`"
                    class="rounded-md border px-3 py-1.5 text-xs text-gray-600 hover:bg-gray-50"
                    >Filament editor →</a
                >
            </div>
        </div>

        <!-- Success / Error -->
        <div
            v-if="successMessage"
            class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700"
        >
            <Check class="h-4 w-4" /> {{ successMessage }}
        </div>
        <div
            v-if="errorMessage"
            class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
        >
            <X class="h-4 w-4" /> {{ errorMessage }}
        </div>

        <!-- Tabs -->
        <div class="flex gap-2 border-b border-gray-200">
            <button
                v-for="tab in ['email', 'social', 'general'] as const"
                :key="tab"
                class="flex items-center gap-2 border-b-2 px-4 py-3 text-sm font-medium transition-colors"
                :class="
                    activeTab === tab
                        ? 'border-blue-600 text-blue-700'
                        : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                "
                @click="activeTab = tab"
            >
                <component :is="tabIcon[tab]" class="h-4 w-4" />
                <span class="capitalize">{{
                    tab === 'email'
                        ? 'Email Sending'
                        : tab === 'social'
                          ? 'Social Channels'
                          : 'General'
                }}</span>
            </button>
        </div>

        <!-- EMAIL TAB -->
        <div v-if="activeTab === 'email'" class="space-y-6">
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <Send class="h-4 w-4 text-blue-500" />
                    <h3 class="text-sm font-semibold">Sender Name</h3>
                </div>
                <p class="mb-3 text-xs text-muted-foreground">
                    This name appears as the sender on all outgoing emails for
                    this brand.
                </p>
                <input
                    v-model="senderName"
                    type="text"
                    placeholder="e.g. UjuziPlus Team"
                    class="w-full max-w-md rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-blue-300 focus:ring-1 focus:ring-blue-200"
                />
            </div>

            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <Mail class="h-4 w-4 text-emerald-500" />
                    <h3 class="text-sm font-semibold">Sender Emails</h3>
                    <span class="ml-auto text-xs text-muted-foreground"
                        >{{ senderEmails.length }} addresses</span
                    >
                </div>
                <p class="mb-3 text-xs text-muted-foreground">
                    Emails are rotated randomly to improve deliverability and
                    avoid spam flags. Each outgoing email picks one at random.
                </p>

                <!-- Add new email -->
                <div class="mb-4 flex items-center gap-2">
                    <input
                        v-model="newEmail"
                        type="email"
                        placeholder="Add sender email..."
                        class="max-w-md flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-blue-300 focus:ring-1 focus:ring-blue-200"
                        @keydown.enter.prevent="addEmail"
                    />
                    <button
                        @click="addEmail"
                        :disabled="!newEmail.trim()"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-40"
                    >
                        <Plus class="h-4 w-4" /> Add
                    </button>
                </div>

                <!-- Email list -->
                <div class="space-y-1.5">
                    <div
                        v-for="(email, idx) in senderEmails"
                        :key="idx"
                        class="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2 text-sm hover:bg-gray-50"
                    >
                        <div class="flex items-center gap-2">
                            <Mail class="h-3.5 w-3.5 text-gray-400" />
                            <span class="font-medium text-gray-700">{{
                                email
                            }}</span>
                        </div>
                        <button
                            @click="removeEmail(idx)"
                            class="rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600"
                            title="Remove"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                    </div>
                    <div
                        v-if="senderEmails.length === 0"
                        class="py-6 text-center text-sm text-muted-foreground"
                    >
                        No sender emails configured. Add at least one above.
                    </div>
                </div>
            </div>
        </div>

        <!-- SOCIAL TAB -->
        <div v-if="activeTab === 'social'" class="space-y-6">
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <MessageSquare class="h-4 w-4 text-green-500" />
                    <h3 class="text-sm font-semibold">WhatsApp</h3>
                </div>
                <p class="mb-3 text-xs text-muted-foreground">
                    WhatsApp Business API or personal number for this brand.
                </p>
                <div class="grid max-w-md gap-3">
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-gray-500"
                            >Phone Number</label
                        >
                        <input
                            v-model="settings.whatsapp_phone"
                            type="text"
                            placeholder="e.g. +254712345678"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-blue-300 focus:ring-1 focus:ring-blue-200"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-gray-500"
                            >API Key / Token</label
                        >
                        <input
                            v-model="settings.whatsapp_api_key"
                            type="password"
                            placeholder="WhatsApp API token"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-blue-300 focus:ring-1 focus:ring-blue-200"
                        />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <Globe class="h-4 w-4 text-orange-500" />
                    <h3 class="text-sm font-semibold">Reddit</h3>
                </div>
                <p class="mb-3 text-xs text-muted-foreground">
                    Reddit account and credentials for organic brand presence.
                </p>
                <div class="grid max-w-md gap-3">
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-gray-500"
                            >Username</label
                        >
                        <input
                            v-model="settings.reddit_username"
                            type="text"
                            placeholder="Reddit username"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-blue-300 focus:ring-1 focus:ring-blue-200"
                        />
                    </div>
                </div>
            </div>

            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <Smartphone class="h-4 w-4 text-blue-500" />
                    <h3 class="text-sm font-semibold">LinkedIn</h3>
                </div>
                <p class="mb-3 text-xs text-muted-foreground">
                    LinkedIn profile or company page for thought leadership.
                </p>
                <div class="grid max-w-md gap-3">
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-gray-500"
                            >Profile URL</label
                        >
                        <input
                            v-model="settings.linkedin_url"
                            type="url"
                            placeholder="https://linkedin.com/company/..."
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-blue-300 focus:ring-1 focus:ring-blue-200"
                        />
                    </div>
                </div>
            </div>

            <div
                class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-5 text-center text-sm text-muted-foreground"
            >
                <Plus class="mx-auto mb-2 h-5 w-5 text-gray-400" />
                More channels will be added here as they're integrated
                (Telegram, TikTok, Google Business Profile, etc.)
            </div>
        </div>

        <!-- GENERAL TAB -->
        <div v-if="activeTab === 'general'" class="space-y-6">
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center gap-2">
                    <Building2 class="h-4 w-4 text-indigo-500" />
                    <h3 class="text-sm font-semibold">Brand Info</h3>
                </div>
                <div class="grid max-w-lg gap-4">
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-gray-500"
                            >Name</label
                        >
                        <input
                            :value="brand.name"
                            disabled
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-gray-500"
                            >Slug</label
                        >
                        <input
                            :value="brand.slug"
                            disabled
                            class="w-full rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-gray-500"
                            >Description</label
                        >
                        <textarea
                            :value="brand.description || ''"
                            disabled
                            rows="2"
                            class="w-full resize-none rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-gray-500"
                            >Brand Voice</label
                        >
                        <textarea
                            :value="brand.brand_voice || ''"
                            disabled
                            rows="3"
                            class="w-full resize-none rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-gray-500"
                            >Market / KPI</label
                        >
                        <div class="flex gap-3">
                            <input
                                :value="brand.primary_market || ''"
                                disabled
                                class="flex-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500"
                            />
                            <input
                                :value="brand.primary_kpi || ''"
                                disabled
                                class="flex-1 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500"
                            />
                        </div>
                    </div>
                </div>
                <p class="mt-3 text-xs text-muted-foreground">
                    Basic brand info is managed in the Filament admin editor.
                </p>
            </div>
        </div>

        <!-- Save Button (sticky) -->
        <div
            class="sticky bottom-0 mt-auto flex items-center justify-end gap-3 rounded-xl border bg-card p-4 shadow-lg"
        >
            <a
                href="/admin/brands"
                class="rounded-lg border px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50"
                >Cancel</a
            >
            <button
                @click="saveSettings"
                :disabled="saving"
                class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-6 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
            >
                <Save class="h-4 w-4" />
                {{ saving ? 'Saving...' : 'Save Settings' }}
            </button>
        </div>
    </div>
</template>
