<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import FrontLayout from '@/layouts/FrontLayout.vue';
import Hero from '@/components/ahd/Hero.vue';
import SectionHeader from '@/components/ahd/SectionHeader.vue';
import Rail from '@/components/ahd/Rail.vue';
import PosterCard from '@/components/ahd/PosterCard.vue';
import StaggerGrid from '@/components/ahd/StaggerGrid.vue';
import Pagination from '@/components/Pagination.vue';
import { toCardItem, toCardItems, type AnimeRecord } from '@/lib/animeCard';
import { useAutoReveal } from '@/composables/useReveal';
import { useSeo, useAppMeta } from '@/composables/useSeo';
import { siteJsonLd, organizationJsonLd } from '@/lib/schema';

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
    const source = (props.recommended && props.recommended.length >= 3
        ? props.recommended
        : props.anime.data
    ).slice(0, 5);
    return source.map(toCardItem);
});

const popularItems = computed(() => toCardItems(props.popular ?? props.anime.data.slice(0, 10)));
const latestItems = computed(() => toCardItems(props.anime.data));

const { appName, appUrl } = useAppMeta();

useSeo(() => ({
    title: 'ดูอนิเมะออนไลน์',
    description: 'ดูอนิเมะออนไลน์ ทั้งซับไทย พากย์ไทย เดอะมูฟวี่ คุณภาพ HD รับชมได้ทุกเรื่อง',
    type: 'website',
    schema: [
        siteJsonLd(appName.value, appUrl.value),
        organizationJsonLd(appName.value, appUrl.value),
    ],
}));

useAutoReveal();
</script>

<template>
    <Head title="หน้าแรก" />
    <FrontLayout>
        <Hero v-if="heroItems.length" :items="heroItems" variant="editorial" />

        <section v-if="popularItems.length" class="max-w-[1440px] mx-auto px-6 lg:px-10 mt-20 reveal">
            <SectionHeader eyebrow="กำลังมาแรง" title="ยอดนิยม" />
            <Rail :items="popularItems" layout="poster" />
        </section>

        <section class="max-w-[1440px] mx-auto px-6 lg:px-10 mt-24 reveal">
            <SectionHeader eyebrow="อัปเดตล่าสุด" title="ตอนใหม่ล่าสุด" />
            <StaggerGrid>
                <PosterCard v-for="item in latestItems" :key="item.id" :item="item" />
            </StaggerGrid>
            <Pagination :links="anime.links" />
        </section>
    </FrontLayout>
</template>
