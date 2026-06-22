<script setup lang="ts">
import { ref, computed } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import { Mail, LayoutGrid } from '@lucide/vue'
import { dashboard } from '@/routes'
import StatsBar from './components/StatsBar.vue'
import FilterBar from './components/FilterBar.vue'
import LeadSequenceRow from './components/LeadSequenceRow.vue'
import BulkActionBar from './components/BulkActionBar.vue'
import type { StepInfo } from './components/SequenceTimeline.vue'

defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Dashboard', href: dashboard() },
      { title: 'Email Sequences', href: '/email-sequences' },
    ],
  },
})

interface BrandInfo {
  id: number
  name: string
  slug: string
  color: string | null
}

interface LeadData {
  id: number
  company_name: string
  email: string | null
  contact_name: string | null
  segment: string
  city: string | null
  brand: BrandInfo | null
  steps: StepInfo[]
  has_pending: boolean
  sequence_complete: boolean | null
  missing_steps: number[]
}

interface EmailStats {
  total: number
  needs_content: number
  pending: number
  approved: number
  rejected: number
  sent: number
  opened: number
  clicked: number
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
  leads: PaginatedData<LeadData>
  stats: EmailStats
  filters: Record<string, string | undefined>
  brands: BrandInfo[]
}>()

const selectedIds = ref<Set<number>>(new Set())

const selectedCount = computed(() => selectedIds.value.size)
const hasPendingSelected = computed(() => {
  return props.leads.data.some(
    lead => selectedIds.value.has(lead.id) && lead.has_pending,
  )
})

const activeStatFilter = ref<string | undefined>(undefined)

function toggleSelect(id: number) {
  const newSet = new Set(selectedIds.value)
  if (newSet.has(id)) {
    newSet.delete(id)
  } else {
    newSet.add(id)
  }
  selectedIds.value = newSet
}

function clearSelection() {
  selectedIds.value = new Set()
}

function handleApproveAll() {
  if (selectedIds.value.size === 0) return
  router.post('/email-sequences/approve', { lead_ids: Array.from(selectedIds.value) }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      selectedIds.value = new Set()
    },
  })
}

function handleRejectAll() {
  if (selectedIds.value.size === 0) return
  router.post('/email-sequences/reject', { lead_ids: Array.from(selectedIds.value) }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      selectedIds.value = new Set()
    },
  })
}

function handleStatFilter(statKey: string) {
  if (statKey === 'total') {
    // Clear filters
    router.get('/email-sequences', {}, { preserveScroll: true, preserveState: true, replace: true })
    activeStatFilter.value = undefined
    return
  }
  // Map stat key to approval status filter
  const approvalMap: Record<string, string> = {
    pending: 'pending',
    approved: 'approved',
    rejected: 'rejected',
  }
  const filterVal = approvalMap[statKey]
  if (filterVal) {
    activeStatFilter.value = statKey
    router.get(`/email-sequences?approval=${filterVal}`, {}, { preserveScroll: true, preserveState: true, replace: true })
  }
}

function refreshAfterAction() {
  selectedIds.value = new Set()
}

// Pagination
function goToPage(page: number) {
  const params = new URLSearchParams(window.location.search)
  params.set('page', String(page))
  router.get(`/email-sequences?${params.toString()}`, {}, { preserveScroll: true, preserveState: true, replace: true })
}
</script>

<template>
  <Head title="Email Sequences" />

  <div class="pb-16">
    <!-- Page header -->
    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
      <div class="flex items-center gap-2">
        <Mail class="h-4 w-4 text-gray-400" />
        <h1 class="text-sm font-semibold text-gray-900">Email Sequences</h1>
        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-500">
          {{ stats.total }} total
        </span>
      </div>
      <a
        href="/admin/email-messages"
        class="text-xs text-gray-500 underline-offset-2 hover:text-gray-700 hover:underline"
        target="_blank"
      >
        Full admin table →
      </a>
    </div>

    <!-- Stats bar -->
    <StatsBar
      :stats="stats"
      :active-filter="activeStatFilter"
      @filter-by-stat="handleStatFilter"
    />

    <!-- Filter bar -->
    <FilterBar
      :brands="brands"
      :current-filters="filters"
    />

    <!-- Lead sequence list -->
    <div>
      <!-- Column headers (subtle) -->
      <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-1.5 text-[10px] font-medium uppercase tracking-wider text-gray-400">
        <div class="w-4" /> <!-- checkbox space -->
        <div class="flex-1">Lead</div>
        <div class="shrink-0 px-2">Sequence</div>
        <div class="shrink-0 text-right">Progress</div>
        <div class="shrink-0 pr-2">Actions</div>
      </div>

      <!-- Leads -->
      <div v-if="leads.data.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
        <Mail class="mb-3 h-10 w-10 text-gray-300" />
        <p class="text-sm font-medium text-gray-600">No email sequences found</p>
        <p class="mt-1 text-xs text-gray-400">
          Try adjusting your filters or import email sequences first.
        </p>
      </div>

      <div v-else>
        <LeadSequenceRow
          v-for="lead in leads.data"
          :key="lead.id"
          :lead="lead"
          :selected="selectedIds.has(lead.id)"
          @toggle-select="toggleSelect"
          @approved="refreshAfterAction"
        />
      </div>
    </div>

    <!-- Pagination -->
    <div
      v-if="leads.last_page > 1"
      class="flex items-center justify-between border-t border-gray-100 px-4 py-3"
    >
      <span class="text-xs text-gray-500">
        Showing {{ leads.from }}–{{ leads.to }} of {{ leads.total }}
      </span>
      <div class="flex items-center gap-1">
        <button
          v-for="link in leads.links"
          :key="link.label"
          class="rounded px-2.5 py-1 text-xs font-medium transition-colors"
          :class="{
            'bg-blue-600 text-white': link.active,
            'text-gray-600 hover:bg-gray-100': !link.active && link.url,
            'text-gray-300': !link.url,
          }"
          :disabled="!link.url"
          @click="link.url && goToPage(Number(link.label))"
          v-html="link.label"
        />
      </div>
    </div>
  </div>

  <!-- Bulk action bar -->
  <BulkActionBar
    :selected-count="selectedCount"
    :has-pending="hasPendingSelected"
    @clear-selection="clearSelection"
    @approve-all="handleApproveAll"
    @reject-all="handleRejectAll"
  />
</template>
