<script setup lang="ts">
export interface StepInfo {
    step: number;
    exists: boolean;
    subject: string | null;
    body: string | null;
    approval_status:
        | 'pending'
        | 'approved'
        | 'rejected'
        | 'needs_content'
        | null;
    send_status: 'draft' | 'queued' | 'sent' | 'failed' | null;
    sent_at: string | null;
    opened_at: string | null;
    clicked_at: string | null;
    scheduled_for: string | null;
    id: number | null;
}

const props = defineProps<{
    steps: StepInfo[];
    totalSteps?: number;
}>();

const total = props.totalSteps ?? 5;

function stepState(
    step: StepInfo,
):
    | 'sent_opened'
    | 'sent'
    | 'pending'
    | 'approved'
    | 'rejected'
    | 'draft'
    | 'needs_content'
    | 'empty' {
    if (!step.exists) return 'empty';
    if (step.clicked_at) return 'sent_opened';
    if (step.opened_at) return 'sent_opened';
    if (step.send_status === 'sent') return 'sent';
    if (step.approval_status === 'pending') return 'pending';
    if (step.approval_status === 'needs_content') return 'needs_content';
    if (step.approval_status === 'rejected') return 'rejected';
    if (step.approval_status === 'approved') return 'approved';
    return 'draft';
}

function stateColors(state: string) {
    switch (state) {
        case 'sent_opened':
            return 'bg-green-500 border-green-500 ring-green-200';
        case 'sent':
            return 'bg-blue-500 border-blue-500 ring-blue-200';
        case 'approved':
            return 'bg-blue-500 border-blue-500 ring-blue-200';
        case 'needs_content':
            return 'bg-purple-400 border-purple-400 ring-purple-200';
        case 'pending':
            return 'bg-amber-400 border-amber-400 ring-amber-200';
        case 'rejected':
            return 'bg-red-400 border-red-400 ring-red-200';
        case 'draft':
            return 'bg-gray-200 border-gray-300';
        default:
            return 'bg-white border-dashed border-gray-300';
    }
}

function stateLabel(step: StepInfo): string {
    const state = stepState(step);
    if (!step.exists) return '—';
    if (step.clicked_at) return '🔗 clicked';
    if (step.opened_at) return '👁 opened';
    if (step.send_status === 'sent') return '✓ sent';
    if (step.approval_status === 'approved') return '✓ approved';
    if (step.approval_status === 'needs_content') return '✏️ needs draft';
    if (step.approval_status === 'pending') return '⏳ pending';
    if (step.approval_status === 'rejected') return '✗ rejected';
    return '— draft';
}

function isActive(step: StepInfo): boolean {
    if (!step.exists) return false;
    return step.approval_status === 'pending';
}

function findCurrentStepIndex(): number {
    const idx = props.steps.findIndex(
        (s) => s.exists && s.approval_status === 'pending',
    );
    if (idx >= 0) return idx;
    // fallback: first empty step or last existing
    const emptyIdx = props.steps.findIndex((s) => !s.exists);
    return emptyIdx >= 0 ? emptyIdx : props.steps.length - 1;
}
</script>

<template>
    <div class="flex items-center gap-0">
        <div
            v-for="(step, idx) in steps"
            :key="step.step"
            class="flex items-center"
        >
            <!-- Step circle -->
            <div class="flex flex-col items-center">
                <div
                    class="relative flex h-[22px] w-[22px] items-center justify-center rounded-full border-2 transition-all duration-200"
                    :class="[
                        stateColors(stepState(step)),
                        isActive(step) ? 'animate-pulse ring-4' : '',
                    ]"
                >
                    <span
                        v-if="
                            stepState(step) !== 'empty' &&
                            stepState(step) !== 'draft'
                        "
                        class="block h-[6px] w-[6px] rounded-full bg-white"
                    />
                    <span
                        v-else-if="stepState(step) === 'draft'"
                        class="block h-[6px] w-[6px] rounded-full bg-gray-400"
                    />
                </div>
                <span
                    class="mt-1 text-[10px] leading-tight whitespace-nowrap"
                    :class="{
                        'text-gray-400': !step.exists,
                        'font-medium text-green-600':
                            stepState(step) === 'sent_opened',
                        'font-medium text-blue-600': stepState(step) === 'sent',
                        'font-medium text-amber-600':
                            stepState(step) === 'pending',
                        'font-medium text-purple-600':
                            stepState(step) === 'needs_content',
                        'font-medium text-red-600':
                            stepState(step) === 'rejected',
                        'text-gray-500':
                            stepState(step) === 'draft' ||
                            stepState(step) === 'empty',
                    }"
                >
                    E{{ step.step }}
                </span>
                <span class="text-[9px] leading-tight text-gray-400">
                    {{ stateLabel(step) }}
                </span>
            </div>

            <!-- Connector line -->
            <div
                v-if="idx < steps.length - 1"
                class="mx-0.5 mt-[11px] h-[2px] w-5 self-start"
                :class="{
                    'bg-green-300': stepState(step) === 'sent_opened',
                    'bg-blue-300':
                        stepState(step) === 'sent' ||
                        stepState(step) === 'approved',
                    'bg-gray-200':
                        stepState(step) !== 'sent_opened' &&
                        stepState(step) !== 'sent' &&
                        stepState(step) !== 'approved',
                }"
            />
        </div>
    </div>
</template>
