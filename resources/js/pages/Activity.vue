<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import { Activity, ChevronDown, ChevronRight, Clock, MessageSquare, Send, Bot, Flag, CheckCircle, Loader } from '@lucide/vue'
import { dashboard } from '@/routes'

defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Dashboard', href: dashboard() },
      { title: 'Activity Feed', href: '/activity' },
    ],
  },
})

interface BrandInfo {
  id: number
  name: string
  slug: string
  color: string | null
}

interface CommentData {
  id: number
  author: string
  body: string
  metadata: Record<string, unknown> | null
  is_instruction: boolean
  instruction_status: string | null
  created_at: string
  relative_time: string
}

interface ActivityEventData {
  id: number
  source: string
  event_type: string
  title: string
  body: string | null
  metadata: Record<string, unknown> | null
  severity: 'info' | 'success' | 'warning' | 'error'
  brand: BrandInfo | null
  relative_time: string
  created_at: string
  comments_count?: number
  comments?: CommentData[]
}

const props = defineProps<{
  groupedEvents: Record<string, ActivityEventData[]>
  brands: BrandInfo[]
  filters: Record<string, string | undefined>
  latestId: number
}>()

const activeBrand = ref<string | undefined>(props.filters.brand)
const newEventCount = ref(0)
const latestKnownId = ref(props.latestId)
let pollInterval: ReturnType<typeof setInterval> | null = null
const expandedEvents = ref<Set<number>>(new Set())
const expandedDays = ref<Set<string>>(new Set(Object.keys(props.groupedEvents)))
const loadingMore = ref(false)

// Comment thread state
const openThreads = ref<Set<number>>(new Set())
const commentTexts = ref<Record<number, string>>({})
const sendingComments = ref<Record<number, boolean>>({})
const toggleInstructions = ref<Record<number, boolean>>({})

const dayGroups = computed(() => {
  return Object.entries(props.groupedEvents).map(([day, events]) => ({
    day,
    events: events as ActivityEventData[],
    hasDailyBrief: (events as ActivityEventData[]).some(e => e.event_type === 'daily_brief'),
  }))
})

function filterByBrand(slug: string | null) {
  activeBrand.value = slug ?? undefined
  const params: Record<string, string> = {}
  if (slug) params.brand = slug
  router.get('/activity', params, { preserveScroll: true, preserveState: true, replace: true })
}

function clearFilter() {
  activeBrand.value = undefined
  router.get('/activity', {}, { preserveScroll: true, preserveState: true, replace: true })
}

function toggleEvent(id: number) {
  const newSet = new Set(expandedEvents.value)
  if (newSet.has(id)) newSet.delete(id)
  else newSet.add(id)
  expandedEvents.value = newSet
}

function toggleThread(eventId: number) {
  const newSet = new Set(openThreads.value)
  if (newSet.has(eventId)) newSet.delete(eventId)
  else newSet.add(eventId)
  openThreads.value = newSet
}

function toggleDay(day: string) {
  const newSet = new Set(expandedDays.value)
  if (newSet.has(day)) newSet.delete(day)
  else newSet.add(day)
  expandedDays.value = newSet
}

function isExpanded(id: number): boolean {
  return expandedEvents.value.has(id)
}

function threadOpen(eventId: number): boolean {
  return openThreads.value.has(eventId)
}

function severityColor(severity: string): string {
  switch (severity) {
    case 'success': return 'bg-green-500'
    case 'warning': return 'bg-amber-400'
    case 'error': return 'bg-red-500'
    default: return 'bg-gray-400'
  }
}

function severityBorder(severity: string): string {
  switch (severity) {
    case 'success': return 'border-l-green-400'
    case 'warning': return 'border-l-amber-400'
    case 'error': return 'border-l-red-400'
    default: return 'border-l-gray-300'
  }
}

function brandColor(brand: BrandInfo | null): string {
  return brand?.color || '#6b7280'
}

function sourceLabel(source: string): string {
  const parts = source.split('.')
  if (parts.length >= 2 && parts[0] === 'laravel') return 'System'
  if (parts.length >= 2) return parts[1]?.charAt(0).toUpperCase() + parts[1]?.slice(1) || 'System'
  return source
}

function isDailyBrief(event: ActivityEventData): boolean {
  return event.event_type === 'daily_brief'
}

function formatTimestamp(isoStr: string): string {
  const d = new Date(isoStr)
  const now = new Date()
  const diffMs = now.getTime() - d.getTime()
  const diffMin = Math.floor(diffMs / 60000)
  const diffHrs = Math.floor(diffMs / 3600000)
  const diffDays = Math.floor(diffMs / 86400000)
  
  if (diffMin < 1) return 'just now'
  if (diffMin === 1) return '1 Minute Ago'
  if (diffMin < 60) return `${diffMin} Minutes Ago`
  if (diffHrs === 1) return '1h'
  if (diffHrs < 24) return `${diffHrs}h`
  if (diffDays === 1) return 'Yesterday'
  if (diffDays < 7) return `${diffDays} days ago`
  
  // Older: show date
  const sameYear = d.getFullYear() === now.getFullYear()
  if (sameYear) {
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  }
  // Different year: include year
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

const commentCounts = computed(() => {
  const counts: Record<number, number> = {}
  for (const events of Object.values(props.groupedEvents)) {
    for (const ev of events) {
      counts[ev.id] = ev.comments_count ?? 0
    }
  }
  return counts
})

function getComments(event: ActivityEventData): CommentData[] {
  return (event as any).comments ?? []
}

function getCommentCount(event: ActivityEventData): number {
  return (event as any).comments_count ?? (event as any).comments?.length ?? 0
}

async function postComment(eventId: number) {
  const body = commentTexts.value[eventId]?.trim()
  if (!body) return

  sendingComments.value[eventId] = true
  const isInstruction = toggleInstructions.value[eventId] ?? false

  try {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? ''
    const res = await fetch(`/activity/${eventId}/comments`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': token,
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
      body: JSON.stringify({ body, is_instruction: isInstruction }),
    })

    if (res.ok) {
      const data = await res.json()
      // Reload to show new comment
      router.reload({ only: ['groupedEvents'], preserveScroll: true, preserveState: true })
      commentTexts.value[eventId] = ''
      toggleInstructions.value[eventId] = false
    }
  } catch {
    // silently fail
  } finally {
    sendingComments.value[eventId] = false
  }
}

// Poll for new events
async function checkForNewEvents() {
  try {
    const params = new URLSearchParams({ since: String(latestKnownId.value) })
    if (activeBrand.value) params.set('brand', activeBrand.value)
    const res = await fetch(`/activity/poll?${params.toString()}`)
    const data = await res.json()
    if (data.new_count > 0) {
      newEventCount.value = data.new_count
      latestKnownId.value = data.latest_id
    }
  } catch {
    // silently ignore poll errors
  }
}

function loadNewEvents() {
  router.reload({ only: ['groupedEvents', 'latestId'], preserveScroll: true })
  newEventCount.value = 0
}

async function loadMore() {
  const allEvents = Object.values(props.groupedEvents).flat()
  const oldest = allEvents[allEvents.length - 1]
  if (!oldest) return

  loadingMore.value = true
  try {
    const params = new URLSearchParams({ before: String(oldest.id) })
    if (activeBrand.value) params.set('brand', activeBrand.value)
    const res = await fetch(`/activity/load-more?${params.toString()}`)
    const data = await res.json()

    if (data.events?.length > 0) {
      const newGrouped: Record<string, ActivityEventData[]> = {}
      for (const event of data.events) {
        const d = new Date(event.created_at)
        const dayKey = isToday(d) ? 'Today' : isYesterday(d) ? 'Yesterday' : d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
        if (!newGrouped[dayKey]) newGrouped[dayKey] = []
        newGrouped[dayKey].push(event)
      }
      for (const [day, events] of Object.entries(newGrouped)) {
        if (props.groupedEvents[day]) {
          props.groupedEvents[day] = [...props.groupedEvents[day], ...events]
        } else {
          props.groupedEvents[day] = events
        }
      }
    }
  } catch {
    // silently ignore
  }
  loadingMore.value = false
}

function isToday(d: Date): boolean {
  const now = new Date()
  return d.getDate() === now.getDate() && d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear()
}

function isYesterday(d: Date): boolean {
  const yesterday = new Date()
  yesterday.setDate(yesterday.getDate() - 1)
  return d.getDate() === yesterday.getDate() && d.getMonth() === yesterday.getMonth() && d.getFullYear() === yesterday.getFullYear()
}

onMounted(() => {
  pollInterval = setInterval(checkForNewEvents, 25000)
})

onUnmounted(() => {
  if (pollInterval) clearInterval(pollInterval)
})
</script>

<template>
  <Head title="Activity Feed" />

  <div class="pb-16">
    <!-- Page header -->
    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
      <div class="flex items-center gap-2">
        <Activity class="h-4 w-4 text-gray-400" />
        <h1 class="text-sm font-semibold text-gray-900">Activity Feed</h1>
      </div>
      <div class="text-[10px] text-gray-400">
        <Clock class="mr-1 inline h-3 w-3" />
        Updates every ~25s
      </div>
    </div>

    <!-- Brand filter pills -->
    <div class="flex flex-wrap items-center gap-1.5 border-b border-gray-200 px-4 py-2.5">
      <button
        class="rounded-full px-3 py-1 text-xs font-medium transition-colors"
        :class="!activeBrand ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
        @click="clearFilter"
      >All</button>
      <button
        v-for="brand in brands"
        :key="brand.slug"
        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition-colors"
        :class="activeBrand === brand.slug ? 'text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
        :style="activeBrand === brand.slug ? { backgroundColor: brand.color || '#3b82f6' } : {}"
        @click="filterByBrand(brand.slug)"
      >
        <span v-if="brand.color" class="h-2 w-2 rounded-full" :style="{ backgroundColor: activeBrand === brand.slug ? 'white' : brand.color }" />
        {{ brand.name }}
      </button>
    </div>

    <!-- New events banner -->
    <Transition name="slide-down">
      <button
        v-if="newEventCount > 0"
        class="flex w-full items-center justify-center gap-2 bg-blue-50 px-4 py-2 text-xs font-medium text-blue-700 hover:bg-blue-100"
        @click="loadNewEvents"
      >
        <Activity class="h-3.5 w-3.5" />
        {{ newEventCount }} new {{ newEventCount === 1 ? 'event' : 'events' }} — click to load
      </button>
    </Transition>

    <!-- Feed -->
    <div v-if="dayGroups.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
      <Activity class="mb-3 h-10 w-10 text-gray-300" />
      <p class="text-sm font-medium text-gray-600">All quiet</p>
      <p class="mt-1 text-xs text-gray-400">No activity in the last 24h</p>
    </div>

    <div v-else class="divide-y divide-gray-100">
      <div v-for="group in dayGroups" :key="group.day">
        <!-- Day header -->
        <div class="sticky top-0 z-10 flex cursor-pointer items-center gap-2 border-b border-gray-100 bg-white px-4 py-2" @click="toggleDay(group.day)">
          <component :is="expandedDays.has(group.day) ? ChevronDown : ChevronRight" class="h-3.5 w-3.5 text-gray-400" />
          <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ group.day }}</span>
          <span class="text-[10px] text-gray-400">{{ group.events.length }}</span>
        </div>

        <Transition name="fade">
          <div v-if="expandedDays.has(group.day)">
            <div
              v-for="event in group.events"
              :key="event.id"
              v-show="!isDailyBrief(event) || expandedDays.has(group.day)"
            >
              <!-- Daily brief card -->
              <div v-if="isDailyBrief(event)" class="border-l-4 border-blue-400 bg-blue-50/50 px-4 py-3">
                <div class="flex items-start gap-3">
                  <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-100">
                    <span class="text-[10px] font-bold text-blue-600">📌</span>
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                      <span class="text-xs font-semibold text-blue-800">{{ event.title }}</span>
                      <span class="text-[10px] text-blue-500">{{ formatTimestamp(event.created_at) }}</span>
                    </div>
                    <div v-if="event.body" class="mt-1 whitespace-pre-wrap text-xs leading-relaxed text-gray-700">{{ event.body }}</div>
                  </div>
                </div>
              </div>

              <!-- Regular event card -->
              <div v-else class="border-l-2 px-4 py-2 transition-colors hover:bg-gray-50/50" :class="severityBorder(event.severity)">
                <!-- Clickable header area -->
                <div class="flex items-start gap-3 cursor-pointer" @click="toggleEvent(event.id)">
                  <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white" :style="{ backgroundColor: brandColor(event.brand) }">
                    {{ event.brand ? event.brand.name.charAt(0) : 'S' }}
                  </div>

                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2 text-[10px]">
                      <span class="font-medium text-gray-700">{{ sourceLabel(event.source) }}</span>
                      <span v-if="event.brand" class="text-gray-400">· {{ event.brand.name }}</span>
                      <span class="text-gray-400">{{ formatTimestamp(event.created_at) }}</span>
                      <span
                        class="ml-auto inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[9px] font-medium"
                        :class="{
                          'bg-green-100 text-green-700': event.severity === 'success',
                          'bg-amber-100 text-amber-700': event.severity === 'warning',
                          'bg-red-100 text-red-700': event.severity === 'error',
                          'bg-gray-100 text-gray-500': event.severity === 'info',
                        }"
                      >{{ event.severity }}</span>
                    </div>
                    <p class="mt-0.5 text-sm font-medium text-gray-900">{{ event.title }}</p>

                    <!-- Expanded body + metadata -->
                    <Transition name="fade">
                      <div v-if="isExpanded(event.id)" class="mt-2 space-y-1.5">
                        <div v-if="event.body" class="whitespace-pre-wrap rounded-md bg-gray-50 px-3 py-2 text-xs leading-relaxed text-gray-600">{{ event.body }}</div>
                        <div v-if="event.metadata && Object.keys(event.metadata).length > 0" class="rounded-md bg-gray-50 px-3 py-2 font-mono text-[10px] text-gray-500">
                          <div v-for="(val, key) in event.metadata" :key="key" class="flex gap-2">
                            <span class="font-medium text-gray-600">{{ key }}:</span>
                            <span>{{ typeof val === 'object' ? JSON.stringify(val) : val }}</span>
                          </div>
                        </div>
                        <div class="text-[10px] text-gray-400">Source: {{ event.source }}</div>
                      </div>
                    </Transition>
                  </div>

                  <!-- Comment count + expand indicator -->
                  <div class="flex shrink-0 flex-col items-center gap-1">
                    <!-- Comment count button -->
                    <button
                      class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-[10px] transition-colors"
                      :class="getCommentCount(event) > 0 ? 'text-gray-500 hover:bg-gray-100' : 'text-gray-300 hover:text-gray-500'"
                      @click.stop="toggleThread(event.id)"
                      :title="getCommentCount(event) > 0 ? `${getCommentCount(event)} comments` : 'Reply'"
                    >
                      <MessageSquare class="h-3 w-3" />
                      <span v-if="getCommentCount(event) > 0">{{ getCommentCount(event) }}</span>
                      <span v-else class="text-[9px]">Reply</span>
                    </button>
                    <ChevronRight
                      class="h-3.5 w-3.5 text-gray-300 transition-transform"
                      :class="{ 'rotate-90': isExpanded(event.id) }"
                    />
                  </div>
                </div>

                <!-- Comment thread (expanded below card) -->
                <Transition name="fade">
                  <div v-if="threadOpen(event.id)" class="ml-10 mt-2 border-l-2 border-gray-200 pl-4">
                    <!-- Thread header -->
                    <div class="mb-2 text-[10px] text-gray-400 italic">
                      Replying to "{{ event.title.length > 60 ? event.title.substring(0, 60) + '...' : event.title }}"
                    </div>

                    <!-- Comments -->
                    <div v-if="getComments(event).length === 0 && !getCommentCount(event)" class="mb-2 text-[10px] text-gray-400">No comments yet</div>
                    <div v-for="comment in getComments(event)" :key="comment.id" class="mb-2 rounded-lg border border-gray-100 px-3 py-2">
                      <div class="flex items-center gap-2">
                        <!-- Author chip -->
                        <span
                          class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium"
                          :class="comment.author === 'hermes'
                            ? 'bg-purple-100 text-purple-700'
                            : comment.author === 'agent'
                              ? 'bg-amber-100 text-amber-700'
                              : 'bg-gray-100 text-gray-700'"
                        >
                          <Bot v-if="comment.author === 'hermes' || comment.author === 'agent'" class="h-3 w-3" />
                          {{ comment.author === 'human' ? 'Sam' : comment.author === 'hermes' ? 'Hermes' : 'Agent' }}
                        </span>
                        <span class="text-[10px] text-gray-400">{{ formatTimestamp(comment.created_at) }}</span>

                        <!-- Instruction status pill -->
                        <span v-if="comment.is_instruction" class="inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[9px] font-medium"
                          :class="comment.instruction_status === 'pending'
                            ? 'bg-amber-100 text-amber-700'
                            : comment.instruction_status === 'acknowledged'
                              ? 'bg-blue-100 text-blue-700'
                              : 'bg-green-100 text-green-700'"
                        >
                          <Flag v-if="comment.instruction_status === 'pending'" class="h-2.5 w-2.5" />
                          <CheckCircle v-else class="h-2.5 w-2.5" />
                          {{ comment.instruction_status === 'pending' ? '→ sent to Hermes ● pending' : comment.instruction_status }}
                        </span>
                      </div>
                      <div class="mt-1 text-xs leading-relaxed text-gray-700">{{ comment.body }}</div>
                      <div v-if="comment.metadata && Object.keys(comment.metadata).length > 0" class="mt-1">
                        <details class="text-[10px] text-gray-400">
                          <summary class="cursor-pointer hover:text-gray-600">details</summary>
                          <pre class="mt-1 rounded bg-gray-50 p-1.5 font-mono text-[9px]">{{ JSON.stringify(comment.metadata, null, 2) }}</pre>
                        </details>
                      </div>
                    </div>

                    <!-- Composer -->
                    <div class="mt-2 flex items-end gap-2">
                      <div class="flex-1">
                        <textarea
                          v-model="commentTexts[event.id]"
                          rows="2"
                          placeholder="Write a reply..."
                          class="w-full rounded-lg border border-gray-200 px-3 py-2 text-xs outline-none focus:border-blue-300 focus:ring-1 focus:ring-blue-200 resize-none"
                          @keydown.meta.enter="postComment(event.id)"
                          @keydown.ctrl.enter="postComment(event.id)"
                        ></textarea>
                        <label class="mt-1 flex items-center gap-1.5 text-[10px] text-gray-400 cursor-pointer hover:text-gray-600">
                          <input
                            type="checkbox"
                            v-model="toggleInstructions[event.id]"
                            class="h-3 w-3 rounded border-gray-300"
                          />
                          <Flag class="h-3 w-3" />
                          Send to Hermes — flag as instruction
                        </label>
                      </div>
                      <button
                        @click="postComment(event.id)"
                        :disabled="!commentTexts[event.id]?.trim() || sendingComments[event.id]"
                        class="inline-flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-40"
                      >
                        <Send v-if="!sendingComments[event.id]" class="h-3.5 w-3.5" />
                        <Loader v-else class="h-3.5 w-3.5 animate-spin" />
                        Post
                      </button>
                    </div>
                  </div>
                </Transition>
              </div>
            </div>
          </div>
        </Transition>
      </div>

      <!-- Load more -->
      <div class="px-4 py-4 text-center">
        <button
          class="rounded-md bg-white px-4 py-2 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 disabled:opacity-50"
          :disabled="loadingMore"
          @click="loadMore"
        >{{ loadingMore ? 'Loading...' : 'Load more' }}</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.slide-down-enter-active, .slide-down-leave-active { transition: all 0.2s ease; }
.slide-down-enter-from, .slide-down-leave-to { opacity: 0; transform: translateY(-100%); }
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>