<script setup lang="ts">
import { ref } from 'vue';
import {
    ChevronDown,
    Mail,
    Phone,
    Globe,
    MapPin,
    Eye,
    MousePointerClick,
    MessageSquare,
} from '@lucide/vue';

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

const props = defineProps<{
    lead: LeadData;
}>();

const expanded = ref(false);

const segmentColors: Record<string, string> = {
    rabbit: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    deer: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    mouse: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
    elephant: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
};

const statusColors: Record<string, string> = {
    new: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    enriching:
        'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
    enriched:
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    no_email_found:
        'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
    emailed:
        'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
    replied:
        'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
    interested:
        'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
    not_interested:
        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    suppressed:
        'bg-stone-100 text-stone-600 dark:bg-stone-800 dark:text-stone-400',
};

const tierColors: Record<string, string> = {
    hot: 'bg-red-500 text-white',
    warm: 'bg-orange-500 text-white',
    moderate: 'bg-amber-500 text-white',
    cold: 'bg-blue-500 text-white',
    frigid: 'bg-gray-400 text-white',
};

const confidenceLabels: Record<string, string> = {
    verified: 'Verified',
    inferred: 'Inferred',
    estimated: 'Estimated',
    unavailable: 'Unavailable',
};

function formatDate(iso: string | null): string {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function toggle() {
    expanded.value = !expanded.value;
}
</script>

<template>
    <div
        class="border-b border-gray-100 transition-colors hover:bg-gray-50/50"
        :class="{ 'bg-blue-50/30': expanded }"
    >
        <!-- Main row -->
        <div
            class="flex cursor-pointer items-center gap-3 px-4 py-2.5"
            @click="toggle"
        >
            <!-- Expand chevron -->
            <ChevronDown
                class="h-3.5 w-3.5 shrink-0 text-gray-400 transition-transform"
                :class="{ 'rotate-180': expanded }"
            />

            <!-- Brand color bar -->
            <div
                class="h-8 w-1 shrink-0 rounded-full"
                :style="{ backgroundColor: lead.brand?.color || '#ccc' }"
            />

            <!-- Score badge -->
            <div
                class="flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-lg text-xs font-bold"
                :class="
                    tierColors[lead.score_tier] || 'bg-gray-200 text-gray-600'
                "
            >
                <span class="text-base leading-none">{{ lead.score }}</span>
            </div>

            <!-- Lead info -->
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span
                        class="truncate text-sm font-semibold text-gray-900"
                        >{{ lead.company_name }}</span
                    >
                    <span
                        class="shrink-0 rounded px-1.5 py-0.5 text-[10px] font-medium capitalize"
                        :class="
                            segmentColors[lead.segment] || segmentColors.mouse
                        "
                    >
                        {{ lead.segment }}
                    </span>
                </div>
                <div
                    class="mt-0.5 flex items-center gap-2 text-xs text-gray-500"
                >
                    <span v-if="lead.email" class="truncate">{{
                        lead.email
                    }}</span>
                    <span v-else class="text-gray-400 italic">No email</span>
                    <span
                        v-if="lead.city"
                        class="flex shrink-0 items-center gap-0.5"
                    >
                        <MapPin class="h-3 w-3" />{{ lead.city }}
                    </span>
                </div>
            </div>

            <!-- Subcategory -->
            <span
                class="w-28 shrink-0 truncate text-xs text-gray-500"
                :title="lead.subcategory || undefined"
            >
                {{ lead.subcategory || '—' }}
            </span>

            <!-- Status badge -->
            <span
                class="shrink-0 rounded px-2 py-0.5 text-[10px] font-medium capitalize"
                :class="statusColors[lead.status] || statusColors.new"
            >
                {{ lead.status.replace('_', ' ') }}
            </span>

            <!-- Engagement indicators -->
            <div class="flex shrink-0 items-center gap-2 text-xs text-gray-500">
                <span
                    v-if="lead.emails_sent > 0"
                    class="flex items-center gap-0.5"
                    :title="`${lead.emails_sent} sent`"
                >
                    <Mail class="h-3 w-3" />{{ lead.emails_sent }}
                </span>
                <span
                    v-if="lead.emails_opened > 0"
                    class="flex items-center gap-0.5"
                    :title="`${lead.emails_opened} opened`"
                >
                    <Eye class="h-3 w-3 text-blue-500" />{{
                        lead.emails_opened
                    }}
                </span>
                <span
                    v-if="lead.emails_clicked > 0"
                    class="flex items-center gap-0.5"
                    :title="`${lead.emails_clicked} clicked`"
                >
                    <MousePointerClick class="h-3 w-3 text-purple-500" />{{
                        lead.emails_clicked
                    }}
                </span>
            </div>

            <!-- Brand name -->
            <span
                class="w-20 shrink-0 truncate text-right text-xs text-gray-400"
            >
                {{ lead.brand?.name || '' }}
            </span>
        </div>

        <!-- Expanded detail -->
        <div
            v-if="expanded"
            class="border-t border-gray-50 bg-white px-4 pt-1 pb-3"
        >
            <div class="grid grid-cols-2 gap-3 text-xs md:grid-cols-4">
                <!-- Contact info -->
                <div class="space-y-1">
                    <div
                        class="text-[10px] font-medium tracking-wider text-gray-400 uppercase"
                    >
                        Contact
                    </div>
                    <div v-if="lead.contact_name" class="text-gray-700">
                        {{ lead.contact_name }}
                    </div>
                    <div
                        v-if="lead.phone"
                        class="flex items-center gap-1 text-gray-700"
                    >
                        <Phone class="h-3 w-3" />{{ lead.phone }}
                    </div>
                    <div
                        v-if="lead.website"
                        class="flex items-center gap-1 truncate text-blue-600"
                    >
                        <Globe class="h-3 w-3 shrink-0" />
                        <a
                            :href="lead.website"
                            target="_blank"
                            class="truncate hover:underline"
                            >{{ lead.website }}</a
                        >
                    </div>
                </div>

                <!-- Classification -->
                <div class="space-y-1">
                    <div
                        class="text-[10px] font-medium tracking-wider text-gray-400 uppercase"
                    >
                        Classification
                    </div>
                    <div v-if="lead.category" class="text-gray-700">
                        {{ lead.category }}
                    </div>
                    <div v-if="lead.subcategory" class="text-gray-700">
                        Subcategory: {{ lead.subcategory }}
                    </div>
                    <div v-if="lead.email_confidence" class="text-gray-700">
                        Email:
                        {{
                            confidenceLabels[lead.email_confidence] ||
                            lead.email_confidence
                        }}
                    </div>
                    <div class="text-gray-700">
                        Enrichment attempts: {{ lead.enrichment_attempts }}
                    </div>
                    <div
                        v-if="lead.email_verified"
                        class="font-medium text-emerald-600"
                    >
                        Email verified
                    </div>
                </div>

                <!-- Engagement -->
                <div class="space-y-1">
                    <div
                        class="text-[10px] font-medium tracking-wider text-gray-400 uppercase"
                    >
                        Engagement
                    </div>
                    <div class="flex items-center gap-1 text-gray-700">
                        <Mail class="h-3 w-3" />{{ lead.emails_sent }} sent /
                        {{ lead.total_emails }} total
                    </div>
                    <div
                        v-if="lead.emails_opened > 0"
                        class="flex items-center gap-1 text-gray-700"
                    >
                        <Eye class="h-3 w-3 text-blue-500" />{{
                            lead.emails_opened
                        }}
                        opened
                    </div>
                    <div
                        v-if="lead.emails_clicked > 0"
                        class="flex items-center gap-1 text-gray-700"
                    >
                        <MousePointerClick class="h-3 w-3 text-purple-500" />{{
                            lead.emails_clicked
                        }}
                        clicked
                    </div>
                </div>

                <!-- Meta + actions -->
                <div class="space-y-1">
                    <div
                        class="text-[10px] font-medium tracking-wider text-gray-400 uppercase"
                    >
                        Details
                    </div>
                    <div class="text-gray-700">Country: {{ lead.country }}</div>
                    <div v-if="lead.created_at" class="text-gray-500">
                        Added: {{ formatDate(lead.created_at) }}
                    </div>
                    <div class="pt-1">
                        <a
                            :href="`/admin/leads/${lead.id}`"
                            class="inline-flex items-center gap-1 text-blue-600 hover:underline"
                        >
                            <MessageSquare class="h-3 w-3" /> View in admin
                        </a>
                    </div>
                </div>
            </div>

            <!-- Score breakdown bar -->
            <div class="mt-3 flex items-center gap-2">
                <span
                    class="text-[10px] font-medium tracking-wider text-gray-400 uppercase"
                    >Score</span
                >
                <div
                    class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100"
                >
                    <div
                        class="h-full rounded-full transition-all"
                        :class="tierColors[lead.score_tier] || 'bg-gray-300'"
                        :style="{ width: lead.score + '%' }"
                    />
                </div>
                <span
                    class="text-xs font-bold"
                    :class="
                        tierColors[lead.score_tier]
                            ? 'text-gray-700'
                            : 'text-gray-400'
                    "
                >
                    {{ lead.score }}/100
                </span>
            </div>
        </div>
    </div>
</template>
