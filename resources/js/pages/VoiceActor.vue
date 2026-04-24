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
    voiceActor: {
        id: number;
        name: string;
        name_japanese?: string | null;
        image_url?: string | null;
        language?: string | null;
        mal_id?: number | null;
    };
    anime: Paginator<AnimeRecord>;
}>();

const items = computed(() => toCardItems(props.anime.data));

useSeo(() => ({
    title: props.voiceActor.name,
    description: `อนิเมะที่นักพากย์ ${props.voiceActor.name} ร่วมพากย์`,
    image: props.voiceActor.image_url ?? undefined,
    type: 'website',
    schema: [
        breadcrumbJsonLd([
            { name: 'หน้าแรก', url: '/' },
            { name: 'นักพากย์', url: '/voice-actors' },
            { name: props.voiceActor.name, url: `/voice-actor/${props.voiceActor.id}` },
        ]),
    ],
}));

useAutoReveal();
</script>

<template>
    <Head :title="voiceActor.name" />
    <FrontLayout>
        <section class="max-w-[1440px] mx-auto px-6 lg:px-10 pt-16 pb-8 flex items-start gap-6">
            <img
                v-if="voiceActor.image_url"
                :src="voiceActor.image_url"
                :alt="voiceActor.name"
                class="w-28 h-28 rounded-full object-cover shrink-0"
            />
            <div>
                <div class="font-mono text-[11px] tracking-[0.25em] uppercase mb-2" style="color: hsl(var(--fg-muted));">
                    นักพากย์
                </div>
                <h1 class="font-display italic leading-none mb-2" style="font-size: clamp(40px, 6vw, 64px);">
                    {{ voiceActor.name }}
                </h1>
                <div v-if="voiceActor.name_japanese" class="font-display text-[22px] italic mb-1" style="color: hsl(var(--accent));">
                    {{ voiceActor.name_japanese }}
                </div>
                <div v-if="voiceActor.language" class="chip font-mono inline-flex">{{ voiceActor.language }}</div>
            </div>
        </section>

        <section class="max-w-[1440px] mx-auto px-6 lg:px-10 reveal">
            <StaggerGrid>
                <PosterCard v-for="item in items" :key="item.id" :item="item" />
            </StaggerGrid>
            <Pagination :links="anime.links" />
        </section>
    </FrontLayout>
</template>
