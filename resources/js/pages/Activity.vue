<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import { Activity, ChevronDown, ChevronRight, Clock } from '@lucide/vue'
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

// Compute day groups from props
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
  if (newSet.has(id)) {
    newSet.delete(id)
  } else {
    newSet.add(id)
  }
  expandedEvents.value = newSet
}

function toggleDay(day: string) {
  const newSet = new Set(expandedDays.value)
  if (newSet.has(day)) {
    newSet.delete(day)
  } else {
    newSet.add(day)
  }
  expandedDays.value = newSet
}

function isExpanded(id: number): boolean {
  return expandedEvents.value.has(id)
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

  if (diffMin < 1) return 'just now'
  if (diffMin < 60) return `${diffMin}m`
  if (diffHrs < 24) return `${diffHrs}h`
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

function brandPillClass(slug: string): string {
  const brand = props.brands.find(b => b.slug === slug)
  if (!brand?.color) return 'bg-gray-100 text-gray-600'
  return 'text-gray-700'
}

function brandPillBgClass(slug: string): string {
  const brand = props.brands.find(b => b.slug === slug)
  if (!brand?.color) return 'bg-gray-100'
  return 'bg-gray-100'
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
      // Group newly loaded events by day
      const newGrouped: Record<string, ActivityEventData[]> = {}
      for (const event of data.events) {
        const d = new Date(event.created_at)
        const dayKey = isToday(d) ? 'Today' : isYesterday(d) ? 'Yesterday' : d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' })
        if (!newGrouped[dayKey]) newGrouped[dayKey] = []
        newGrouped[dayKey].push(event)
      }

      // Merge into groupedEvents
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
      >
        All
      </button>
      <button
        v-for="brand in brands"
        :key="brand.slug"
        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium transition-colors"
        :class="activeBrand === brand.slug ? 'text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
        :style="activeBrand === brand.slug ? { backgroundColor: brand.color || '#3b82f6' } : {}"
        @click="filterByBrand(brand.slug)"
      >
        <span
          v-if="brand.color"
          class="h-2 w-2 rounded-full"
          :style="{ backgroundColor: activeBrand === brand.slug ? 'white' : brand.color }"
        />
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
        <div
          class="sticky top-0 z-10 flex cursor-pointer items-center gap-2 border-b border-gray-100 bg-white px-4 py-2"
          @click="toggleDay(group.day)"
        >
          <component :is="expandedDays.has(group.day) ? ChevronDown : ChevronRight" class="h-3.5 w-3.5 text-gray-400" />
          <span class="text-xs font-semibold uppercase tracking-wider text-gray-500">{{ group.day }}</span>
          <span class="text-[10px] text-gray-400">{{ group.events.length }}</span>
        </div>

        <Transition name="fade">
          <div v-if="expandedDays.has(group.day)">
            <!-- Daily brief (pinned/expanded at top of day) -->
            <div
              v-for="event in group.events"
              :key="event.id"
              v-show="!isDailyBrief(event) || expandedDays.has(group.day)"
            >
              <!-- Daily brief card -->
              <div
                v-if="isDailyBrief(event)"
                class="border-l-4 border-blue-400 bg-blue-50/50 px-4 py-3"
              >
                <div class="flex items-start gap-3">
                  <div class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-blue-100">
                    <span class="text-[10px] font-bold text-blue-600">📌</span>
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                      <span class="text-xs font-semibold text-blue-800">{{ event.title }}</span>
                      <span class="text-[10px] text-blue-500">{{ event.relative_time }}</span>
                    </div>
                    <div v-if="event.body" class="mt-1 whitespace-pre-wrap text-xs leading-relaxed text-gray-700">
                      {{ event.body }}
                    </div>
                    <div v-if="event.metadata" class="mt-1 text-[10px] text-gray-400">
                      {{ JSON.stringify(event.metadata) }}
                    </div>
                  </div>
                </div>
              </div>

              <!-- Regular event card -->
              <div
                v-else
                class="cursor-pointer border-l-2 px-4 py-3 transition-colors hover:bg-gray-50/50"
                :class="severityBorder(event.severity)"
                @click="toggleEvent(event.id)"
              >
                <div class="flex items-start gap-3">
                  <!-- Brand / severity dot -->
                  <div
                    class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-[10px] font-bold text-white"
                    :style="{ backgroundColor: brandColor(event.brand) }"
                  >
                    {{ event.brand ? event.brand.name.charAt(0) : 'S' }}
                  </div>

                  <div class="min-w-0 flex-1">
                    <!-- Meta row -->
                    <div class="flex items-center gap-2 text-[10px]">
                      <span class="font-medium text-gray-700">{{ sourceLabel(event.source) }}</span>
                      <span v-if="event.brand" class="text-gray-400">· {{ event.brand.name }}</span>
                      <span class="text-gray-400">{{ event.relative_time }}</span>
                      <!-- Severity badge -->
                      <span
                        class="ml-auto inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[9px] font-medium"
                        :class="{
                          'bg-green-100 text-green-700': event.severity === 'success',
                          'bg-amber-100 text-amber-700': event.severity === 'warning',
                          'bg-red-100 text-red-700': event.severity === 'error',
                          'bg-gray-100 text-gray-500': event.severity === 'info',
                        }"
                      >
                        {{ event.severity }}
                      </span>
                    </div>

                    <!-- Title -->
                    <p class="mt-0.5 text-sm font-medium text-gray-900">{{ event.title }}</p>

                    <!-- Expanded body + metadata -->
                    <Transition name="fade">
                      <div v-if="isExpanded(event.id)" class="mt-2 space-y-1.5">
                        <div
                          v-if="event.body"
                          class="whitespace-pre-wrap rounded-md bg-gray-50 px-3 py-2 text-xs leading-relaxed text-gray-600"
                        >
                          {{ event.body }}
                        </div>
                        <div
                          v-if="event.metadata && Object.keys(event.metadata).length > 0"
                          class="rounded-md bg-gray-50 px-3 py-2 font-mono text-[10px] text-gray-500"
                        >
                          <div v-for="(val, key) in event.metadata" :key="key" class="flex gap-2">
                            <span class="font-medium text-gray-600">{{ key }}:</span>
                            <span>{{ typeof val === 'object' ? JSON.stringify(val) : val }}</span>
                          </div>
                        </div>
                        <div class="text-[10px] text-gray-400">
                          Source: {{ event.source }}
                        </div>
                      </div>
                    </Transition>
                  </div>

                  <!-- Expand indicator -->
                  <ChevronRight
                    class="mt-0.5 h-3.5 w-3.5 shrink-0 text-gray-300 transition-transform"
                    :class="{ 'rotate-90': isExpanded(event.id) }"
                  />
                </div>
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
        >
          {{ loadingMore ? 'Loading...' : 'Load more' }}
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.slide-down-enter-active,
.slide-down-leave-active {
  transition: all 0.2s ease;
}
.slide-down-enter-from,
.slide-down-leave-to {
  opacity: 0;
  transform: translateY(-100%);
}
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.15s ease;
}
.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
