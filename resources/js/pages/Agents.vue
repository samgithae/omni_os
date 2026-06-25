<script setup lang="ts">
import { ref } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import { Bot, Activity, ChevronDown, ChevronRight, FileText, ExternalLink, Clock, Circle } from '@lucide/vue'
import { dashboard } from '@/routes'

defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Dashboard', href: dashboard() },
      { title: 'Agents', href: '/agents' },
    ],
  },
})

interface AgentDocument {
  id: number
  label: string
  url: string | null
  mime_type: string | null
  size_bytes: number | null
}

interface RecentEvent {
  id: number
  title: string
  event_type: string
  severity: string
  brand: { name: string; slug: string; color: string } | null
  created_at: string
  relative_time: string
}

interface AgentData {
  id: number
  codename: string
  display_name: string
  role: string | null
  description: string | null
  avatar_url: string | null
  function_area: string | null
  status: string
  is_active: boolean
  last_active_at: string | null
  actions_this_week: number
  recent_events: RecentEvent[]
  documents: AgentDocument[]
}

const props = defineProps<{
  agents: AgentData[]
}>()

const expandedAgents = ref<Set<number>>(new Set())

function toggleExpand(id: number) {
  const newSet = new Set(expandedAgents.value)
  if (newSet.has(id)) newSet.delete(id)
  else newSet.add(id)
  expandedAgents.value = newSet
}

function isExpanded(id: number): boolean {
  return expandedAgents.value.has(id)
}

function functionAreaColor(area: string | null): string {
  switch (area) {
    case 'orchestration': return 'bg-indigo-100 text-indigo-700'
    case 'mining': return 'bg-amber-100 text-amber-700'
    case 'enrichment': return 'bg-blue-100 text-blue-700'
    case 'drafting': return 'bg-green-100 text-green-700'
    case 'triage': return 'bg-gray-100 text-gray-700'
    case 'research': return 'bg-red-100 text-red-700'
    default: return 'bg-gray-100 text-gray-500'
  }
}

function severityColor(severity: string): string {
  switch (severity) {
    case 'success': return 'bg-green-500'
    case 'warning': return 'bg-amber-400'
    case 'error': return 'bg-red-500'
    default: return 'bg-gray-400'
  }
}

function statusDot(status: string): string {
  switch (status) {
    case 'active': return 'bg-green-500'
    case 'paused': return 'bg-gray-400'
    case 'maintenance': return 'bg-amber-400'
    default: return 'bg-gray-300'
  }
}

function formatFileSize(bytes: number | null): string {
  if (!bytes) return '—'
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)} KB`
  return `${(bytes / 1048576).toFixed(1)} MB`
}

function statusLabel(status: string): string {
  switch (status) {
    case 'active': return 'Active'
    case 'paused': return 'Paused'
    case 'maintenance': return 'Maintenance'
    default: return status
  }
}
</script>

<template>
  <Head title="Agents" />

  <div class="pb-16">
    <!-- Page header -->
    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
      <div class="flex items-center gap-2">
        <Bot class="h-4 w-4 text-gray-400" />
        <h1 class="text-sm font-semibold text-gray-900">Agent Fleet</h1>
      </div>
      <div class="text-[10px] text-gray-400">
        Roster · {{ agents.length }} agents
      </div>
    </div>

    <!-- Agent cards -->
    <div v-if="agents.length === 0" class="flex flex-col items-center justify-center py-20 text-center">
      <Bot class="mb-3 h-10 w-10 text-gray-300" />
      <p class="text-sm font-medium text-gray-600">No agents registered</p>
      <p class="mt-1 text-xs text-gray-400">Create agents in the admin panel to get started</p>
    </div>

    <div v-else class="divide-y divide-gray-100">
      <div
        v-for="agent in agents"
        :key="agent.id"
        class="border-b border-gray-100"
        :class="{ 'opacity-60': !agent.is_active }"
      >
        <!-- Agent card header (clickable) -->
        <div
          class="flex cursor-pointer items-start gap-3 px-4 py-3 transition-colors hover:bg-gray-50/50"
          @click="toggleExpand(agent.id)"
        >
          <!-- Avatar -->
          <div class="h-10 w-10 shrink-0 overflow-hidden rounded-full bg-gray-200">
            <img
              v-if="agent.avatar_url"
              :src="agent.avatar_url"
              :alt="agent.display_name"
              class="h-full w-full object-cover"
            />
            <div v-else class="flex h-full w-full items-center justify-center text-sm font-bold text-gray-500">
              {{ agent.display_name.charAt(0) }}
            </div>
          </div>

          <!-- Info -->
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="text-sm font-semibold text-gray-900">{{ agent.display_name }}</span>
              <span
                class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-mono text-gray-600"
              >{{ agent.codename }}</span>
              <!-- Paused badge -->
              <span
                v-if="!agent.is_active || agent.status !== 'active'"
                class="inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                :class="agent.status === 'maintenance' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-500'"
              >
                <Circle class="h-2 w-2" :class="agent.status === 'maintenance' ? 'fill-amber-400' : 'fill-gray-400'" />
                {{ statusLabel(agent.status) }}
              </span>
            </div>
            <div class="mt-0.5 flex items-center gap-2 text-[11px] text-gray-500">
              <span v-if="agent.role">{{ agent.role }}</span>
              <span v-if="agent.role && agent.function_area" class="text-gray-300">·</span>
              <span
                v-if="agent.function_area"
                class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[9px] font-medium"
                :class="functionAreaColor(agent.function_area)"
              >{{ agent.function_area }}</span>
            </div>
            <div class="mt-1 flex items-center gap-3 text-[10px] text-gray-400">
              <span class="flex items-center gap-1">
                <Activity class="h-3 w-3" />
                {{ agent.actions_this_week }} this week
              </span>
              <span v-if="agent.last_active_at" class="flex items-center gap-1">
                <Clock class="h-3 w-3" />
                Active {{ agent.last_active_at }}
              </span>
              <span v-else class="text-gray-300">Never active</span>
            </div>
          </div>

          <!-- Expand arrow -->
          <ChevronRight
            class="mt-1 h-4 w-4 shrink-0 text-gray-300 transition-transform"
            :class="{ 'rotate-90': isExpanded(agent.id) }"
          />
        </div>

        <!-- Expanded details -->
        <Transition name="fade">
          <div v-if="isExpanded(agent.id)" class="border-t border-gray-50 bg-gray-50/50 px-4 pb-4 pt-2">
            <!-- Description -->
            <div v-if="agent.description" class="mb-3 rounded-md bg-white px-3 py-2 text-xs leading-relaxed text-gray-600">
              {{ agent.description }}
            </div>

            <!-- Recent events -->
            <div v-if="agent.recent_events.length > 0" class="mb-3">
              <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Recent Activity</p>
              <div class="space-y-1">
                <div
                  v-for="event in agent.recent_events"
                  :key="event.id"
                  class="flex items-center gap-2 rounded-md bg-white px-3 py-1.5 text-[11px]"
                >
                  <span class="h-2 w-2 shrink-0 rounded-full" :class="severityColor(event.severity)"></span>
                  <span class="flex-1 truncate text-gray-700">{{ event.title }}</span>
                  <span v-if="event.brand" class="text-gray-400" :style="{ color: event.brand.color }">{{ event.brand.name }}</span>
                  <span class="shrink-0 text-gray-400">{{ event.relative_time }}</span>
                </div>
              </div>
              <Link
                :href="`/activity?agent=${agent.id}`"
                class="mt-1 inline-flex items-center gap-1 text-[10px] font-medium text-blue-600 hover:text-blue-800"
              >
                View all activity <ExternalLink class="h-2.5 w-2.5" />
              </Link>
            </div>

            <!-- Documents -->
            <div v-if="agent.documents.length > 0">
              <p class="mb-1.5 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Attached Documents</p>
              <div class="space-y-1">
                <a
                  v-for="doc in agent.documents"
                  :key="doc.id"
                  :href="doc.url ?? '#'"
                  target="_blank"
                  class="flex items-center gap-2 rounded-md bg-white px-3 py-1.5 text-[11px] transition-colors hover:bg-blue-50"
                >
                  <FileText class="h-3.5 w-3.5 shrink-0 text-gray-400" />
                  <span class="flex-1 text-gray-700">{{ doc.label }}</span>
                  <span class="text-gray-400">{{ formatFileSize(doc.size_bytes) }}</span>
                </a>
              </div>
            </div>

            <!-- Empty expand state -->
            <div v-if="!agent.description && agent.recent_events.length === 0 && agent.documents.length === 0" class="py-2 text-center text-[11px] text-gray-400">
              No additional details.
            </div>
          </div>
        </Transition>
      </div>
    </div>
  </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
