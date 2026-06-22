<script setup lang="ts">
import { ref } from 'vue'
import { Head, router, Link } from '@inertiajs/vue3'
import { Mail, Search } from '@lucide/vue'
import { dashboard } from '@/routes'

defineOptions({
  layout: { breadcrumbs: [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Email Messages', href: '/email-messages' },
  ]},
})

interface BrandInfo { id: number; name: string; slug: string }
interface LeadInfo { id: number; company_name: string; email: string | null; segment: string }
interface MessageData {
  id: number; brand_id: number; lead_id: number
  lead: LeadInfo | null; brand: BrandInfo | null
  sequence_step: number; subject: string | null; body: string | null
  status: string; approval_status: string; sent_at: string | null; opened_at: string | null; clicked_at: string | null
  created_at: string | null
}
interface PaginatedData<T> { data: T[]; current_page: number; last_page: number; per_page: number; total: number }

const props = defineProps<{
  messages: PaginatedData<MessageData>
  brands: BrandInfo[]
  filters: Record<string, string | undefined>
}>()

const showBody = ref<Record<number, boolean>>({})

function toggleBody(id: number) {
  showBody.value = { ...showBody.value, [id]: !showBody.value[id] }
}

function goToPage(page: number) {
  const params = new URLSearchParams(window.location.search)
  params.set('page', String(page))
  router.get(`/email-messages?${params.toString()}`, { preserveScroll: true, preserveState: true, replace: true })
}

function applyFilter(key: string, value: string) {
  const params = new URLSearchParams(window.location.search)
  if (value) params.set(key, value); else params.delete(key)
  params.delete('page')
  router.get(`/email-messages?${params.toString()}`, { preserveScroll: true, preserveState: true, replace: true })
}

function approvalColor(st: string): string {
  return { pending: 'bg-amber-100 text-amber-700', approved: 'bg-blue-100 text-blue-700', rejected: 'bg-red-100 text-red-700', needs_content: 'bg-purple-100 text-purple-700' }[st] || 'bg-gray-100 text-gray-500'
}
function statusColor(st: string): string {
  return { sent: 'bg-green-100 text-green-700', queued: 'bg-blue-100 text-blue-700', draft: 'bg-gray-100 text-gray-500', failed: 'bg-red-100 text-red-700' }[st] || 'bg-gray-100 text-gray-500'
}
</script>

<template>
  <Head title="Email Messages" />
  <div>
    <div class="flex items-center border-b border-gray-200 px-4 py-3">
      <Mail class="mr-2 h-4 w-4 text-gray-400" />
      <h1 class="text-sm font-semibold text-gray-900">Email Messages</h1>
      <span class="ml-2 rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-500">{{ messages.total }}</span>
    </div>

    <div class="flex items-center gap-2 border-b border-gray-100 px-4 py-2">
      <select class="rounded border-gray-200 text-xs" :value="filters.brand_id || ''" @change="applyFilter('brand_id', ($event.target as HTMLSelectElement).value)">
        <option value="">All Brands</option><option v-for="b in brands" :key="b.id" :value="String(b.id)">{{ b.name }}</option>
      </select>
      <select class="rounded border-gray-200 text-xs" :value="filters.approval_status || ''" @change="applyFilter('approval_status', ($event.target as HTMLSelectElement).value)">
        <option value="">All Approval</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option><option value="needs_content">Needs Content</option>
      </select>
      <select class="rounded border-gray-200 text-xs" :value="filters.status || ''" @change="applyFilter('status', ($event.target as HTMLSelectElement).value)">
        <option value="">All Status</option><option value="draft">Draft</option><option value="queued">Queued</option><option value="sent">Sent</option><option value="failed">Failed</option>
      </select>
      <input class="rounded border-gray-200 text-xs flex-1 max-w-[200px]" placeholder="Search subject or company..." :value="filters.search || ''"
        @keyup.enter="applyFilter('search', ($event.target as HTMLInputElement).value)" />
    </div>

    <div v-if="messages.data.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
      <Mail class="mb-3 h-10 w-10 text-gray-300" /><p class="text-sm font-medium text-gray-600">No email messages</p>
    </div>
    <div v-else class="overflow-x-auto">
      <table class="w-full text-left text-sm">
        <thead><tr class="border-b border-gray-100 text-[10px] font-medium uppercase tracking-wider text-gray-400">
          <th class="px-4 py-2">Company</th><th class="px-4 py-2">Step</th><th class="px-4 py-2">Subject</th><th class="px-4 py-2">Approval</th><th class="px-4 py-2">Status</th><th class="px-4 py-2">Sent</th><th class="px-4 py-2">Opened</th><th class="px-4 py-2"></th>
        </tr></thead>
        <tbody>
          <tr v-for="m in messages.data" :key="m.id" class="border-b border-gray-50 hover:bg-gray-50/50">
            <td class="px-4 py-2.5 text-xs text-gray-800">{{ m.lead?.company_name || 'Lead #'+m.lead_id }}</td>
            <td class="px-4 py-2.5 text-xs text-gray-500">Step {{ m.sequence_step }}</td>
            <td class="max-w-[250px] truncate px-4 py-2.5 text-xs text-gray-600">{{ m.subject || '(no subject)' }}</td>
            <td class="px-4 py-2.5"><span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium" :class="approvalColor(m.approval_status)">{{ m.approval_status }}</span></td>
            <td class="px-4 py-2.5"><span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-medium" :class="statusColor(m.status)">{{ m.status }}</span></td>
            <td class="px-4 py-2.5 text-xs text-gray-400">{{ m.sent_at ? new Date(m.sent_at).toLocaleDateString() : '-' }}</td>
            <td class="px-4 py-2.5 text-xs text-gray-400">{{ m.opened_at ? 'Yes' : m.clicked_at ? 'Clicked' : '-' }}</td>
            <td class="px-4 py-2.5">
              <button class="text-xs text-blue-600 hover:underline" @click="toggleBody(m.id)">{{ showBody[m.id] ? 'Hide' : 'View' }}</button>
            </td>
          </tr>
          <tr v-for="m in messages.data.filter(m => showBody[m.id])" :key="'body-'+m.id">
            <td colspan="8" class="bg-gray-50/50 px-6 py-3">
              <div class="max-h-64 overflow-y-auto rounded border border-gray-200 bg-white p-3">
                <p class="mb-2 text-xs font-semibold text-gray-800">{{ m.subject }}</p>
                <div class="whitespace-pre-wrap font-sans text-xs leading-relaxed text-gray-600">{{ m.body }}</div>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-if="messages.last_page > 1" class="flex items-center justify-between border-t border-gray-100 px-4 py-3">
      <span class="text-xs text-gray-500">Page {{ messages.current_page }} of {{ messages.last_page }}</span>
      <div class="flex items-center gap-1">
        <button v-for="page in messages.last_page" :key="page" class="rounded px-2.5 py-1 text-xs font-medium"
          :class="{ 'bg-blue-600 text-white': page === messages.current_page, 'text-gray-600 hover:bg-gray-100': page !== messages.current_page }" @click="goToPage(page)">{{ page }}</button>
      </div>
    </div>
  </div>
</template>
