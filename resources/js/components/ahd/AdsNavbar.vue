<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

interface NavbarAdItem {
    href: string;
    alt: string;
    rel: string;
}

const page = usePage<{ ads?: { navbar?: NavbarAdItem[] } }>();
const items = computed<NavbarAdItem[]>(() => page.props.ads?.navbar ?? []);
</script>

<template>
    <div
        v-if="items.length"
        class="ads-navbar"
        role="navigation"
        aria-label="Sponsored links"
    >
        <div class="mx-auto flex max-w-[1440px] flex-wrap items-center gap-x-5 gap-y-1 px-6 py-2 lg:px-10">
            <span
                class="font-mono text-[10px] tracking-[0.22em] uppercase opacity-70"
            >Sponsored</span>
            <ul class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <li v-for="(it, i) in items" :key="i">
                    <a
                        :href="it.href"
                        :rel="it.rel || 'nofollow noopener sponsored noreferrer ugc'"
                        target="_blank"
                        class="text-[12px] font-medium hover:underline"
                    >{{ it.alt }}</a>
                </li>
            </ul>
        </div>
    </div>
</template>

<style scoped>
.ads-navbar {
    background: hsl(var(--bg-soft));
    border-bottom: 1px solid hsl(var(--border-ahd));
    color: hsl(var(--fg-muted));
    font-size: 12px;
}

.ads-navbar a {
    color: hsl(var(--fg));
}
</style>
