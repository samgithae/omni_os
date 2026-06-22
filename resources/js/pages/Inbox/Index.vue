<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import { Mail, Inbox as InboxIcon, Search, X, Send, ArrowLeft, CircleDot } from '@lucide/vue'
import { dashboard } from '@/routes'

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Inbox', href: '/inbox' },
        ],
    },
})

interface BrandInfo {
    id: number
    name: string
    slug: string
    color: string | null
}

interface ReplyItem {
    id: number
    from_email: string
    subject: string | null
    body: string
    body_html: string | null
    classification: string | null
    classification_summary: string | null
    read: boolean
    received_at: string | null
    lead: {
        id: number
        company_name: string
        email: string | null
        segment: string
        city: string | null
        score: number
        status: string
    } | null
    brand: BrandInfo | null
}

interface PaginatedData<T> {
    data: T[]
    current_page: number
    last_page: number
    per_page: number
    total: number
    from: number
    to: number
    links: Array<{ url: string | null; label: string; active: boolean }>
}

const props = defineProps<{
    replies: PaginatedData<ReplyItem>
    unreadCount: number
    filters: Record<string, string | undefined>
    brands: BrandInfo[]
}>()

// Selected lead for conversation panel
const selectedLeadId = ref<number | null>(null)
const conversation = ref<any[]>([])
const leadContext = ref<any>(null)
const loadingConversation = ref(false)
const replyBody = ref('')
const replyError = ref<string | null>(null)
const replySuccess = ref<string | null>(null)

const searchInput = ref(props.filters.search || '')
let debounceTimer: ReturnType<typeof setTimeout> | null = null

watch(searchInput, (val) => {
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => applyFilter('search', val), 300)
})

function applyFilter(key: string, value: string | null) {
    const params = new URLSearchParams(window.location.search)
    if (value) params.set(key, value)
    else params.delete(key)
    params.delete('page')
    const qs = params.toString()
    router.get(qs ? `/inbox?${qs}` : '/inbox', {}, { preserveScroll: true, preserveState: true, replace: true })
}

function clearFilters() {
    router.get('/inbox', {}, { preserveScroll: true, preserveState: true, replace: true })
}

const hasActiveFilters = Object.values(props.filters).some(Boolean)

const classificationColors: Record<string, string> = {
    interested: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    not_interested: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    out_of_office: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    unsubscribe: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    bounce: 'bg-stone-100 text-stone-600 dark:bg-stone-800 dark:text-stone-400',
    unclassified: 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
}

const classificationLabels: Record<string, string> = {
    interested: 'Interested',
    not_interested: 'Not Interested',
    out_of_office: 'Out of Office',
    unsubscribe: 'Unsubscribe',
    bounce: 'Bounce',
    unclassified: 'Unclassified',
}

function formatTime(iso: string | null): string {
    if (!iso) return ''
    const d = new Date(iso)
    const now = new Date()
    const diffMs = now.getTime() - d.getTime()
    const diffMin = Math.floor(diffMs / 60000)
    const diffHr = Math.floor(diffMin / 60)
    const diffDay = Math.floor(diffHr / 24)
    if (diffMin < 1) return 'just now'
    if (diffMin < 60) return `${diffMin}m ago`
    if (diffHr < 24) return `${diffHr}h ago`
    if (diffDay < 7) return `${diffDay}d ago`
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

function bodySnippet(body: string, maxLen = 80): string {
    const stripped = body.replace(/<[^>]*>/g, '').trim()
    return stripped.length > maxLen ? stripped.substring(0, maxLen) + '...' : stripped
}

function selectReply(reply: ReplyItem) {
    if (reply.lead) {
        loadConversation(reply.lead.id)
    }
}

async function loadConversation(leadId: number) {
    loadingConversation.value = true
    selectedLeadId.value = leadId
    conversation.value = []
    leadContext.value = null
    replyError.value = null
    replySuccess.value = null

    try {
        const response = await fetch(`/inbox/conversation/${leadId}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            redirect: 'manual', // Don't follow redirects — a 302 means auth expired
        })

        if (response.type === 'opaqueredirect' || response.status === 302 || response.status === 0) {
            throw new Error('Session expired — please refresh the page')
        }

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}`)
        }

        const data = await response.json()
        conversation.value = data.thread || []
        leadContext.value = data.lead || null
    } catch (e) {
        replyError.value = 'Failed to load conversation: ' + (e instanceof Error ? e.message : 'unknown error')
    } finally {
        loadingConversation.value = false
    }
}

function sendReply() {
    if (!selectedLeadId.value || !replyBody.value.trim()) return
    replyError.value = null
    replySuccess.value = null

    router.post(`/inbox/${selectedLeadId.value}/reply`, {
        body: replyBody.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            replyBody.value = ''
            replySuccess.value = 'Reply sent'
            // Reload conversation to show the outbound reply
            loadConversation(selectedLeadId.value!)
            setTimeout(() => { replySuccess.value = null }, 3000)
        },
        onError: (errors) => {
            replyError.value = errors.body || 'Failed to send reply'
        },
    })
}

function goToPage(page: number) {
    const params = new URLSearchParams(window.location.search)
    params.set('page', String(page))
    router.get(`/inbox?${params.toString()}`, {}, { preserveScroll: true, preserveState: true, replace: true })
}
</script>

<template>
    <Head title="Inbox" />

    <div class="flex h-full flex-1 overflow-hidden">
        <!-- Left pane: reply list (360px fixed) -->
        <div class="flex w-[360px] shrink-0 flex-col border-r border-gray-200">
            <!-- Header -->
            <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                <div class="flex items-center gap-2">
                    <InboxIcon class="h-4 w-4 text-gray-400" />
                    <h1 class="text-sm font-semibold text-gray-900">Inbox</h1>
                    <span v-if="unreadCount > 0" class="rounded-full bg-blue-600 px-1.5 py-0.5 text-[10px] font-bold text-white">{{ unreadCount }}</span>
                </div>
                <span class="text-xs text-gray-500">{{ replies.total }} total</span>
            </div>

            <!-- Filter bar -->
            <div class="flex flex-wrap items-center gap-1.5 border-b border-gray-200 px-3 py-2">
                <select
                    class="rounded-md border border-gray-200 bg-white px-2 py-1 text-xs font-medium text-gray-700 outline-none focus:border-blue-300"
                    :value="filters.brand || ''"
                    @change="applyFilter('brand', ($event.target as HTMLSelectElement).value || null)"
                >
                    <option value="">All Brands</option>
                    <option v-for="b in brands" :key="b.slug" :value="b.slug">{{ b.name }}</option>
                </select>

                <select
                    class="rounded-md border border-gray-200 bg-white px-2 py-1 text-xs font-medium text-gray-700 outline-none focus:border-blue-300"
                    :value="filters.classification || ''"
                    @change="applyFilter('classification', ($event.target as HTMLSelectElement).value || null)"
                >
                    <option value="">All</option>
                    <option value="unclassified">Unclassified</option>
                    <option value="interested">Interested</option>
                    <option value="not_interested">Not Interested</option>
                    <option value="out_of_office">Out of Office</option>
                    <option value="unsubscribe">Unsubscribe</option>
                </select>

                <select
                    class="rounded-md border border-gray-200 bg-white px-2 py-1 text-xs font-medium text-gray-700 outline-none focus:border-blue-300"
                    :value="filters.read || ''"
                    @change="applyFilter('read', ($event.target as HTMLSelectElement).value || null)"
                >
                    <option value="">All</option>
                    <option value="unread">Unread</option>
                    <option value="read">Read</option>
                </select>

                <div class="relative flex-1 min-w-[100px]">
                    <Search class="pointer-events-none absolute left-1.5 top-1/2 h-3 w-3 -translate-y-1/2 text-gray-400" />
                    <input
                        v-model="searchInput"
                        type="text"
                        placeholder="Search..."
                        class="w-full rounded-md border border-gray-200 py-1 pl-6 pr-2 text-xs outline-none placeholder:text-gray-400 focus:border-blue-300"
                    />
                </div>

                <button v-if="hasActiveFilters" @click="clearFilters" class="text-gray-400 hover:text-gray-600">
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>

            <!-- Reply list -->
            <div class="flex-1 overflow-y-auto">
                <div v-if="replies.data.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                    <InboxIcon class="mb-2 h-8 w-8 text-gray-300" />
                    <p class="text-sm text-gray-500">No replies yet</p>
                    <p class="text-xs text-gray-400 mt-1">Replies will appear here when leads respond</p>
                </div>

                <div v-else>
                    <div
                        v-for="reply in replies.data"
                        :key="reply.id"
                        class="cursor-pointer border-b border-gray-50 px-4 py-3 transition-colors hover:bg-gray-50"
                        :class="{
                            'bg-blue-50/40': selectedLeadId === reply.lead?.id,
                            'font-semibold': !reply.read,
                        }"
                        @click="selectReply(reply)"
                    >
                        <div class="flex items-start gap-2">
                            <!-- Unread dot -->
                            <div class="mt-1 shrink-0">
                                <CircleDot v-if="!reply.read" class="h-2 w-2 text-blue-500" />
                                <div v-else class="h-2 w-2" />
                            </div>

                            <div class="flex-1 min-w-0">
                                <!-- Top row: company + time -->
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm truncate" :class="!reply.read ? 'font-bold text-gray-900' : 'font-medium text-gray-700'">
                                        {{ reply.lead?.company_name || reply.from_email }}
                                    </span>
                                    <span class="text-[10px] text-gray-400 shrink-0">{{ formatTime(reply.received_at) }}</span>
                                </div>

                                <!-- Subject -->
                                <p v-if="reply.subject" class="text-xs text-gray-600 truncate mt-0.5">{{ reply.subject }}</p>

                                <!-- Body snippet -->
                                <p class="text-xs text-gray-400 truncate mt-0.5">{{ bodySnippet(reply.body) }}</p>

                                <!-- Classification badge -->
                                <div class="flex items-center gap-1.5 mt-1">
                                    <span
                                        v-if="reply.classification"
                                        class="rounded px-1.5 py-0.5 text-[10px] font-medium"
                                        :class="classificationColors[reply.classification] || classificationColors.unclassified"
                                    >
                                        {{ classificationLabels[reply.classification] || reply.classification }}
                                    </span>
                                    <span v-else class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-400 dark:bg-gray-800">
                                        Pending
                                    </span>
                                    <!-- Brand dot -->
                                    <span v-if="reply.brand" class="h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: reply.brand.color || '#ccc' }" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination -->
                    <div v-if="replies.last_page > 1" class="flex items-center justify-between border-t px-4 py-2">
                        <span class="text-[10px] text-gray-400">{{ replies.from }}–{{ replies.to }} of {{ replies.total }}</span>
                        <div class="flex gap-1">
                            <button
                                v-for="link in replies.links"
                                :key="link.label"
                                class="rounded px-1.5 py-0.5 text-[10px] font-medium"
                                :class="{ 'bg-blue-600 text-white': link.active, 'text-gray-500 hover:bg-gray-100': !link.active && link.url, 'text-gray-300': !link.url }"
                                :disabled="!link.url"
                                @click="link.url && goToPage(Number(link.label))"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right pane: conversation thread -->
        <div class="flex flex-1 flex-col overflow-hidden">
            <div v-if="!selectedLeadId" class="flex flex-1 items-center justify-center text-center">
                <div>
                    <Mail class="mx-auto mb-3 h-10 w-10 text-gray-300" />
                    <p class="text-sm text-gray-500">Select a reply to view the conversation</p>
                    <p class="text-xs text-gray-400 mt-1">Replies from leads will appear here with full thread history</p>
                </div>
            </div>

            <template v-else>
                <!-- Conversation header -->
                <div class="flex items-center gap-2 border-b border-gray-200 px-4 py-3">
                    <button @click="selectedLeadId = null" class="text-gray-400 hover:text-gray-600 md:hidden">
                        <ArrowLeft class="h-4 w-4" />
                    </button>
                    <div class="flex-1">
                        <h2 class="text-sm font-semibold text-gray-900">{{ leadContext?.company_name || 'Loading...' }}</h2>
                        <p class="text-xs text-gray-500">{{ leadContext?.email }} &middot; {{ leadContext?.segment }} &middot; Score: {{ leadContext?.score }}</p>
                    </div>
                    <a v-if="leadContext" :href="`/admin/leads/${leadContext.id}`" class="text-xs text-blue-600 hover:underline">View in admin →</a>
                </div>

                <!-- Thread -->
                <div class="flex-1 overflow-y-auto px-4 py-4">
                    <div v-if="loadingConversation" class="flex items-center justify-center py-12">
                        <p class="text-sm text-gray-400">Loading conversation...</p>
                    </div>

                    <div v-else class="space-y-4">
                        <div
                            v-for="item in conversation"
                            :key="item.id"
                            class="flex"
                            :class="item.direction === 'outbound' ? 'justify-end' : 'justify-start'"
                        >
                            <div
                                class="max-w-[70%] rounded-lg px-4 py-3"
                                :class="item.direction === 'outbound'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'"
                            >
                                <!-- Email item (sent sequence email) -->
                                <template v-if="item.type === 'email'">
                                    <div class="text-[10px] uppercase tracking-wider opacity-60 mb-1">
                                        Sent email &middot; Step {{ item.sequence_step }}
                                        <span v-if="item.opened_at"> &middot; Opened</span>
                                    </div>
                                    <p class="text-sm font-medium">{{ item.subject }}</p>
                                    <div class="text-xs mt-1 opacity-80" v-html="bodySnippet(item.body, 200)" />
                                </template>

                                <!-- Reply item -->
                                <template v-else>
                                    <div class="text-[10px] uppercase tracking-wider opacity-60 mb-1">
                                        {{ item.direction === 'outbound' ? 'You' : item.from_email }}
                                        <span v-if="item.classification && item.direction === 'inbound'">
                                            &middot; <span :class="classificationColors[item.classification]">{{ classificationLabels[item.classification] }}</span>
                                        </span>
                                    </div>
                                    <p v-if="item.subject" class="text-sm font-medium mb-1">{{ item.subject }}</p>
                                    <div v-if="item.body_html" class="text-sm" v-html="item.body_html"></div>
                                    <div v-else class="text-sm whitespace-pre-wrap">{{ item.body }}</div>
                                    <p v-if="item.classification_summary" class="text-xs italic mt-2 opacity-60">
                                        AI: {{ item.classification_summary }}
                                    </p>
                                </template>

                                <div class="text-[10px] mt-1 opacity-50">
                                    {{ formatTime(item.sent_at || item.received_at || item.created_at) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Compose box -->
                <div class="border-t border-gray-200 px-4 py-3">
                    <div v-if="replyError" class="mb-2 rounded-md bg-red-50 px-3 py-1.5 text-xs text-red-600">{{ replyError }}</div>
                    <div v-if="replySuccess" class="mb-2 rounded-md bg-green-50 px-3 py-1.5 text-xs text-green-600">{{ replySuccess }}</div>

                    <div v-if="leadContext?.is_suppressed" class="flex items-center justify-center py-3 text-center">
                        <p class="text-xs text-red-500">This lead is suppressed — cannot send replies</p>
                    </div>
                    <div v-else class="flex items-end gap-2">
                        <textarea
                            v-model="replyBody"
                            rows="2"
                            placeholder="Type your reply..."
                            class="flex-1 rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-blue-300 focus:ring-1 focus:ring-blue-200 resize-none"
                            @keydown.meta.enter="sendReply"
                            @keydown.ctrl.enter="sendReply"
                        ></textarea>
                        <button
                            @click="sendReply"
                            :disabled="!replyBody.trim()"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-40 disabled:cursor-not-allowed"
                        >
                            <Send class="h-4 w-4" />
                            Send
                        </button>
                    </div>
                    <p class="text-[10px] text-gray-400 mt-1">To: {{ leadContext?.email }} &middot; From: {{ leadContext?.brand?.name || 'Omni OS' }}</p>
                </div>
            </template>
        </div>
    </div>
</template>