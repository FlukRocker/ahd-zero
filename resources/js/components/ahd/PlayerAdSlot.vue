<script setup lang="ts">
export interface PlayerAdItem {
    href: string;
    src: string;
    alt: string;
    rel: string;
}

defineProps<{ ad: PlayerAdItem | null | undefined }>();

function safeRel(rel: string | undefined): string {
    return rel && rel.trim() !== ''
        ? rel
        : 'nofollow noopener sponsored noreferrer ugc';
}
</script>

<template>
    <a
        v-if="ad"
        :href="ad.href"
        :rel="safeRel(ad.rel)"
        target="_blank"
        class="player-ad-slot block w-full overflow-hidden rounded-lg"
        style="background: hsl(var(--bg-soft))"
    >
        <img
            :src="ad.src"
            :alt="ad.alt"
            class="block h-auto w-full"
            loading="lazy"
            decoding="async"
            referrerpolicy="no-referrer-when-downgrade"
        />
    </a>
</template>
