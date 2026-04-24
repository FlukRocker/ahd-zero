<script setup lang="ts">
import { ref } from 'vue';
import { useStaggerInView } from '@/composables/useReveal';

withDefaults(
    defineProps<{
        as?: string;
        gridStyle?: string;
        gap?: number;
    }>(),
    { as: 'div', gridStyle: 'grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));', gap: 0.03 },
);

const root = ref<HTMLElement | null>(null);
useStaggerInView(root, { y: 18, gap: 0.03 });
</script>

<template>
    <component :is="as" ref="root" class="grid gap-5" :style="gridStyle">
        <slot />
    </component>
</template>
