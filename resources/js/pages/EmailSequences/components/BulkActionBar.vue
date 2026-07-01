<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { X } from '@lucide/vue';

const props = defineProps<{
    selectedCount: number;
    hasPending: boolean;
}>();

const emit = defineEmits<{
    clearSelection: [];
    approveAll: [];
    rejectAll: [];
}>();

function approveSelected() {
    emit('approveAll');
}

function rejectSelected() {
    emit('rejectAll');
}
</script>

<template>
    <Transition name="slide-up">
        <div
            v-if="selectedCount > 0"
            class="sticky right-0 bottom-0 left-0 z-30 border-t border-blue-200 bg-blue-50 px-4 py-3 shadow-lg"
        >
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="text-sm font-medium text-blue-900">
                        {{ selectedCount }} lead{{
                            selectedCount !== 1 ? 's' : ''
                        }}
                        selected
                    </span>
                    <button
                        class="flex items-center gap-1 text-xs text-gray-500 hover:text-gray-700"
                        @click="emit('clearSelection')"
                    >
                        <X class="h-3 w-3" />
                        Clear Selection
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        class="rounded-md bg-blue-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!hasPending"
                        @click="approveSelected"
                    >
                        Approve All Pending
                    </button>
                    <button
                        class="rounded-md bg-white px-4 py-1.5 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-gray-300 ring-inset hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!hasPending"
                        @click="rejectSelected"
                    >
                        Reject All Pending
                    </button>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.slide-up-enter-active,
.slide-up-leave-active {
    transition: all 0.2s ease;
}
.slide-up-enter-from,
.slide-up-leave-to {
    transform: translateY(100%);
    opacity: 0;
}
</style>
