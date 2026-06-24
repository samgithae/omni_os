<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import { Clock, Pencil, Trash2 } from '@lucide/vue'
import { dashboard } from '@/routes'

defineOptions({
  layout: {
    breadcrumbs: [
      { title: 'Dashboard', href: dashboard() },
      { title: 'Sequence Schedules', href: '/sequence-schedules' },
    ],
  },
})

interface BrandInfo {
  id: number
  name: string
  slug: string
}

interface ScheduleData {
  id: number
  brand_id: number
  brand: BrandInfo | null
  segment: string
  step: number
  days_after_previous: number
  purpose: string | null
  is_active: boolean
}

interface PaginatedData<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

const props = defineProps<{
  schedules: PaginatedData<ScheduleData>
  brands: BrandInfo[]
  filters: Record<string, string | undefined>
}>()

const editingInline = ref<Record<number, boolean>>({})

function toggleInlineEdit(id: number) {
  editingInline.value = { ...editingInline.value, [id]: !editingInline.value[id] }
}

function saveInline(item: ScheduleData) {
  router.put(`/sequence-schedules/${item.id}`, {
    days_after_previous: item.days_after_previous,
    purpose: item.purpose,
    is_active: item.is_active,
  }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      editingInline.value = { ...editingInline.value, [item.id]: false }
    },
  })
}

function confirmDelete(id: number) {
  if (confirm('Delete this schedule entry?')) {
    router.delete(`/sequence-schedules/${id}`, {
      preserveScroll: true,
      preserveState: true,
    })
  }
}

function goToPage(page: number) {
  const params = new URLSearchParams(window.location.search)
  params.set('page', String(page))
  router.get(`/sequence-schedules?${params.toString()}`, { preserveScroll: true, preserveState: true, replace: true })
}

function applyFilter(key: string, value: string) {
  const params = new URLSearchParams(window.location.search)
  if (value) params.set(key, value)
  else params.delete(key)
  params.delete('page')
  router.get(`/sequence-schedules?${params.toString()}`, { preserveScroll: true, preserveState: true, replace: true })
}

function segmentColor(seg: string): string {
  return seg === 'rabbit' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'
}
</script>

<template>
  <Head title="Sequence Schedules" />

  <div>
    <div class="flex items-center border-b border-gray-200 px-4 py-3">
      <Clock class="mr-2 h-4 w-4 text-gray-400" />
      <h1 class="text-sm font-semibold text-gray-900">Sequence Schedules</h1>
      <span class="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-500">{{ schedules.total }}</span>
    </div>

    <!-- Filters -->
    <div class="flex items-center gap-2 border-b border-gray-100 px-4 py-2">
      <select class="rounded border-gray-200 text-xs text-gray-600" :value="filters.segment || ''" @change="applyFilter('segment', ($event.target as HTMLSelectElement).value)">
        <option value="">All Segments</option>
        <option value="rabbit">Rabbit</option>
        <option value="deer">Deer</option>
      </select>
    </div>

    <!-- Table -->
    <div v-if="schedules.data.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
      <Clock class="mb-3 h-10 w-10 text-gray-300" />
      <p class="text-sm font-medium text-gray-600">No schedules found</p>
    </div>

    <div v-else class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-gray-100 text-[10px] font-medium uppercase tracking-wider text-gray-400">
            <th class="px-4 py-2">Brand</th>
            <th class="px-4 py-2">Segment</th>
            <th class="px-4 py-2">Step</th>
            <th class="px-4 py-2">Days Gap</th>
            <th class="px-4 py-2">Purpose</th>
            <th class="px-4 py-2">Active</th>
            <th class="px-4 py-2">Actions</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in schedules.data" :key="s.id" class="border-b border-gray-50 hover:bg-gray-50/50">
            <td class="px-4 py-2.5 font-medium text-gray-900">{{ s.brand?.name || '-' }}</td>
            <td class="px-4 py-2.5">
              <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium" :class="segmentColor(s.segment)">{{ s.segment }}</span>
            </td>
            <td class="px-4 py-2.5 text-gray-600">{{ s.step }}</td>
            <td class="px-4 py-2.5">
              <input v-if="editingInline[s.id]" type="number" v-model.number="s.days_after_previous" min="0" class="w-16 rounded border-gray-200 text-xs" />
              <span v-else class="text-gray-600">{{ s.days_after_previous }}</span>
            </td>
            <td class="max-w-[200px] truncate px-4 py-2.5 text-gray-500">
              <input v-if="editingInline[s.id]" v-model="s.purpose" class="w-full rounded border-gray-200 text-xs" />
              <span v-else class="text-gray-500">{{ s.purpose || '-' }}</span>
            </td>
            <td class="px-4 py-2.5">
              <input v-if="editingInline[s.id]" type="checkbox" v-model="s.is_active" class="rounded border-gray-300 text-blue-600" />
              <span v-else :class="s.is_active ? 'text-green-600' : 'text-gray-400'" class="text-xs">{{ s.is_active ? 'Yes' : 'No' }}</span>
            </td>
            <td class="px-4 py-2.5">
              <div class="flex items-center gap-1">
                <button v-if="!editingInline[s.id]" class="rounded p-1 text-gray-400 hover:text-blue-600 hover:bg-blue-50" title="Edit inline" @click="toggleInlineEdit(s.id)">
                  <Pencil class="h-3.5 w-3.5" />
                </button>
                <template v-if="editingInline[s.id]">
                  <button class="rounded bg-blue-600 px-2 py-0.5 text-[10px] font-medium text-white" @click="saveInline(s)">Save</button>
                  <button class="rounded px-2 py-0.5 text-[10px] font-medium text-gray-600 ring-1 ring-inset ring-gray-300" @click="toggleInlineEdit(s.id)">Cancel</button>
                </template>
                <button class="rounded p-1 text-gray-400 hover:text-red-600 hover:bg-red-50" title="Delete" @click="confirmDelete(s.id)">
                  <Trash2 class="h-3.5 w-3.5" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div v-if="schedules.last_page > 1" class="flex items-center justify-between border-t border-gray-100 px-4 py-3">
      <span class="text-xs text-gray-500">Page {{ schedules.current_page }} of {{ schedules.last_page }}</span>
      <div class="flex items-center gap-1">
        <button v-for="page in schedules.last_page" :key="page" class="rounded px-2.5 py-1 text-xs font-medium"
          :class="{ 'bg-blue-600 text-white': page === schedules.current_page, 'text-gray-600 hover:bg-gray-100': page !== schedules.current_page }"
          @click="goToPage(page)">{{ page }}</button>
      </div>
    </div>
  </div>
</template>
