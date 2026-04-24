<script setup lang="ts">
import { computed } from 'vue';

type IconName =
    | 'play' | 'plus' | 'heart' | 'search' | 'sparkle' | 'bell' | 'menu'
    | 'chevron' | 'chevDown' | 'close' | 'grid' | 'list' | 'filter' | 'fire'
    | 'settings' | 'arrow' | 'user' | 'home' | 'compass' | 'cal' | 'check'
    | 'volume' | 'pause' | 'skip' | 'back' | 'full';

const props = defineProps<{ name: IconName; size?: number | string }>();

const PATHS: Record<IconName, string> = {
    play: '<path d="M8 5v14l11-7z"/>',
    plus: '<path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>',
    heart: '<path d="M12 21s-7-4.35-9.5-9.2C.9 8.5 2.5 5 6 5c2 0 3.2 1 4 2.5C10.8 6 12 5 14 5c3.5 0 5.1 3.5 3.5 6.8C19 16.65 12 21 12 21z"/>',
    search: '<circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="2" fill="none"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    sparkle: '<path d="M12 2l2 6 6 2-6 2-2 6-2-6-6-2 6-2z"/>',
    bell: '<path d="M6 17V11a6 6 0 0112 0v6l2 2H4l2-2zM10 21a2 2 0 004 0" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linecap="round"/>',
    menu: '<path d="M4 7h16M4 12h16M4 17h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    chevron: '<path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
    chevDown: '<path d="M6 9l6 6 6-6" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>',
    close: '<path d="M6 6l12 12M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    grid: '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
    list: '<path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    filter: '<path d="M4 5h16M7 12h10M10 19h4" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>',
    fire: '<path d="M12 2c2 3 4 4 4 7a4 4 0 01-8 0c0-1 .5-2 1-2.5C9 9 10 7 12 2zM7 14c0 3 2 6 5 6s5-3 5-6c-1 1.5-3 2-5 2s-4-.5-5-2z"/>',
    settings: '<circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" fill="none"/>',
    arrow: '<path d="M5 12h14M13 6l6 6-6 6" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
    user: '<circle cx="12" cy="8" r="4" stroke="currentColor" stroke-width="2" fill="none"/><path d="M4 20c0-4 4-6 8-6s8 2 8 6" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>',
    home: '<path d="M3 10l9-7 9 7v10a1 1 0 01-1 1h-5v-6H9v6H4a1 1 0 01-1-1V10z" stroke="currentColor" stroke-width="1.8" fill="none" stroke-linejoin="round"/>',
    compass: '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.8" fill="none"/><path d="M15 9l-1.5 4.5L9 15l1.5-4.5z" fill="currentColor"/>',
    cal: '<rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.8" fill="none"/><path d="M3 9h18M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.8"/>',
    check: '<path d="M5 12l5 5L20 7" stroke="currentColor" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/>',
    volume: '<path d="M4 10v4h3l4 4V6L7 10H4z" fill="currentColor"/><path d="M15 8a5 5 0 010 8" stroke="currentColor" stroke-width="1.8" fill="none"/>',
    pause: '<rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/>',
    skip: '<path d="M6 5l9 7-9 7V5zM17 5v14" stroke="currentColor" stroke-width="2" fill="currentColor" stroke-linejoin="round"/>',
    back: '<path d="M19 19l-9-7 9-7M7 5v14" stroke="currentColor" stroke-width="2" fill="currentColor" stroke-linejoin="round"/>',
    full: '<path d="M4 10V4h6M20 10V4h-6M4 14v6h6M20 14v6h-6" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round"/>',
};

const paths = computed(() => PATHS[props.name] ?? '');
const sizeAttr = computed(() => props.size ?? 20);
</script>

<template>
    <svg :width="sizeAttr" :height="sizeAttr" viewBox="0 0 24 24" style="display: inline-block; vertical-align: middle;" v-html="paths" />
</template>
