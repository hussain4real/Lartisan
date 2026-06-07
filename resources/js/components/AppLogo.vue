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
        src="/images/Lartisan.svg"
        alt="Lartisan"
        :class="['h-8 w-auto object-contain', className]"
        v-bind="$attrs"
    />
</template>
