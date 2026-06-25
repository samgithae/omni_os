<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        src="/images/omni-logo-sm.png"
        alt="Omni OS"
        :class="className"
        v-bind="$attrs"
        class="object-contain"
    />
</template>
