<script setup lang="ts">
import AdsBanner, {
    type BannerItem,
} from '@/components/ahd/AdsBanner.vue';
import AhdIcon from '@/components/ahd/AhdIcon.vue';
import PlayerAdSlot, {
    type PlayerAdItem,
} from '@/components/ahd/PlayerAdSlot.vue';
import Rail from '@/components/ahd/Rail.vue';
import SectionHeader from '@/components/ahd/SectionHeader.vue';
import CommentSection from '@/components/comments/CommentSection.vue';
import { useAutoReveal } from '@/composables/useReveal';
import { useSeo } from '@/composables/useSeo';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { toCardItems, type AnimeRecord } from '@/lib/animeCard';
import { breadcrumbJsonLd, videoObjectJsonLd } from '@/lib/schema';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

type PlayerMode = 'ads' | 'direct';
const STORAGE_KEY = 'ahd.playerMode';
const ADS_EMBED_FALLBACK = 'https://animehdzero.net/player/embed.php';

// SSR-safe base64. Browsers + Node 18+ both expose `btoa` globally; Buffer is
// the explicit Node fallback. Keeps SSR + client output identical so Vue
// doesn't hydrate-mismatch on the iframe src.
function b64encode(s: string): string {
    if (typeof globalThis.btoa === 'function') return globalThis.btoa(s);
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const B = (globalThis as any).Buffer;
    if (B?.from) return B.from(s, 'utf8').toString('base64');
    return s;
}

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
    adsBanners?: BannerItem[];
    playerAds?: { top: PlayerAdItem | null; bottom: PlayerAdItem | null };
}>();

const page = usePage<{ playerConfig?: { adsEmbedUrl?: string | null } }>();
// Force https so a misconfigured http:// env value can't trigger a
// mixed-content block when the page itself is served over https.
function forceHttps(u: string): string {
    return u.replace(/^http:\/\//i, 'https://');
}
const adsEmbedUrl = computed(() =>
    forceHttps(page.props.playerConfig?.adsEmbedUrl || ADS_EMBED_FALLBACK),
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
        const encoded = b64encode(url);
        return `${adsEmbedUrl.value}?link=${encodeURIComponent(encoded)}`;
    }
    return forceHttps(url);
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

// iOS Safari resets scroll on Inertia partial reloads even with
// preserve-scroll — capture window.scrollY pre-visit, restore in onSuccess
// and onFinish (double-tap because iOS sometimes restores between).
function goEpisode(e: MouseEvent, listId: number) {
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
    e.preventDefault();
    const y = window.scrollY;
    const restore = () => window.scrollTo(0, y);
    router.visit(`/anime/${props.anime.cat_id}/episode/${listId}`, {
        preserveScroll: true,
        preserveState: true,
        only: ['currentEpisode'],
        onSuccess: restore,
        onFinish: restore,
    });
}

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
            {
                name: props.anime.cat_title,
                url: `/anime/${props.anime.cat_id}`,
            },
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
        <section
            v-if="adsBanners && adsBanners.length"
            class="mx-auto mt-6 max-w-[1440px] px-6 lg:px-10"
        >
            <AdsBanner :banners="adsBanners" />
        </section>

        <section class="mx-auto max-w-[1440px] px-6 pt-10 pb-16 lg:px-10">
            <div class="mb-6">
                <Link
                    :href="`/anime/${anime.cat_id}`"
                    class="u-grow inline-flex items-center gap-2 font-mono text-[13px] tracking-widest uppercase"
                    style="color: hsl(var(--fg-muted))"
                >
                    <AhdIcon name="back" :size="14" /> {{ anime.cat_title }}
                </Link>
                <h1
                    class="font-display mt-3 leading-tight italic"
                    style="font-size: clamp(32px, 4vw, 52px)"
                >
                    {{ currentEpisode.list_title }}
                </h1>
            </div>

            <div class="grid grid-cols-12 gap-6">
                <div class="col-span-12 lg:col-span-8">
                    <PlayerAdSlot
                        v-if="playerAds?.top"
                        :ad="playerAds.top"
                        class="mb-3"
                    />

                    <div
                        class="relative w-full overflow-hidden rounded-2xl"
                        style="aspect-ratio: 16/9; background: #000"
                    >
                        <iframe
                            v-if="playerSrc"
                            :key="playerMode + ':' + currentEpisode.list_id"
                            :src="playerSrc"
                            referrerpolicy="strict-origin-when-cross-origin"
                            class="absolute inset-0 h-full w-full"
                            scrolling="no"
                            frameborder="0"
                            allowfullscreen
                            allow="
                                accelerometer;
                                autoplay;
                                clipboard-write;
                                encrypted-media;
                                gyroscope;
                                picture-in-picture;
                            "
                        />
                        <div
                            v-else
                            class="absolute inset-0 flex items-center justify-center text-white/60"
                        >
                            ไม่สามารถเล่นได้
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap items-center gap-3">
                        <a
                            v-if="prevEp"
                            :href="`/anime/${anime.cat_id}/episode/${prevEp.list_id}`"
                            class="btn btn-ghost"
                            @click="goEpisode($event, prevEp.list_id)"
                        >
                            <AhdIcon name="back" :size="14" /> ก่อนหน้า
                        </a>
                        <a
                            v-if="nextEp"
                            :href="`/anime/${anime.cat_id}/episode/${nextEp.list_id}`"
                            class="btn btn-primary"
                            @click="goEpisode($event, nextEp.list_id)"
                        >
                            ถัดไป <AhdIcon name="skip" :size="14" />
                        </a>

                        <div class="seg ml-auto">
                            <button
                                type="button"
                                :class="playerMode === 'ads' ? 'on' : ''"
                                @click="playerMode = 'ads'"
                            >
                                ตัวเล่นหลัก
                            </button>
                            <button
                                type="button"
                                :class="playerMode === 'direct' ? 'on' : ''"
                                @click="playerMode = 'direct'"
                            >
                                ตัวเล่นสำรอง
                            </button>
                        </div>
                    </div>

                    <PlayerAdSlot
                        v-if="playerAds?.bottom"
                        :ad="playerAds.bottom"
                        class="mt-4"
                    />

                    <div v-if="anime.cat_desc" class="mt-8">
                        <div
                            class="mb-2 font-mono text-[10px] tracking-[0.22em] uppercase"
                            style="color: hsl(var(--fg-faint))"
                        >
                            เรื่องย่อ
                        </div>
                        <div
                            class="anime-desc max-w-3xl text-[14px]"
                            style="color: hsl(var(--fg-muted))"
                            v-html="anime.cat_desc"
                        />
                    </div>
                </div>

                <aside class="col-span-12 lg:col-span-4">
                    <div
                        class="rounded-2xl p-4"
                        style="
                            background: hsl(var(--bg-elev));
                            border: 1px solid hsl(var(--border-ahd));
                            max-height: 640px;
                            overflow: auto;
                        "
                    >
                        <div
                            class="mb-3 font-mono text-[10px] tracking-[0.22em] uppercase"
                            style="color: hsl(var(--fg-faint))"
                        >
                            รายการตอน
                        </div>
                        <ul class="space-y-1">
                            <li v-for="ep in episodes" :key="ep.list_id">
                                <a
                                    :href="`/anime/${anime.cat_id}/episode/${ep.list_id}`"
                                    class="ep-row flex items-center gap-3 rounded-lg p-2 text-[13px]"
                                    :style="
                                        ep.list_id === currentEpisode.list_id
                                            ? 'background: hsl(var(--accent) / 0.15); color: hsl(var(--fg));'
                                            : ''
                                    "
                                    @click="goEpisode($event, ep.list_id)"
                                >
                                    <AhdIcon name="play" :size="12" />
                                    <span class="truncate">{{
                                        ep.list_title
                                    }}</span>
                                </a>
                            </li>
                        </ul>
                    </div>

                </aside>
            </div>
        </section>

        <section
            v-if="relatedCards.length"
            class="reveal mx-auto mt-8 max-w-[1440px] px-6 lg:px-10"
        >
            <SectionHeader eyebrow="คุณอาจชอบ" title="อนิเมะที่เกี่ยวข้อง" />
            <Rail :items="relatedCards" layout="poster" />
        </section>

        <section class="mx-auto mt-12 mb-20 max-w-[1440px] px-6 lg:px-10">
            <CommentSection
                commentable-type="episode"
                :commentable-id="currentEpisode.list_id"
            />
        </section>
    </FrontLayout>
</template>
