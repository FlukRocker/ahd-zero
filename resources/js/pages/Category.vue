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
import { breadcrumbJsonLd } from '@/lib/schema';

interface Paginator<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    total?: number;
}

const props = defineProps<{
    anime: Paginator<AnimeRecord>;
    categoryName: string;
    currentType: string | number;
}>();

const items = computed(() => toCardItems(props.anime.data));

useSeo(() => ({
    title: props.categoryName,
    description: `เรียกดูอนิเมะหมวด ${props.categoryName}`,
    type: 'website',
    schema: [
        breadcrumbJsonLd([
            { name: 'หน้าแรก', url: '/' },
            { name: props.categoryName, url: `/category/${props.currentType}` },
        ]),
    ],
}));

useAutoReveal();
</script>

<template>
    <Head :title="categoryName" />
    <FrontLayout>
        <section class="max-w-[1440px] mx-auto px-6 lg:px-10 pt-16 pb-8">
            <div class="font-mono text-[11px] tracking-[0.25em] uppercase mb-3" style="color: hsl(var(--fg-muted));">
                หมวดหมู่
            </div>
            <h1 class="font-display italic leading-none mb-4" style="font-size: clamp(48px, 7vw, 80px);">
                {{ categoryName }}
            </h1>
            <p v-if="anime.total" class="text-[13px] font-mono" style="color: hsl(var(--fg-faint));">
                {{ anime.total }} เรื่อง
            </p>
        </section>

        <section class="max-w-[1440px] mx-auto px-6 lg:px-10 reveal">
            <StaggerGrid>
                <PosterCard v-for="item in items" :key="item.id" :item="item" />
            </StaggerGrid>
            <Pagination :links="anime.links" />
        </section>
    </FrontLayout>
</template>
