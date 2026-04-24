<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import FrontLayout from '@/layouts/FrontLayout.vue';
import PosterCard from '@/components/ahd/PosterCard.vue';
import StaggerGrid from '@/components/ahd/StaggerGrid.vue';
import Pagination from '@/components/Pagination.vue';
import { toCardItems, type AnimeRecord } from '@/lib/animeCard';
import { useAutoReveal } from '@/composables/useReveal';
import { useSeo } from '@/composables/useSeo';

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
        <section class="max-w-[1440px] mx-auto px-6 lg:px-10 pt-16 pb-8">
            <div class="font-mono text-[11px] tracking-[0.25em] uppercase mb-3" style="color: hsl(var(--fg-muted));">
                ค้นหา
            </div>
            <h1 class="font-display italic leading-none mb-3" style="font-size: clamp(40px, 6vw, 64px);">
                "{{ query }}"
            </h1>
            <p class="text-[13px] font-mono" style="color: hsl(var(--fg-faint));">
                <span v-if="animes.total">พบ {{ animes.total }} ผลลัพธ์</span>
                <span v-else>ไม่พบผลลัพธ์</span>
            </p>
        </section>

        <section class="max-w-[1440px] mx-auto px-6 lg:px-10 reveal">
            <StaggerGrid v-if="items.length">
                <PosterCard v-for="item in items" :key="item.id" :item="item" />
            </StaggerGrid>
            <Pagination :links="animes.links" />
        </section>
    </FrontLayout>
</template>
