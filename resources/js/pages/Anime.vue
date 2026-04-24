<script setup lang="ts">
import AhdIcon from '@/components/ahd/AhdIcon.vue';
import Rail from '@/components/ahd/Rail.vue';
import SectionHeader from '@/components/ahd/SectionHeader.vue';
import { useAutoReveal } from '@/composables/useReveal';
import { useSeo } from '@/composables/useSeo';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { toCardItems, type AnimeRecord } from '@/lib/animeCard';
import { breadcrumbJsonLd, tvSeriesJsonLd } from '@/lib/schema';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface EpisodeRef {
    list_id: number;
    list_title: string;
    uuid?: string | null;
}

interface GenreRef {
    id: number;
    name: string;
    name_th?: string | null;
    slug?: string | null;
}

interface StudioRef {
    id: number;
    name: string;
}

interface CharacterRef {
    id: number;
    name: string;
    name_japanese?: string | null;
    image_url?: string | null;
    role?: string | null;
    voice_actor?: {
        id: number;
        name: string;
        language?: string | null;
        image_url?: string | null;
    } | null;
}

interface AnimeDetail extends AnimeRecord {
    cat_desc?: string | null;
    banner_original?: string | null;
    aired_from?: string | null;
    aired_to?: string | null;
    aired_from_iso?: string | null;
    aired_to_iso?: string | null;
    cat_update_iso?: string | null;
    premiered_season?: string | null;
    premiered_year?: number | null;
    broadcast?: string | null;
    source?: string | null;
    duration?: string | null;
    rating?: string | null;
    anime_slug?: string | null;
    genres?: GenreRef[];
    studios?: StudioRef[];
    producers?: StudioRef[];
    licensors?: StudioRef[];
    characters?: CharacterRef[];
    staff?: Array<{
        id: number;
        name: string;
        position?: string | null;
        image_url?: string | null;
    }>;
    episode_list?: EpisodeRef[];
    series_anime?: AnimeRecord[];
    related_anime?: Array<{
        id: number;
        relation_type?: string | null;
        related_title?: string | null;
        related_image?: string | null;
        related_anime_id?: number | null;
    }>;
}

const props = defineProps<{ anime: AnimeDetail }>();

const firstEp = computed(() => props.anime.episode_list?.[0]);
const heroImg = computed(
    () =>
        props.anime.banner_md ||
        props.anime.banner_original ||
        props.anime.cat_image ||
        '',
);

const seriesCards = computed(() => toCardItems(props.anime.series_anime));

useSeo(() => ({
    title: props.anime.cat_title,
    description: (
        props.anime.cat_desc ?? `ดู ${props.anime.cat_title} ออนไลน์`
    ).slice(0, 200),
    image: props.anime.cat_image ?? undefined,
    type: 'video.tv_show',
    schema: [
        tvSeriesJsonLd({
            name: props.anime.cat_title,
            alternateName: props.anime.title_japanese,
            description: props.anime.cat_desc,
            image: props.anime.cat_image,
            url: `/anime/${props.anime.cat_id}`,
            numberOfEpisodes: props.anime.episodes,
            startDate: props.anime.aired_from_iso ?? undefined,
            endDate: props.anime.aired_to_iso ?? undefined,
            genre: props.anime.genres?.map((g) => g.name),
            productionCompany: props.anime.studios?.map((s) => ({
                name: s.name,
            })),
        }),
        breadcrumbJsonLd([
            { name: 'หน้าแรก', url: '/' },
            {
                name: props.anime.cat_title,
                url: `/anime/${props.anime.cat_id}`,
            },
        ]),
    ],
}));

useAutoReveal();
</script>

<template>
    <Head :title="anime.cat_title" />
    <FrontLayout>
        <section class="relative overflow-hidden">
            <div v-if="heroImg" class="absolute inset-0">
                <img
                    :src="heroImg"
                    class="h-full w-full object-cover opacity-60"
                    :alt="anime.cat_title"
                />
                <div class="grad-hero" />
            </div>
            <div
                class="relative mx-auto grid max-w-[1440px] grid-cols-12 gap-8 px-6 pt-28 pb-16 lg:px-10"
            >
                <div class="col-span-12 md:col-span-4 lg:col-span-3">
                    <div
                        class="poster-card"
                        style="box-shadow: 0 40px 80px -30px rgba(0, 0, 0, 0.5)"
                    >
                        <img
                            v-if="anime.cover_md || anime.cat_image"
                            :src="anime.cover_md ?? anime.cat_image ?? ''"
                            :alt="anime.cat_title"
                        />
                    </div>
                </div>
                <div class="col-span-12 md:col-span-8 lg:col-span-9">
                    <div
                        v-if="anime.anime_type || anime.premiered_season"
                        class="mb-3 font-mono text-[11px] tracking-[0.25em] uppercase"
                        style="color: hsl(var(--fg-muted))"
                    >
                        {{
                            [
                                anime.anime_type,
                                anime.premiered_season,
                                anime.premiered_year,
                            ]
                                .filter(Boolean)
                                .join(' · ')
                        }}
                    </div>
                    <h1
                        class="font-display mb-4 leading-[0.95] italic"
                        style="
                            font-size: clamp(42px, 6vw, 72px);
                            text-wrap: balance;
                        "
                    >
                        {{ anime.cat_title }}
                    </h1>
                    <div
                        v-if="anime.title_japanese"
                        class="font-display mb-6 text-[26px] italic"
                        style="color: hsl(var(--accent))"
                    >
                        {{ anime.title_japanese }}
                    </div>

                    <div class="mb-6 flex flex-wrap items-center gap-3">
                        <span
                            v-if="anime.anime_status"
                            class="chip chip-accent font-mono"
                            >{{ anime.anime_status }}</span
                        >
                        <span v-if="anime.episodes" class="chip font-mono"
                            >{{ anime.episodes }} EP</span
                        >
                        <span v-if="anime.duration" class="chip font-mono">{{
                            anime.duration
                        }}</span>
                        <span v-if="anime.rating" class="chip font-mono">{{
                            anime.rating
                        }}</span>
                    </div>

                    <div
                        v-if="anime.cat_desc"
                        class="anime-desc mb-6 max-w-3xl text-[15px]"
                        style="color: hsl(var(--fg-muted))"
                        v-html="anime.cat_desc"
                    />

                    <div class="mb-8 flex flex-wrap items-center gap-3">
                        <Link
                            v-if="firstEp"
                            :href="`/anime/${anime.cat_id}/episode/${firstEp.list_id}`"
                            class="btn btn-primary"
                        >
                            <AhdIcon name="play" :size="14" /> ดูตอนนี้
                        </Link>
                        <button class="btn btn-ghost" type="button">
                            <AhdIcon name="plus" :size="14" /> เพิ่มในรายการ
                        </button>
                    </div>

                    <dl
                        class="grid grid-cols-2 gap-x-6 gap-y-3 text-[13px] md:grid-cols-3"
                        style="color: hsl(var(--fg-muted))"
                    >
                        <div v-if="anime.aired_from">
                            <dt
                                class="font-mono text-[10px] tracking-widest uppercase opacity-70"
                            >
                                ออกอากาศ
                            </dt>
                            <dd>
                                {{ anime.aired_from
                                }}<span v-if="anime.aired_to">
                                    — {{ anime.aired_to }}</span
                                >
                            </dd>
                        </div>
                        <div v-if="anime.broadcast">
                            <dt
                                class="font-mono text-[10px] tracking-widest uppercase opacity-70"
                            >
                                ตารางออกอากาศ
                            </dt>
                            <dd>{{ anime.broadcast }}</dd>
                        </div>
                        <div v-if="anime.source">
                            <dt
                                class="font-mono text-[10px] tracking-widest uppercase opacity-70"
                            >
                                ต้นฉบับ
                            </dt>
                            <dd>{{ anime.source }}</dd>
                        </div>
                        <div v-if="anime.studios && anime.studios.length">
                            <dt
                                class="font-mono text-[10px] tracking-widest uppercase opacity-70"
                            >
                                สตูดิโอ
                            </dt>
                            <dd>
                                <template
                                    v-for="(s, i) in anime.studios"
                                    :key="s.id"
                                >
                                    <Link
                                        :href="`/studio/${s.id}`"
                                        class="u-grow"
                                        >{{ s.name }}</Link
                                    >
                                    <span v-if="i < anime.studios!.length - 1"
                                        >,
                                    </span>
                                </template>
                            </dd>
                        </div>
                        <div
                            v-if="anime.genres && anime.genres.length"
                            class="col-span-2 md:col-span-3"
                        >
                            <dt
                                class="font-mono text-[10px] tracking-widest uppercase opacity-70"
                            >
                                หมวดหมู่
                            </dt>
                            <dd class="mt-1 flex flex-wrap gap-2">
                                <span
                                    v-for="g in anime.genres"
                                    :key="g.id"
                                    class="chip font-mono"
                                    >{{ g.name }}</span
                                >
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </section>

        <section
            v-if="anime.episode_list && anime.episode_list.length"
            class="reveal mx-auto mt-10 max-w-[1440px] px-6 lg:px-10"
        >
            <SectionHeader
                eyebrow="สตรีม"
                :title="`ตอนทั้งหมด (${anime.episode_list.length})`"
            />
            <div
                class="grid gap-2"
                style="
                    grid-template-columns: repeat(
                        auto-fill,
                        minmax(240px, 1fr)
                    );
                "
            >
                <Link
                    v-for="ep in anime.episode_list"
                    :key="ep.list_id"
                    :href="`/anime/${anime.cat_id}/episode/${ep.list_id}`"
                    class="ep-row flex items-center gap-3 rounded-lg p-3"
                    style="
                        background: hsl(var(--bg-elev));
                        border: 1px solid hsl(var(--border-ahd));
                    "
                >
                    <AhdIcon name="play" :size="14" />
                    <span class="text-[13px]">{{ ep.list_title }}</span>
                </Link>
            </div>
        </section>

        <section
            v-if="anime.characters && anime.characters.length"
            class="reveal mx-auto mt-20 max-w-[1440px] px-6 lg:px-10"
        >
            <SectionHeader eyebrow="นักแสดง" title="ตัวละครและนักพากย์" />
            <div
                class="grid gap-4"
                style="
                    grid-template-columns: repeat(
                        auto-fill,
                        minmax(260px, 1fr)
                    );
                "
            >
                <div
                    v-for="c in anime.characters.slice(0, 12)"
                    :key="c.id"
                    class="flex items-center gap-3 rounded-xl p-3"
                    style="
                        background: hsl(var(--bg-elev));
                        border: 1px solid hsl(var(--border-ahd));
                    "
                >
                    <img
                        v-if="c.image_url"
                        :src="c.image_url"
                        :alt="c.name"
                        class="h-12 w-12 shrink-0 rounded-full object-cover"
                        loading="lazy"
                    />
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium">{{ c.name }}</div>
                        <div
                            v-if="c.voice_actor"
                            class="truncate text-[12px]"
                            style="color: hsl(var(--fg-muted))"
                        >
                            พากย์โดย
                            <Link
                                :href="`/voice-actor/${c.voice_actor.id}`"
                                class="u-grow"
                                >{{ c.voice_actor.name }}</Link
                            >
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section
            v-if="seriesCards.length"
            class="reveal mx-auto mt-20 max-w-[1440px] px-6 lg:px-10"
        >
            <SectionHeader eyebrow="ซีรีส์" title="ภาคอื่นในซีรีส์" />
            <Rail :items="seriesCards" layout="poster" />
        </section>
    </FrontLayout>
</template>
