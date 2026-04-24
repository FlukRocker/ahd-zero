<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import FrontLayout from '@/layouts/FrontLayout.vue';
import AhdIcon from '@/components/ahd/AhdIcon.vue';
import Rail from '@/components/ahd/Rail.vue';
import SectionHeader from '@/components/ahd/SectionHeader.vue';
import { toCardItems, type AnimeRecord } from '@/lib/animeCard';
import { useAutoReveal } from '@/composables/useReveal';
import { useSeo } from '@/composables/useSeo';
import { breadcrumbJsonLd, videoObjectJsonLd } from '@/lib/schema';

type PlayerMode = 'ads' | 'direct';
const STORAGE_KEY = 'ahd.playerMode';
const ADS_EMBED_FALLBACK = 'https://anime-hdzero.com/player/embed.php';

interface AnimeSummary extends AnimeRecord {
    cat_desc?: string | null;
}

interface EpisodeItem {
    list_id: number;
    list_title: string;
    uuid?: string | null;
}

interface CurrentEpisode {
    list_id: number;
    list_title: string;
    uuid?: string | null;
    player_url?: string | null;
    upload_date_iso?: string | null;
}

const props = defineProps<{
    anime: AnimeSummary;
    currentEpisode: CurrentEpisode;
    episodes: EpisodeItem[];
    relatedAnime?: AnimeRecord[];
}>();

const page = usePage<{ playerConfig?: { adsEmbedUrl?: string | null } }>();
const adsEmbedUrl = computed(
    () => page.props.playerConfig?.adsEmbedUrl || ADS_EMBED_FALLBACK,
);

const playerMode = ref<PlayerMode>('ads');

onMounted(() => {
    const saved = localStorage.getItem(STORAGE_KEY) as PlayerMode | null;
    if (saved === 'ads' || saved === 'direct') {
        playerMode.value = saved;
    }
});

watch(playerMode, (m) => {
    if (typeof window !== 'undefined') localStorage.setItem(STORAGE_KEY, m);
});

const playerSrc = computed(() => {
    const url = props.currentEpisode.player_url;
    if (!url) return null;
    if (playerMode.value === 'ads') {
        if (typeof window === 'undefined') return null;
        const encoded = window.btoa(url);
        return `${adsEmbedUrl.value}?link=${encodeURIComponent(encoded)}`;
    }
    return url;
});

const currentIndex = computed(() =>
    props.episodes.findIndex((e) => e.list_id === props.currentEpisode.list_id),
);

const prevEp = computed(() =>
    currentIndex.value > 0 ? props.episodes[currentIndex.value - 1] : null,
);

const nextEp = computed(() =>
    currentIndex.value >= 0 && currentIndex.value < props.episodes.length - 1
        ? props.episodes[currentIndex.value + 1]
        : null,
);

const relatedCards = computed(() => toCardItems(props.relatedAnime));

const pageTitle = computed(
    () => `${props.anime.cat_title} — ${props.currentEpisode.list_title}`,
);

useSeo(() => ({
    title: pageTitle.value,
    description: `ดู ${props.anime.cat_title} — ${props.currentEpisode.list_title}`,
    image: props.anime.cat_image ?? undefined,
    type: 'video.episode',
    schema: [
        videoObjectJsonLd({
            name: pageTitle.value,
            description: props.anime.cat_desc,
            thumbnailUrl: props.anime.cat_image,
            uploadDate: props.currentEpisode.upload_date_iso,
            embedUrl: props.currentEpisode.player_url,
            partOfSeries: {
                name: props.anime.cat_title,
                url: `/anime/${props.anime.cat_id}`,
            },
        }),
        breadcrumbJsonLd([
            { name: 'หน้าแรก', url: '/' },
            { name: props.anime.cat_title, url: `/anime/${props.anime.cat_id}` },
            {
                name: props.currentEpisode.list_title,
                url: `/anime/${props.anime.cat_id}/episode/${props.currentEpisode.list_id}`,
            },
        ]),
    ],
}));

useAutoReveal();
</script>

<template>
    <Head :title="pageTitle" />
    <FrontLayout>
        <section class="max-w-[1440px] mx-auto px-6 lg:px-10 pt-10 pb-16">
            <div class="mb-6">
                <Link
                    :href="`/anime/${anime.cat_id}`"
                    class="inline-flex items-center gap-2 text-[13px] font-mono uppercase tracking-widest u-grow"
                    style="color: hsl(var(--fg-muted));"
                >
                    <AhdIcon name="back" :size="14" /> {{ anime.cat_title }}
                </Link>
                <h1 class="font-display italic leading-tight mt-3" style="font-size: clamp(32px, 4vw, 52px);">
                    {{ currentEpisode.list_title }}
                </h1>
            </div>

            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-12 lg:col-span-8">
                    <div
                        class="relative w-full overflow-hidden rounded-2xl"
                        style="aspect-ratio: 16/9; background: #000;"
                    >
                        <iframe
                            v-if="playerSrc"
                            :key="playerMode + ':' + currentEpisode.list_id"
                            :src="playerSrc"
                            referrerpolicy="strict-origin-when-cross-origin"
                            class="absolute inset-0 w-full h-full"
                            scrolling="no"
                            frameborder="0"
                            allowfullscreen
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        />
                        <div
                            v-else
                            class="absolute inset-0 flex items-center justify-center text-white/60"
                        >
                            ไม่สามารถเล่นได้
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 mt-5">
                        <Link
                            v-if="prevEp"
                            :href="`/anime/${anime.cat_id}/episode/${prevEp.list_id}`"
                            class="btn btn-ghost"
                        >
                            <AhdIcon name="back" :size="14" /> ก่อนหน้า
                        </Link>
                        <Link
                            v-if="nextEp"
                            :href="`/anime/${anime.cat_id}/episode/${nextEp.list_id}`"
                            class="btn btn-primary"
                        >
                            ถัดไป <AhdIcon name="skip" :size="14" />
                        </Link>

                        <div class="seg ml-auto">
                            <button
                                type="button"
                                :class="playerMode === 'ads' ? 'on' : ''"
                                @click="playerMode = 'ads'"
                            >มีโฆษณา</button>
                            <button
                                type="button"
                                :class="playerMode === 'direct' ? 'on' : ''"
                                @click="playerMode = 'direct'"
                            >ไม่มีโฆษณา</button>
                        </div>
                    </div>

                    <div v-if="anime.cat_desc" class="mt-8">
                        <div
                            class="font-mono text-[10px] tracking-[0.22em] uppercase mb-2"
                            style="color: hsl(var(--fg-faint));"
                        >เรื่องย่อ</div>
                        <div
                            class="text-[14px] max-w-3xl anime-desc"
                            style="color: hsl(var(--fg-muted));"
                            v-html="anime.cat_desc"
                        />
                    </div>
                </div>

                <aside class="col-span-12 lg:col-span-4">
                    <div
                        class="rounded-2xl p-4"
                        style="background: hsl(var(--bg-elev)); border: 1px solid hsl(var(--border-ahd)); max-height: 640px; overflow: auto;"
                    >
                        <div
                            class="font-mono text-[10px] tracking-[0.22em] uppercase mb-3"
                            style="color: hsl(var(--fg-faint));"
                        >รายการตอน</div>
                        <ul class="space-y-1">
                            <li v-for="ep in episodes" :key="ep.list_id">
                                <Link
                                    :href="`/anime/${anime.cat_id}/episode/${ep.list_id}`"
                                    class="ep-row flex items-center gap-3 p-2 rounded-lg text-[13px]"
                                    :style="
                                        ep.list_id === currentEpisode.list_id
                                            ? 'background: hsl(var(--accent) / 0.15); color: hsl(var(--fg));'
                                            : ''
                                    "
                                >
                                    <AhdIcon name="play" :size="12" />
                                    <span class="truncate">{{ ep.list_title }}</span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                </aside>
            </div>
        </section>

        <section v-if="relatedCards.length" class="max-w-[1440px] mx-auto px-6 lg:px-10 mt-8 reveal">
            <SectionHeader eyebrow="คุณอาจชอบ" title="อนิเมะที่เกี่ยวข้อง" />
            <Rail :items="relatedCards" layout="poster" />
        </section>
    </FrontLayout>
</template>
