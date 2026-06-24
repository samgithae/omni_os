<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import { Ban, Plus, Search, Trash2 } from '@lucide/vue'
import { dashboard } from '@/routes'

defineOptions({
  layout: { breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Suppressions', href: '/suppressions' },
  ]},
})

interface BrandInfo { id: number; name: string; slug: string; color: string | null }
interface SuppressionData {
  id: number; brand_id: number; brand: BrandInfo | null
  email: string; reason: string; notes: string | null; created_at: string | null
}
interface PaginatedData<T> { data: T[]; current_page: number; last_page: number; per_page: number; total: number }

const props = defineProps<{
  suppressions: PaginatedData<SuppressionData>
  brands: BrandInfo[]
  filters: Record<string, string | undefined>
}>()

const showAddForm = ref(false)
const form = ref({ brand_id: props.brands[0]?.id || '', email: '', reason: 'manual', notes: '' })
const errors = ref<Record<string, string>>({})

function submitAdd() {
  router.post('/suppressions', form.value, {
    preserveScroll: true, preserveState: true,
    onError: (e) => { errors.value = e as Record<string, string> },
    onSuccess: () => { showAddForm.value = false; form.value = { brand_id: props.brands[0]?.id || '', email: '', reason: 'manual', notes: '' } }
  })
}

function confirmDelete(id: number) {
  if (confirm('Remove this suppression?')) router.delete(`/suppressions/${id}`, { preserveScroll: true, preserveState: true })
}

function applyFilter(key: string, value: string) {
  const params = new URLSearchParams(window.location.search)
  if (value) params.set(key, value); else params.delete(key)
  params.delete('page')
  router.get(`/suppressions?${params.toString()}`, { preserveScroll: true, preserveState: true, replace: true })
}

function goToPage(page: number) {
  const params = new URLSearchParams(window.location.search)
  params.set('page', String(page))
  router.get(`/suppressions?${params.toString()}`, { preserveScroll: true, preserveState: true, replace: true })
}

function reasonBadge(reason: string): string {
  return {
    unsubscribe: 'bg-red-100 text-red-700',
    hard_bounce: 'bg-orange-100 text-orange-700',
    complaint: 'bg-purple-100 text-purple-700',
    manual: 'bg-gray-100 text-gray-700',
  }[reason] || 'bg-gray-100 text-gray-500'
}
</script>

<template>
  <Head title="Suppressions" />
  <div>
    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
      <div class="flex items-center gap-2">
        <Ban class="h-4 w-4 text-gray-400" />
        <h1 class="text-sm font-semibold text-gray-900">Suppressions</h1>
        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-500">{{ suppressions.total }}</span>
      </div>
      <button @click="showAddForm = !showAddForm" class="inline-flex items-center gap-1 rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700">
        <Plus class="h-3.5 w-3.5" /> Add
      </button>
    </div>

    <!-- Add form -->
    <div v-if="showAddForm" class="border-b border-gray-100 bg-gray-50/50 px-4 py-3">
      <form @submit.prevent="submitAdd" class="flex flex-wrap items-end gap-2">
        <div>
          <label class="mb-0.5 block text-[10px] font-medium text-gray-500">Brand</label>
          <select v-model="form.brand_id" class="rounded border-gray-200 text-xs">
            <option v-for="b in brands" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
        </div>
        <div class="flex-1 min-w-[200px]">
          <label class="mb-0.5 block text-[10px] font-medium text-gray-500">Email</label>
          <input v-model="form.email" type="email" required class="w-full rounded border-gray-200 text-xs" :class="{ 'border-red-400': errors.email }" placeholder="email@example.com" />
        </div>
        <div>
          <label class="mb-0.5 block text-[10px] font-medium text-gray-500">Reason</label>
          <select v-model="form.reason" class="rounded border-gray-200 text-xs">
            <option value="unsubscribe">Unsubscribe</option>
            <option value="hard_bounce">Hard Bounce</option>
            <option value="complaint">Complaint</option>
            <option value="manual">Manual</option>
          </select>
        </div>
        <button type="submit" class="rounded bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700">Save</button>
      </form>
    </div>

    <!-- Filters -->
    <div class="flex items-center gap-2 border-b border-gray-100 px-4 py-2">
      <select class="rounded border-gray-200 text-xs" :value="filters.reason || ''" @change="applyFilter('reason', ($event.target as HTMLSelectElement).value)">
        <option value="">All Reasons</option>
        <option value="unsubscribe">Unsubscribe</option>
        <option value="hard_bounce">Hard Bounce</option>
        <option value="complaint">Complaint</option>
        <option value="manual">Manual</option>
      </select>
      <input class="rounded border-gray-200 text-xs flex-1 max-w-[200px]" placeholder="Search email..." :value="filters.search || ''"
        @keyup.enter="applyFilter('search', ($event.target as HTMLInputElement).value)" />
    </div>

    <div v-if="suppressions.data.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
      <Ban class="mb-3 h-10 w-10 text-gray-300" />
      <p class="text-sm font-medium text-gray-600">No suppressions</p>
    </div>
    <div v-else class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead>
          <tr class="border-b border-gray-100 text-[10px] font-medium uppercase tracking-wider text-gray-400">
            <th class="px-4 py-2">Email</th>
            <th class="px-4 py-2">Brand</th>
            <th class="px-4 py-2">Reason</th>
            <th class="px-4 py-2">Notes</th>
            <th class="px-4 py-2">Date</th>
            <th class="px-4 py-2"></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="s in suppressions.data" :key="s.id" class="border-b border-gray-50 hover:bg-gray-50/50">
            <td class="px-4 py-2.5 font-mono text-xs text-gray-800">{{ s.email }}</td>
            <td class="px-4 py-2.5 text-xs text-gray-500">{{ s.brand?.name || '-' }}</td>
            <td class="px-4 py-2.5"><span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium" :class="reasonBadge(s.reason)">{{ s.reason }}</span></td>
            <td class="max-w-[200px] truncate px-4 py-2.5 text-xs text-gray-400">{{ s.notes || '-' }}</td>
            <td class="px-4 py-2.5 text-xs text-gray-400">{{ s.created_at ? new Date(s.created_at).toLocaleDateString() : '-' }}</td>
            <td class="px-4 py-2.5"><button class="rounded p-1 text-gray-400 hover:text-red-600" title="Remove" @click="confirmDelete(s.id)"><Trash2 class="h-3.5 w-3.5" /></button></td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-if="suppressions.last_page > 1" class="flex items-center justify-between border-t border-gray-100 px-4 py-3">
      <span class="text-xs text-gray-500">Page {{ suppressions.current_page }} of {{ suppressions.last_page }}</span>
      <div class="flex items-center gap-1">
        <button v-for="page in suppressions.last_page" :key="page" class="rounded px-2.5 py-1 text-xs font-medium"
          :class="{ 'bg-blue-600 text-white': page === suppressions.current_page, 'text-gray-600 hover:bg-gray-100': page !== suppressions.current_page }" @click="goToPage(page)">{{ page }}</button>
      </div>
    </div>
  </div>
</template>
