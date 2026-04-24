<script setup lang="ts">
import PosterCard from '@/components/ahd/PosterCard.vue';
import StaggerGrid from '@/components/ahd/StaggerGrid.vue';
import Pagination from '@/components/Pagination.vue';
import { useAutoReveal } from '@/composables/useReveal';
import { useSeo } from '@/composables/useSeo';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { toCardItems, type AnimeRecord } from '@/lib/animeCard';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Paginator<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    total?: number;
}

const props = defineProps<{
    animes: Paginator<AnimeRecord>;
    query: string;
}>();

const items = computed(() => toCardItems(props.animes.data));

useSeo(() => ({
    title: `ค้นหา: ${props.query}`,
    description: `ผลการค้นหาสำหรับ "${props.query}"`,
    robots: 'noindex, follow',
    type: 'website',
}));

useAutoReveal();
</script>

<template>
    <Head :title="`ค้นหา: ${query}`" />
    <FrontLayout>
        <section class="mx-auto max-w-[1440px] px-6 pt-16 pb-8 lg:px-10">
            <div
                class="mb-3 font-mono text-[11px] tracking-[0.25em] uppercase"
                style="color: hsl(var(--fg-muted))"
            >
                ค้นหา
            </div>
            <h1
                class="font-display mb-3 leading-none italic"
                style="font-size: clamp(40px, 6vw, 64px)"
            >
                "{{ query }}"
            </h1>
            <p
                class="font-mono text-[13px]"
                style="color: hsl(var(--fg-faint))"
            >
                <span v-if="animes.total">พบ {{ animes.total }} ผลลัพธ์</span>
                <span v-else>ไม่พบผลลัพธ์</span>
            </p>
        </section>

        <section class="reveal mx-auto max-w-[1440px] px-6 lg:px-10">
            <StaggerGrid v-if="items.length">
                <PosterCard v-for="item in items" :key="item.id" :item="item" />
            </StaggerGrid>
            <Pagination :links="animes.links" />
        </section>
    </FrontLayout>
</template>
