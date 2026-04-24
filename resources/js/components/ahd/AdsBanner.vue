<script setup lang="ts">
export interface BannerItem {
    href: string;
    src: string;
    alt: string;
    col: number;
    rel: string;
}

withDefaults(
    defineProps<{
        banners: BannerItem[];
        title?: string;
    }>(),
    { title: '' },
);
</script>

<template>
    <aside v-if="banners.length" class="ads-banner">
        <div
            v-if="title"
            class="mb-2 font-mono text-[10px] tracking-[0.22em] uppercase"
            style="color: hsl(var(--fg-faint))"
        >
            {{ title }}
        </div>
        <div class="ads-grid">
            <a
                v-for="(b, i) in banners"
                :key="i"
                :href="b.href"
                target="_blank"
                :rel="b.rel || 'nofollow noopener sponsored noreferrer ugc'"
                :class="b.col >= 12 ? 'col-full' : 'col-half'"
            >
                <img
                    :src="b.src"
                    :alt="b.alt"
                    class="block h-auto w-full"
                    loading="lazy"
                    decoding="async"
                    referrerpolicy="no-referrer-when-downgrade"
                />
            </a>
        </div>
    </aside>
</template>

<style scoped>
.ads-banner {
    width: 100%;
}

.ads-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 4px;
}

.ads-grid > .col-full {
    grid-column: 1 / -1;
}

.ads-grid > .col-half {
    grid-column: span 1;
}

.ads-grid a {
    display: block;
    overflow: hidden;
    border-radius: 8px;
    background: hsl(var(--bg-soft));
    transition: transform 0.2s;
}

.ads-grid a:hover {
    transform: translateY(-1px);
}
</style>
