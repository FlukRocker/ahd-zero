<script setup lang="ts">
defineProps<{
    eyebrow?: string;
    title: string;
    link?: string;
    linkHref?: string;
}>();
</script>

<template>
    <div class="mb-6 flex items-end justify-between">
        <div>
            <div
                v-if="eyebrow"
                class="mb-2 font-mono text-[10px] tracking-[0.22em] uppercase"
                style="color: hsl(var(--fg-faint))"
            >
                {{ eyebrow }}
            </div>
            <h2
                class="font-display text-[36px] leading-none italic md:text-[44px]"
            >
                {{ title }}
            </h2>
        </div>
        <a
            v-if="link"
            :href="linkHref || '#'"
            class="u-grow hidden font-mono text-[13px] tracking-wider uppercase md:block"
            >{{ link }} →</a
        >
    </div>
</template>
