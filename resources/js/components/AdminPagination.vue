<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    links: Array<{ url: string | null; label: string; active: boolean }>;
}>();

// Laravel labels carry HTML entities (&laquo; / &raquo;); show them as text.
function text(label: string): string {
    return label
        .replace('&laquo;', '«')
        .replace('&raquo;', '»')
        .replace('&amp;', '&');
}
</script>

<template>
    <nav
        v-if="links.length > 3"
        class="flex flex-wrap items-center justify-center gap-1 text-sm"
        aria-label="Pagination"
    >
        <template v-for="(link, i) in links" :key="`${i}-${link.label}`">
            <span
                v-if="link.url === null"
                class="rounded-md border px-3 py-1.5 opacity-40"
                >{{ text(link.label) }}</span
            >
            <Link
                v-else
                :href="link.url"
                preserve-scroll
                class="rounded-md border px-3 py-1.5 hover:bg-accent"
                :class="link.active ? 'bg-accent font-semibold' : ''"
                >{{ text(link.label) }}</Link
            >
        </template>
    </nav>
</template>
