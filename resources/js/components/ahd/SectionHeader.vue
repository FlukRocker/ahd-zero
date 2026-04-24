<script setup lang="ts">
defineProps<{ eyebrow?: string; title: string; link?: string; linkHref?: string }>();
</script>

<template>
    <div class="flex items-end justify-between mb-6">
        <div>
            <div
                v-if="eyebrow"
                class="font-mono text-[10px] tracking-[0.22em] uppercase mb-2"
                style="color: hsl(var(--fg-faint));"
            >{{ eyebrow }}</div>
            <h2 class="font-display text-[36px] md:text-[44px] italic leading-none">{{ title }}</h2>
        </div>
        <a
            v-if="link"
            :href="linkHref || '#'"
            class="text-[13px] font-mono uppercase tracking-wider u-grow hidden md:block"
        >{{ link }} →</a>
    </div>
</template>
