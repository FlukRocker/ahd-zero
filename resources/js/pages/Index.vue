<script setup lang="ts">
import Hero from '@/components/ahd/Hero.vue';
import PosterCard from '@/components/ahd/PosterCard.vue';
import Rail from '@/components/ahd/Rail.vue';
import SectionHeader from '@/components/ahd/SectionHeader.vue';
import StaggerGrid from '@/components/ahd/StaggerGrid.vue';
import Pagination from '@/components/Pagination.vue';
import { useAutoReveal } from '@/composables/useReveal';
import { useAppMeta, useSeo } from '@/composables/useSeo';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { toCardItem, toCardItems, type AnimeRecord } from '@/lib/animeCard';
import { bunnyImg } from '@/lib/img';
import { organizationJsonLd, siteJsonLd } from '@/lib/schema';
import { Head } from '@inertiajs/vue3';
import { useHead } from '@unhead/vue';
import { computed } from 'vue';

interface Paginator<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    meta?: unknown;
}

const props = defineProps<{
    anime: Paginator<AnimeRecord>;
    recommended?: AnimeRecord[];
    popular?: AnimeRecord[];
}>();

const heroItems = computed(() => {
    const source = (
        props.recommended && props.recommended.length >= 3
            ? props.recommended
            : props.anime.data
    ).slice(0, 5);
    return source.map(toCardItem);
});

const popularItems = computed(() =>
    toCardItems(props.popular ?? props.anime.data.slice(0, 10)),
);
const latestItems = computed(() => toCardItems(props.anime.data));

const { appName, appUrl } = useAppMeta();

useSeo(() => ({
    title: 'ดูอนิเมะออนไลน์ ซับไทย พากย์ไทย เดอะมูฟวี่ HD',
    description:
        'ดูอนิเมะออนไลน์ฟรี รวมอนิเมะใหม่ล่าสุด ทั้งซับไทย พากย์ไทย เดอะมูฟวี่ คุณภาพ HD ดูง่าย ลื่นไหล อัปเดตทุกวัน รับชมได้ทุกอุปกรณ์ผ่าน Anime HD Zero',
    type: 'website',
    schema: [
        siteJsonLd(appName.value, appUrl.value),
        organizationJsonLd(appName.value, appUrl.value),
    ],
}));

// Preload the first hero image — that <img> is the LCP element. Use the
// Bunny-optimized URL at the same width the Hero <img> requests so the
// preload exactly matches the eventual src (otherwise the browser fetches
// both the original and the optimized variant — wasted bytes).
useHead(() => {
    const first = heroItems.value[0];
    if (!first?.poster) return {};
    const optimized = bunnyImg(first.poster, { width: 480 }) ?? first.poster;
    return {
        link: [
            {
                rel: 'preload',
                as: 'image',
                href: optimized,
                fetchpriority: 'high',
            },
        ],
    };
});

useAutoReveal();
</script>

<template>
    <Head title="หน้าแรก" />
    <FrontLayout>
        <Hero v-if="heroItems.length" :items="heroItems" variant="editorial" />

        <section
            v-if="popularItems.length"
            class="reveal mx-auto mt-20 max-w-[1440px] px-6 lg:px-10"
        >
            <SectionHeader eyebrow="กำลังมาแรง" title="ยอดนิยม" />
            <Rail :items="popularItems" layout="poster" />
        </section>

        <section class="reveal mx-auto mt-24 max-w-[1440px] px-6 lg:px-10">
            <SectionHeader eyebrow="อัปเดตล่าสุด" title="ตอนใหม่ล่าสุด" />
            <StaggerGrid>
                <PosterCard
                    v-for="item in latestItems"
                    :key="item.id"
                    :item="item"
                />
            </StaggerGrid>
            <Pagination :links="anime.links" />
        </section>
    </FrontLayout>
</template>
