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
            {
                name: props.voiceActor.name,
                url: `/voice-actor/${props.voiceActor.id}`,
            },
        ]),
    ],
}));

useAutoReveal();
</script>

<template>
    <Head :title="voiceActor.name" />
    <FrontLayout>
        <section
            class="mx-auto flex max-w-[1440px] items-start gap-6 px-6 pt-16 pb-8 lg:px-10"
        >
            <img
                v-if="voiceActor.image_url"
                :src="voiceActor.image_url"
                :alt="voiceActor.name"
                class="h-28 w-28 shrink-0 rounded-full object-cover"
            />
            <div>
                <div
                    class="mb-2 font-mono text-[11px] tracking-[0.25em] uppercase"
                    style="color: hsl(var(--fg-muted))"
                >
                    นักพากย์
                </div>
                <h1
                    class="font-display mb-2 leading-none italic"
                    style="font-size: clamp(40px, 6vw, 64px)"
                >
                    {{ voiceActor.name }}
                </h1>
                <div
                    v-if="voiceActor.name_japanese"
                    class="font-display mb-1 text-[22px] italic"
                    style="color: hsl(var(--accent))"
                >
                    {{ voiceActor.name_japanese }}
                </div>
                <div
                    v-if="voiceActor.language"
                    class="chip inline-flex font-mono"
                >
                    {{ voiceActor.language }}
                </div>
            </div>
        </section>

        <section class="reveal mx-auto max-w-[1440px] px-6 lg:px-10">
            <StaggerGrid>
                <PosterCard v-for="item in items" :key="item.id" :item="item" />
            </StaggerGrid>
            <Pagination :links="anime.links" />
        </section>
    </FrontLayout>
</template>
