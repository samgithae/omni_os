<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import EmailPreview from './EmailPreview.vue'
import type { StepInfo } from './SequenceTimeline.vue'

const props = defineProps<{
  steps: StepInfo[]
  companyName: string
}>()

const emit = defineEmits<{
  approved: []
  rejected: []
}>()

interface PreviewState {
  [step: number]: boolean
}
const previewOpen = defineModel<PreviewState>('previewOpen', { default: {} })

function togglePreview(step: number) {
  previewOpen.value = { ...previewOpen.value, [step]: !previewOpen.value[step] }
}

function stepStatusIcon(step: StepInfo): string {
  if (step.clicked_at) return '🔗'
  if (step.opened_at) return '👁'
  if (step.send_status === 'sent') return '✓'
  if (step.approval_status === 'approved' && step.send_status === 'queued') return '✓'
  if (step.approval_status === 'needs_content') return '✏️'
  if (step.approval_status === 'pending') return '⏳'
  if (step.approval_status === 'rejected') return '✗'
  return '○'
}

function stepStatusText(step: StepInfo): string {
  if (step.clicked_at) return 'Clicked'
  if (step.opened_at) return 'Opened'
  if (step.send_status === 'sent') return 'Sent'
  if (step.approval_status === 'approved') return 'Approved'
  if (step.approval_status === 'needs_content') return 'Needs Content'
  if (step.approval_status === 'pending') return 'Pending'
  if (step.approval_status === 'rejected') return 'Rejected'
  return 'Draft'
}

function stepStatusClass(step: StepInfo): string {
  if (step.clicked_at) return 'text-purple-600'
  if (step.opened_at) return 'text-green-600'
  if (step.send_status === 'sent') return 'text-blue-600'
  if (step.approval_status === 'approved') return 'text-blue-600'
  if (step.approval_status === 'needs_content') return 'text-purple-600'
  if (step.approval_status === 'pending') return 'text-amber-600'
  if (step.approval_status === 'rejected') return 'text-red-600'
  return 'text-gray-400'
}

function approvalBadge(step: StepInfo): string {
  if (!step.exists) return ''
  if (step.approval_status === 'needs_content') return 'bg-purple-100 text-purple-700'
  if (step.approval_status === 'pending') return 'bg-amber-100 text-amber-700'
  if (step.approval_status === 'approved') return 'bg-blue-100 text-blue-700'
  if (step.approval_status === 'rejected') return 'bg-red-100 text-red-700'
  return 'bg-gray-100 text-gray-500'
}

function subjectMismatch(leadName: string, subject: string | null): boolean {
  if (!subject) return false
  const normalized = leadName.toLowerCase().split(/\s+/)
  const subjectLower = subject.toLowerCase()
  return !normalized.some(word => word.length > 3 && subjectLower.includes(word))
}

function approveSingle(emailId: number) {
  router.post(`/email-sequences/${emailId}/approve`, {}, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => emit('approved'),
  })
}

function rejectSingle(emailId: number) {
  router.post(`/email-sequences/${emailId}/reject`, {}, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => emit('approved'),
  })
}

function approveAllPending() {
  const pendingIds = props.steps
    .filter(s => s.exists && s.approval_status === 'pending' && s.id)
    .map(s => s.id!)
  if (!pendingIds.length) return
  router.post('/email-sequences/approve', { lead_ids: [pendingIds[0]] }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => emit('approved'),
  })
}

function rejectAllPending() {
  const pendingIds = props.steps
    .filter(s => s.exists && s.approval_status === 'pending' && s.id)
    .map(s => s.id!)
  if (!pendingIds.length) return
  router.post('/email-sequences/reject', { lead_ids: [pendingIds[0]] }, {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => emit('approved'),
  })
}

function formatDate(dateStr: string | null): string {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

const hasAnyPending = props.steps.some(s => s.exists && s.approval_status === 'pending')
</script>

<template>
  <div class="border-t border-gray-100 bg-gray-50/50 px-6 py-4">
    <Transition name="expand" mode="out-in">
      <div class="space-y-4">
        <!-- Each step as a vertical timeline item -->
        <div
          v-for="step in steps"
          :key="step.step"
          class="relative pl-6"
        >
          <!-- Vertical line connector -->
          <div
            v-if="step.exists"
            class="absolute left-[7px] top-3 h-full w-px bg-gray-200"
          />
          <div
            v-if="step.exists"
            class="absolute left-0 top-[5px] h-[15px] w-[15px] rounded-full border-2"
            :class="{
              'border-green-500 bg-green-100': step.clicked_at || step.opened_at || step.send_status === 'sent',
              'border-amber-400 bg-amber-100': step.approval_status === 'pending',
              'border-purple-400 bg-purple-100': step.approval_status === 'needs_content',
              'border-blue-500 bg-blue-100': step.approval_status === 'approved' && step.send_status !== 'sent',
              'border-red-400 bg-red-100': step.approval_status === 'rejected',
              'border-gray-300 bg-gray-50': !step.exists,
            }"
          />

          <div :class="{ 'opacity-40': !step.exists }">
            <!-- Step header -->
            <div class="mb-1 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="text-xs font-semibold text-gray-500">
                  Step {{ step.step }}
                </span>
                <span
                  v-if="step.exists"
                  class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-medium"
                  :class="approvalBadge(step)"
                >
                  {{ stepStatusIcon(step) }} {{ stepStatusText(step) }}
                </span>
                <span
                  v-if="!step.exists"
                  class="text-[10px] italic text-gray-400"
                >
                  Not drafted yet
                </span>
                <!-- Subject mismatch warning -->
                <span
                  v-if="step.exists && subjectMismatch(companyName, step.subject)"
                  class="group relative inline-flex cursor-help items-center gap-1 rounded bg-red-50 px-1.5 py-0.5 text-[10px] text-red-600"
                  title="Subject may reference wrong company — review before approving"
                >
                  ⚠️ mismatch
                </span>
              </div>

              <!-- Timestamps -->
              <div
                v-if="step.exists"
                class="flex items-center gap-3 text-[10px] text-gray-400"
              >
                <span v-if="step.sent_at">Sent {{ formatDate(step.sent_at) }}</span>
                <span v-if="step.opened_at">Opened {{ formatDate(step.opened_at) }}</span>
                <span v-else-if="step.send_status === 'sent'" class="text-amber-500">Not opened</span>
                <span v-if="step.clicked_at">Clicked {{ formatDate(step.clicked_at) }}</span>
                <span v-else-if="step.opened_at" class="text-amber-500">No clicks</span>
                <span v-if="step.scheduled_for && !step.sent_at">
                  Scheduled: {{ formatDate(step.scheduled_for) }}
                </span>
              </div>
            </div>

            <!-- Subject line -->
            <div
              v-if="step.exists"
              class="mb-1 text-sm font-medium text-gray-800"
            >
              "{{ step.subject }}"
            </div>

            <!-- Actions for existing steps -->
            <div
              v-if="step.exists"
              class="mb-2 flex items-center gap-2"
            >
              <button
                class="text-xs font-medium text-gray-500 underline-offset-2 hover:text-gray-700 hover:underline"
                @click="togglePreview(step.step)"
              >
                {{ previewOpen[step.step] ? 'Hide Preview ▲' : 'Preview ▾' }}
              </button>
              <a
                v-if="step.id"
                :href="`/admin/email-messages/${step.id}/edit`"
                target="_blank"
                class="text-xs text-gray-400 underline-offset-2 hover:text-gray-600 hover:underline"
              >
                Edit
              </a>
              <button
                v-if="step.approval_status === 'pending' && step.id"
                class="rounded bg-blue-600 px-2.5 py-0.5 text-[10px] font-medium text-white hover:bg-blue-700"
                @click="approveSingle(step.id!)"
              >
                Approve
              </button>
              <button
                v-if="step.approval_status === 'pending' && step.id"
                class="rounded bg-white px-2.5 py-0.5 text-[10px] font-medium text-gray-600 ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                @click="rejectSingle(step.id!)"
              >
                Reject
              </button>
            </div>

            <!-- Inline preview -->
            <Transition name="fade">
              <div v-if="previewOpen[step.step] && step.exists" class="mb-3">
                <EmailPreview
                  :subject="step.subject"
                  :body="step.body"
                  :lead-name="companyName"
                />
              </div>
            </Transition>
          </div>
        </div>

        <!-- Batch actions for this lead -->
        <div
          v-if="hasAnyPending"
          class="flex items-center gap-2 border-t border-gray-200 pt-3"
        >
          <button
            class="rounded bg-blue-600 px-3 py-1 text-xs font-medium text-white hover:bg-blue-700"
            @click="approveAllPending"
          >
            Approve All Pending
          </button>
          <button
            class="rounded bg-white px-3 py-1 text-xs font-medium text-gray-600 ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
            @click="rejectAllPending"
          >
            Reject All Pending
          </button>
        </div>
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
  opacity: 0;
  transform: translateY(-8px);
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
