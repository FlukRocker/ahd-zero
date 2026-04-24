<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}>();
</script>

<template>
    <nav
        v-if="links.length > 3"
        class="mt-10 flex flex-wrap items-center justify-center gap-1"
    >
        <template v-for="(link, key) in links" :key="key">
            <span
                v-if="link.url === null"
                class="px-3 py-2 font-mono text-[13px] opacity-40"
                style="color: hsl(var(--fg-muted))"
            >
                <span v-html="link.label" />
            </span>
            <Link
                v-else
                :href="link.url"
                class="inline-block rounded-full px-3 py-2 font-mono text-[13px] transition-colors"
                :style="
                    link.active
                        ? 'background: hsl(var(--fg)); color: hsl(var(--bg));'
                        : 'background: hsl(var(--bg-soft)); color: hsl(var(--fg-muted)); border: 1px solid hsl(var(--border-ahd));'
                "
            >
                <span v-html="link.label" />
            </Link>
        </template>
    </nav>
</template>
