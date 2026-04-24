<script setup lang="ts">
import { useStaggerInView } from '@/composables/useReveal';
import type { CardItem } from '@/lib/animeCard';
import { ref } from 'vue';
import EditorialCard from './EditorialCard.vue';
import LandscapeCard from './LandscapeCard.vue';
import PosterCard from './PosterCard.vue';

type Layout = 'poster' | 'landscape' | 'editorial';

const props = withDefaults(
    defineProps<{ items: CardItem[]; layout?: Layout }>(),
    { layout: 'poster' },
);

const root = ref<HTMLElement | null>(null);
useStaggerInView(root, { y: 20, gap: 0.04 });

function slotWidth(): string {
    if (props.layout === 'landscape') return '340px';
    if (props.layout === 'editorial') return '440px';
    return 'var(--density-row)';
}
</script>

<template>
    <div
        ref="root"
        class="thin-scroll flex snap-x gap-[var(--density-gap)] overflow-x-auto pb-4"
        style="scroll-padding: 24px"
    >
        <div
            v-for="item in items"
            :key="item.id"
            class="shrink-0 snap-start"
            :style="{ width: slotWidth() }"
        >
            <PosterCard v-if="layout === 'poster'" :item="item" />
            <LandscapeCard v-else-if="layout === 'landscape'" :item="item" />
            <EditorialCard v-else-if="layout === 'editorial'" :item="item" />
        </div>
    </div>
</template>
