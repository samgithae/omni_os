<script setup lang="ts">
import { router } from '@inertiajs/vue3'

export interface EmailStats {
  total: number
  needs_content: number
  pending: number
  approved: number
  rejected: number
  sent: number
  opened: number
  clicked: number
}

const props = defineProps<{
  stats: EmailStats
  currentFilter?: string
  activeFilter?: string
}>()

const emit = defineEmits<{
  filterByStat: [statKey: string]
}>()

interface StatItem {
  label: string
  key: string
  value: number
  color: string
}

const statItems: StatItem[] = [
  { label: 'Total', key: 'total', value: props.stats.total, color: 'text-gray-900' },
  { label: 'Needs Content', key: 'needs_content', value: props.stats.needs_content, color: 'text-purple-600' },
  { label: 'Pending', key: 'pending', value: props.stats.pending, color: 'text-amber-600' },
  { label: 'Approved', key: 'approved', value: props.stats.approved, color: 'text-blue-600' },
  { label: 'Rejected', key: 'rejected', value: props.stats.rejected, color: 'text-red-600' },
  { label: 'Sent', key: 'sent', value: props.stats.sent, color: 'text-green-600' },
  { label: 'Opened', key: 'opened', value: props.stats.opened, color: 'text-emerald-600' },
  { label: 'Clicked', key: 'clicked', value: props.stats.clicked, color: 'text-purple-600' },
]

function clickStat(key: string) {
  emit('filterByStat', key)
}
</script>

<template>
  <div class="flex items-center gap-0 border-b border-gray-200 px-4 py-2.5 text-xs">
    <button
      v-for="(item, idx) in statItems"
      :key="item.key"
      class="flex cursor-pointer items-center gap-1 px-3 py-1 transition-colors hover:bg-gray-50"
      :class="{
        'rounded bg-blue-50 font-semibold': activeFilter === item.key,
        'border-r border-gray-200': idx < statItems.length - 1,
      }"
      @click="clickStat(item.key)"
    >
      <span class="text-lg font-semibold leading-none" :class="item.color">
        {{ item.value.toLocaleString() }}
      </span>
      <span class="text-gray-500">{{ item.label }}</span>
    </button>
  </div>
</template>
