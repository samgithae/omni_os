<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { Search, X } from '@lucide/vue'
import { ref, watch } from 'vue'

interface BrandInfo {
  id: number
  name: string
  slug: string
  color: string | null
}

const props = defineProps<{
  brands: BrandInfo[]
  cities: string[]
  currentFilters: Record<string, string | undefined>
}>()

const searchInput = ref(props.currentFilters.search || '')

let debounceTimer: ReturnType<typeof setTimeout> | null = null

watch(searchInput, (val) => {
  if (debounceTimer) clearTimeout(debounceTimer)
  debounceTimer = setTimeout(() => {
    applyFilter('search', val)
  }, 300)
})

function applyFilter(key: string, value: string | null) {
  const params = new URLSearchParams(window.location.search)
  if (value) {
    params.set(key, value)
  } else {
    params.delete(key)
  }
  params.delete('page')
  const qs = params.toString()
  const url = qs ? `/leads?${qs}` : '/leads'
  router.get(url, {}, { preserveScroll: true, preserveState: true, replace: true })
}

function clearFilters() {
  router.get('/leads', {}, { preserveScroll: true, preserveState: true, replace: true })
}

const hasActiveFilters = Object.values(props.currentFilters).some(v => v && v !== 'score' && v !== 'desc')
</script>

<template>
  <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 px-4 py-2">
    <!-- Segment dropdown -->
    <select
      class="rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-sm outline-none focus:border-blue-300 focus:ring-1 focus:ring-blue-200"
      :value="currentFilters.segment || ''"
      @change="applyFilter('segment', ($event.target as HTMLSelectElement).value || null)"
    >
      <option value="">All Segments</option>
      <option value="rabbit">Rabbit</option>
      <option value="deer">Deer</option>
      <option value="mouse">Mouse</option>
      <option value="elephant">Elephant</option>
    </select>

    <!-- Status dropdown -->
    <select
      class="rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-sm outline-none focus:border-blue-300 focus:ring-1 focus:ring-blue-200"
      :value="currentFilters.status || ''"
      @change="applyFilter('status', ($event.target as HTMLSelectElement).value || null)"
    >
      <option value="">All Status</option>
      <option value="new">New</option>
      <option value="enriching">Enriching</option>
      <option value="enriched">Enriched</option>
      <option value="no_email_found">No Email Found</option>
      <option value="emailed">Emailed</option>
      <option value="replied">Replied</option>
      <option value="interested">Interested</option>
      <option value="not_interested">Not Interested</option>
      <option value="suppressed">Suppressed</option>
    </select>

    <!-- Score tier dropdown -->
    <select
      class="rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-sm outline-none focus:border-blue-300 focus:ring-1 focus:ring-blue-200"
      :value="currentFilters.tier || ''"
      @change="applyFilter('tier', ($event.target as HTMLSelectElement).value || null)"
    >
      <option value="">All Tiers</option>
      <option value="hot">Hot (80+)</option>
      <option value="warm">Warm (60-79)</option>
      <option value="moderate">Moderate (40-59)</option>
      <option value="cold">Cold (20-39)</option>
      <option value="frigid">Frigid (&lt;20)</option>
    </select>

    <!-- City dropdown -->
    <select
      class="rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-sm outline-none focus:border-blue-300 focus:ring-1 focus:ring-blue-200"
      :value="currentFilters.city || ''"
      @change="applyFilter('city', ($event.target as HTMLSelectElement).value || null)"
    >
      <option value="">All Cities</option>
      <option v-for="c in cities" :key="c" :value="c">{{ c }}</option>
    </select>

    <!-- Has email dropdown -->
    <select
      class="rounded-md border border-gray-200 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-sm outline-none focus:border-blue-300 focus:ring-1 focus:ring-blue-200"
      :value="currentFilters.has_email || ''"
      @change="applyFilter('has_email', ($event.target as HTMLSelectElement).value || null)"
    >
      <option value="">Email: Any</option>
      <option value="yes">Has Email</option>
      <option value="no">No Email</option>
    </select>

    <!-- Search input -->
    <div class="relative ml-auto min-w-[180px]">
      <Search class="pointer-events-none absolute left-2 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-gray-400" />
      <input
        v-model="searchInput"
        type="text"
        placeholder="Search company, email, category..."
        class="w-full rounded-md border border-gray-200 py-1.5 pl-7 pr-2 text-xs outline-none placeholder:text-gray-400 focus:border-blue-300 focus:ring-1 focus:ring-blue-200"
      />
    </div>

    <!-- Clear filters -->
    <button
      v-if="hasActiveFilters"
      class="flex items-center gap-1 rounded-md px-2 py-1.5 text-xs font-medium text-gray-500 hover:bg-gray-100"
      @click="clearFilters"
    >
      <X class="h-3 w-3" />
      Clear
    </button>
  </div>
</template>