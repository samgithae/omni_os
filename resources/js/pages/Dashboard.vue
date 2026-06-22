<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Users, Mail, Ban, Building2, TrendingUp, MapPin, Activity, LayoutGrid, Star, Award } from '@lucide/vue';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

interface Stats {
    total_leads: number;
    enriched_leads: number;
    new_leads: number;
    no_email_leads: number;
    total_emails: number;
    suppressed: number;
    active_brands: number;
    rabbits: number;
    deer: number;
    total_email_messages: number;
    pending_approval: number;
    approved_emails: number;
    rejected_emails: number;
    sent_emails: number;
    queued_emails: number;
    draft_emails: number;
    failed_emails: number;
    opened_emails: number;
    clicked_emails: number;
    leads_with_sequences: number;
}

interface BrandData {
    name: string;
    slug: string;
    color: string;
    leads_count: number;
}

interface EventData {
    id: number;
    event_type: string;
    company: string;
    created_at: string;
}

interface TopLead {
    id: number;
    company_name: string;
    email: string | null;
    segment: string;
    city: string | null;
    score: number;
    status: string;
    brand: { name: string; slug: string; color: string | null } | null;
}

const props = defineProps<{
    stats: Stats;
    leadsByBrand: BrandData[];
    leadsBySegment: Record<string, number>;
    leadsByStatus: Record<string, number>;
    topCities: Record<string, number>;
    recentEvents: EventData[];
    emailsByStep: Record<string, number>;
    emailApprovalBreakdown: Record<string, number>;
    emailStatusBreakdown: Record<string, number>;
    avgScore: number;
    scoreTiers: Record<string, number>;
    topLeads: TopLead[];
}>();

const segmentColors: Record<string, string> = {
    rabbit: '#10b981',
    deer: '#f59e0b',
    mouse: '#6b7280',
    elephant: '#ef4444',
};

const statusColors: Record<string, string> = {
    new: '#3b82f6',
    enriching: '#f59e0b',
    enriched: '#10b981',
    emailed: '#6366f1',
    replied: '#8b5cf6',
    interested: '#059669',
    not_interested: '#dc2626',
    no_email_found: '#ef4444',
    suppressed: '#7f1d1d',
    closed: '#6b7280',
};

const segmentLabels: Record<string, string> = {
    rabbit: 'Rabbits',
    deer: 'Deer',
    mouse: 'Mice',
    elephant: 'Elephants',
};

const statusLabels: Record<string, string> = {
    new: 'New',
    enriching: 'Enriching',
    enriched: 'Enriched',
    emailed: 'Emailed',
    replied: 'Replied',
    interested: 'Interested',
    not_interested: 'Not Interested',
    no_email_found: 'No Email Found',
    suppressed: 'Suppressed',
    closed: 'Closed',
};

function maxCityCount(): number {
    return Math.max(...Object.values(props.topCities), 1);
}

function maxBrandCount(): number {
    return Math.max(...props.leadsByBrand.map((b) => b.leads_count), 1);
}

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

function maxTierCount(): number {
    return Math.max(...Object.values(props.scoreTiers), 1);
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 overflow-x-auto p-6">
        <!-- Page Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Omni OS Dashboard</h1>
                <p class="text-sm text-muted-foreground">Multi-brand marketing automation overview</p>
            </div>
            <a
                href="/admin"
                class="inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
            >
                <Building2 class="h-4 w-4" />
                Admin Panel
            </a>
        </div>

        <!-- Stat Cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-muted-foreground">Total Leads</span>
                    <Users class="h-5 w-5 text-indigo-500" />
                </div>
                <div class="mt-2 text-3xl font-bold">{{ stats.total_leads }}</div>
                <div class="mt-1 text-xs text-muted-foreground">
                    {{ stats.rabbits }} rabbits &middot; {{ stats.deer }} deer
                </div>
            </div>

            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-muted-foreground">With Email</span>
                    <Mail class="h-5 w-5 text-emerald-500" />
                </div>
                <div class="mt-2 text-3xl font-bold">{{ stats.total_emails }}</div>
                <div class="mt-1 text-xs text-muted-foreground">
                    {{ stats.enriched_leads }} enriched &middot; {{ stats.new_leads }} new
                </div>
            </div>

            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-muted-foreground">Suppressed</span>
                    <Ban class="h-5 w-5 text-red-500" />
                </div>
                <div class="mt-2 text-3xl font-bold">{{ stats.suppressed }}</div>
                <div class="mt-1 text-xs text-muted-foreground">Do-not-contact list</div>
            </div>

            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-muted-foreground">Active Brands</span>
                    <Building2 class="h-5 w-5 text-amber-500" />
                </div>
                <div class="mt-2 text-3xl font-bold">{{ stats.active_brands }}</div>
                <div class="mt-1 text-xs text-muted-foreground">In portfolio</div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Leads by Brand -->
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <Building2 class="h-4 w-4 text-muted-foreground" />
                    <h3 class="text-sm font-semibold">Leads by Brand</h3>
                </div>
                <div class="space-y-3">
                    <div v-for="brand in leadsByBrand" :key="brand.slug">
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="font-medium">{{ brand.name }}</span>
                            <span class="text-muted-foreground">{{ brand.leads_count }}</span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-muted overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all"
                                :style="{
                                    width: (brand.leads_count / maxBrandCount() * 100) + '%',
                                    backgroundColor: brand.color,
                                }"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leads by Segment -->
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <TrendingUp class="h-4 w-4 text-muted-foreground" />
                    <h3 class="text-sm font-semibold">Leads by Segment</h3>
                </div>
                <div class="space-y-3">
                    <div v-for="(count, segment) in leadsBySegment" :key="segment">
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="font-medium">{{ segmentLabels[segment] || segment }}</span>
                            <span class="text-muted-foreground">{{ count }}</span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-muted overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all"
                                :style="{
                                    width: (count / stats.total_leads * 100) + '%',
                                    backgroundColor: segmentColors[segment] || '#999',
                                }"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Leads by Status -->
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <Activity class="h-4 w-4 text-muted-foreground" />
                    <h3 class="text-sm font-semibold">Leads by Status</h3>
                </div>
                <div class="space-y-3">
                    <div v-for="(count, status) in leadsByStatus" :key="status">
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="font-medium">{{ statusLabels[status] || status }}</span>
                            <span class="text-muted-foreground">{{ count }}</span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-muted overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all"
                                :style="{
                                    width: (count / stats.total_leads * 100) + '%',
                                    backgroundColor: statusColors[status] || '#999',
                                }"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Row: Cities + Recent Activity -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Top Cities -->
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <MapPin class="h-4 w-4 text-muted-foreground" />
                    <h3 class="text-sm font-semibold">Top 10 Cities</h3>
                </div>
                <div class="space-y-2">
                    <div v-for="(count, city) in topCities" :key="city">
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="font-medium">{{ city }}</span>
                            <span class="text-muted-foreground">{{ count }} leads</span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-muted overflow-hidden">
                            <div
                                class="h-full rounded-full bg-indigo-500 transition-all"
                                :style="{ width: (count / maxCityCount() * 100) + '%' }"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <Activity class="h-4 w-4 text-muted-foreground" />
                    <h3 class="text-sm font-semibold">Recent Activity</h3>
                </div>
                <div class="space-y-2 max-h-80 overflow-y-auto">
                    <div
                        v-for="event in recentEvents"
                        :key="event.id"
                        class="flex items-center gap-3 rounded-lg border px-3 py-2 text-sm hover:bg-muted/50"
                    >
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-indigo-100 dark:bg-indigo-900/30"
                        >
                            <Users class="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium truncate">{{ event.company }}</p>
                            <p class="text-xs text-muted-foreground capitalize">
                                {{ event.event_type.replace('_', ' ') }}
                            </p>
                        </div>
                        <span class="text-xs text-muted-foreground shrink-0">{{ event.created_at }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Email Sequence Section -->
        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Email Stats Card -->
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <Mail class="h-4 w-4 text-muted-foreground" />
                    <h3 class="text-sm font-semibold">Email Sequences</h3>
                </div>
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium">Total Emails</span>
                        <span class="text-2xl font-bold">{{ stats.total_email_messages }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Pending Approval</span>
                        <span class="text-amber-600 font-semibold">{{ stats.pending_approval }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Approved</span>
                        <span class="text-emerald-600 font-semibold">{{ stats.approved_emails }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Rejected</span>
                        <span class="text-red-600 font-semibold">{{ stats.rejected_emails }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-muted-foreground">Sent</span>
                        <span class="text-blue-600 font-semibold">{{ stats.sent_emails }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm border-t pt-2">
                        <span class="text-muted-foreground">Leads w/ Sequences</span>
                        <span class="font-semibold">{{ stats.leads_with_sequences }}</span>
                    </div>
                </div>
            </div>

            <!-- Email by Step -->
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <Mail class="h-4 w-4 text-muted-foreground" />
                    <h3 class="text-sm font-semibold">Emails by Sequence Step</h3>
                </div>
                <div class="space-y-3">
                    <div v-for="(count, step) in emailsByStep" :key="step">
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="font-medium">Step {{ step }}</span>
                            <span class="text-muted-foreground">{{ count }}</span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-muted overflow-hidden">
                            <div
                                class="h-full rounded-full bg-indigo-500 transition-all"
                                :style="{ width: (count / Math.max(...Object.values(emailsByStep), 1) * 100) + '%' }"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Approval Breakdown -->
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <Activity class="h-4 w-4 text-muted-foreground" />
                    <h3 class="text-sm font-semibold">Approval Breakdown</h3>
                </div>
                <div class="space-y-3">
                    <div v-for="(count, status) in emailApprovalBreakdown" :key="status">
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="font-medium capitalize">{{ status }}</span>
                            <span class="text-muted-foreground">{{ count }}</span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-muted overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all"
                                :style="{
                                    width: (count / Math.max(stats.total_email_messages, 1) * 100) + '%',
                                    backgroundColor: status === 'pending' ? '#f59e0b' : status === 'approved' ? '#10b981' : '#ef4444',
                                }"
                            />
                        </div>
                    </div>
                    <div class="border-t pt-2 mt-2">
                        <div class="text-xs text-muted-foreground mb-1">Send Status</div>
                        <div class="flex flex-wrap gap-2">
                            <span class="inline-flex items-center gap-1 rounded-md bg-gray-100 dark:bg-gray-800 px-2 py-1 text-xs">
                                Draft: {{ emailStatusBreakdown.draft || 0 }}
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-md bg-blue-100 dark:bg-blue-900/30 px-2 py-1 text-xs">
                                Queued: {{ emailStatusBreakdown.queued || 0 }}
                            </span>
                            <span class="inline-flex items-center gap-1 rounded-md bg-emerald-100 dark:bg-emerald-900/30 px-2 py-1 text-xs">
                                Sent: {{ emailStatusBreakdown.sent || 0 }}
                            </span>
                            <span v-if="emailStatusBreakdown.failed > 0" class="inline-flex items-center gap-1 rounded-md bg-red-100 dark:bg-red-900/30 px-2 py-1 text-xs">
                                Failed: {{ emailStatusBreakdown.failed || 0 }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lead Scoring Section -->
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Score Distribution -->
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <Star class="h-4 w-4 text-indigo-500" />
                    <h3 class="text-sm font-semibold">Lead Score Distribution</h3>
                    <span class="ml-auto text-xs text-muted-foreground">Avg: <span class="font-bold text-indigo-600">{{ avgScore }}</span></span>
                </div>
                <div class="space-y-3">
                    <div v-for="(count, tier) in scoreTiers" :key="tier">
                        <div class="flex items-center justify-between text-sm mb-1">
                            <span class="font-medium">{{ tierLabels[tier] || tier }}</span>
                            <span class="text-muted-foreground">{{ count }} leads</span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-muted overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all"
                                :style="{
                                    width: (count / maxTierCount() * 100) + '%',
                                    backgroundColor: tierColors[tier] || '#999',
                                }"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Scored Leads -->
            <div class="rounded-xl border bg-card p-5 shadow-sm">
                <div class="flex items-center gap-2 mb-4">
                    <Award class="h-4 w-4 text-amber-500" />
                    <h3 class="text-sm font-semibold">Top Scored Leads</h3>
                    <a href="/leads" class="ml-auto text-xs text-blue-600 hover:underline">View all →</a>
                </div>
                <div class="space-y-2 max-h-80 overflow-y-auto">
                    <div
                        v-for="lead in topLeads"
                        :key="lead.id"
                        class="flex items-center gap-3 rounded-lg border px-3 py-2 text-sm hover:bg-muted/50"
                    >
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold text-white"
                            :style="{ backgroundColor: tierColors[
                                lead.score >= 80 ? 'hot' :
                                lead.score >= 60 ? 'warm' :
                                lead.score >= 40 ? 'moderate' :
                                lead.score >= 20 ? 'cold' : 'frigid'
                            ] }"
                        >
                            {{ lead.score }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="font-medium truncate">{{ lead.company_name }}</p>
                            <p class="text-xs text-muted-foreground truncate">
                                {{ lead.city || 'Unknown city' }} &middot; {{ lead.segment }}
                            </p>
                        </div>
                        <div
                            v-if="lead.brand"
                            class="h-2 w-2 shrink-0 rounded-full"
                            :style="{ backgroundColor: lead.brand.color || '#ccc' }"
                            :title="lead.brand.name"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="flex flex-wrap gap-3">
            <a
                href="/leads"
                class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium hover:bg-muted"
            >
                <Users class="h-4 w-4" />
                Manage Leads
            </a>
            <a
                href="/email-sequences"
                class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium hover:bg-muted"
            >
                <Mail class="h-4 w-4" />
                Email Sequences
            </a>
            <a
                href="/admin/brands"
                class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium hover:bg-muted"
            >
                <Building2 class="h-4 w-4" />
                Manage Brands
            </a>
            <a
                href="/admin/suppressions"
                class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium hover:bg-muted"
            >
                <Ban class="h-4 w-4" />
                Suppressions
            </a>
            <a
                href="/admin/mining-targets"
                class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium hover:bg-muted"
            >
                <MapPin class="h-4 w-4" />
                Mining Targets
            </a>
            <a
                href="/admin"
                class="inline-flex items-center gap-2 rounded-lg border px-4 py-2 text-sm font-medium hover:bg-muted"
            >
                <LayoutGrid class="h-4 w-4" />
                Full Admin Panel
            </a>
        </div>
    </div>
</template>
