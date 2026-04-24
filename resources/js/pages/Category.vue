<script setup lang="ts">
import PosterCard from '@/components/ahd/PosterCard.vue';
import StaggerGrid from '@/components/ahd/StaggerGrid.vue';
import Pagination from '@/components/Pagination.vue';
import { useAutoReveal } from '@/composables/useReveal';
import { useSeo } from '@/composables/useSeo';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { toCardItems, type AnimeRecord } from '@/lib/animeCard';
import { breadcrumbJsonLd } from '@/lib/schema';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

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
        <section class="mx-auto max-w-[1440px] px-6 pt-16 pb-8 lg:px-10">
            <div
                class="mb-3 font-mono text-[11px] tracking-[0.25em] uppercase"
                style="color: hsl(var(--fg-muted))"
            >
                หมวดหมู่
            </div>
            <h1
                class="font-display mb-4 leading-none italic"
                style="font-size: clamp(48px, 7vw, 80px)"
            >
                {{ categoryName }}
            </h1>
            <p
                v-if="anime.total"
                class="font-mono text-[13px]"
                style="color: hsl(var(--fg-faint))"
            >
                {{ anime.total }} เรื่อง
            </p>
        </section>

        <section class="reveal mx-auto max-w-[1440px] px-6 lg:px-10">
            <StaggerGrid>
                <PosterCard v-for="item in items" :key="item.id" :item="item" />
            </StaggerGrid>
            <Pagination :links="anime.links" />
        </section>
    </FrontLayout>
</template>
