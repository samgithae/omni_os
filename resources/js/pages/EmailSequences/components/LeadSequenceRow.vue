<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import { ChevronDown, ChevronRight } from '@lucide/vue'
import SequenceTimeline from './SequenceTimeline.vue'
import ExpandedSequence from './ExpandedSequence.vue'
import type { StepInfo } from './SequenceTimeline.vue'

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

const props = defineProps<{
  lead: LeadData
  selected: boolean
  showSegment?: boolean
}>()

const emit = defineEmits<{
  toggleSelect: [id: number]
  approved: []
}>()

const expanded = ref(false)
const previewState = ref<{ [step: number]: boolean }>({})

function toggleExpand() {
  expanded.value = !expanded.value
  if (!expanded.value) {
    previewState.value = {}
  }
}

function brandColor(): string {
  return props.lead.brand?.color || '#6b7280'
}

function brandAccentClass(): string {
  if (!props.lead.brand?.color) return 'border-l-gray-300'
  return ''
}

function brandAccentStyle(): Record<string, string> {
  return { borderLeftColor: brandColor() }
}

function segmentBadgeClass(): string {
  if (props.lead.segment === 'rabbit') return 'bg-emerald-100 text-emerald-700'
  if (props.lead.segment === 'deer') return 'bg-amber-100 text-amber-700'
  if (props.lead.segment === 'mouse') return 'bg-gray-100 text-gray-600'
  return 'bg-red-100 text-red-700'
}

function pendingCount(): number {
  return props.lead.steps.filter(s => s.exists && s.approval_status === 'pending').length
}

function sentCount(): number {
  return props.lead.steps.filter(s => {
    if (!s.exists) return false
    // Count sent, opened, clicked, or approved (queued) as "completed" steps
    if (s.clicked_at || s.opened_at) return true
    if (s.send_status === 'sent') return true
    if (s.approval_status === 'approved') return true
    return false
  }).length
}

function doApproveNext() {
  const nextPending = props.lead.steps.find(s => s.exists && s.approval_status === 'pending')
  if (!nextPending?.id) return

  router.post(`/email-sequences/${nextPending.id}/approve`, {}, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => emit('approved'),
  })
}
</script>

<template>
  <div
    class="border-b border-gray-100 transition-colors hover:bg-gray-50/50"
    :style="brandAccentStyle()"
    :class="[brandAccentClass(), 'border-l-2']"
  >
    <!-- Compact row -->
    <div class="flex items-center gap-3 px-4 py-2.5">
      <!-- Checkbox -->
      <div>
        <input
          type="checkbox"
          class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
          :checked="selected"
          @change="emit('toggleSelect', lead.id)"
        />
      </div>

      <!-- Brand color dot -->
      <div
        v-if="lead.brand?.color"
        class="h-2.5 w-2.5 shrink-0 rounded-full"
        :style="{ backgroundColor: brandColor() }"
        :title="lead.brand?.name"
      />

      <!-- Lead info -->
      <div class="min-w-0 flex-1">
        <div class="flex items-center gap-2">
          <span class="truncate text-sm font-semibold text-gray-900">
            {{ lead.company_name }}
          </span>
          <span
            class="inline-flex shrink-0 items-center rounded-full px-1.5 py-0.5 text-[10px] font-medium"
            :class="segmentBadgeClass()"
          >
            {{ lead.segment }}
          </span>
        </div>
        <div class="flex items-center gap-2 text-xs text-gray-500">
          <span v-if="lead.email" class="truncate">{{ lead.email }}</span>
          <span v-if="lead.city">· {{ lead.city }}</span>
        </div>
      </div>

      <!-- Sequence timeline (centerpiece) -->
      <div class="shrink-0 px-2">
        <SequenceTimeline :steps="lead.steps" :total-steps="5" />
      </div>

      <!-- Progress summary -->
      <div class="shrink-0 text-right text-[10px] leading-tight text-gray-500">
        <div>{{ sentCount() }} of 5 sent</div>
        <div v-if="pendingCount() > 0" class="font-medium text-amber-600">
          {{ pendingCount() }} pending
        </div>
      </div>

      <!-- Actions -->
      <div class="flex shrink-0 items-center gap-1">
        <button
          v-if="lead.has_pending"
          class="whitespace-nowrap rounded bg-blue-600 px-2.5 py-1 text-[11px] font-medium text-white hover:bg-blue-700"
          @click="doApproveNext"
        >
          Approve Next
        </button>
        <button
          class="flex items-center gap-0.5 rounded px-2 py-1 text-[11px] font-medium text-gray-500 hover:bg-gray-100"
          @click="toggleExpand"
        >
          {{ expanded ? 'Less' : 'View' }}
          <component :is="expanded ? ChevronDown : ChevronRight" class="h-3 w-3" />
        </button>
      </div>
    </div>

    <!-- Expanded sequence view -->
    <Transition name="expand">
      <div v-if="expanded" class="overflow-hidden">
        <ExpandedSequence
          :steps="lead.steps"
          :company-name="lead.company_name"
          :sequence-complete="lead.sequence_complete"
          :missing-steps="lead.missing_steps"
          v-model:preview-open="previewState"
          @approved="emit('approved')"
        />
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.expand-enter-active,
.expand-leave-active {
  transition: all 0.2s ease;
}
.expand-enter-from,
.expand-leave-to {
  max-height: 0;
  opacity: 0;
}
.expand-enter-to,
.expand-leave-from {
  max-height: 2000px;
  opacity: 1;
}
</style>
