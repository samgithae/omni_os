<script setup lang="ts">
defineProps<{
    subject: string | null;
    body: string | null;
    leadName?: string;
}>();
</script>

<template>
    <div class="rounded-lg border border-gray-200 bg-gray-50">
        <div class="border-b border-gray-200 px-3 py-2">
            <span class="text-xs font-medium text-gray-700">{{
                subject || '(no subject)'
            }}</span>
        </div>
        <div
            class="max-h-[300px] overflow-y-auto p-3 text-sm leading-relaxed text-gray-700"
        >
            <div
                v-if="body"
                v-html="body"
                class="prose prose-sm prose-headings:text-gray-900 max-w-none"
            />
            <p v-else class="text-gray-400 italic">No content</p>
        </div>
    </div>
</template>
