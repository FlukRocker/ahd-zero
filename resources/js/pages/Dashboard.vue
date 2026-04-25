<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

interface AnimeRow {
    cat_id: number;
    cat_title: string;
    cat_image?: string | null;
    cat_type?: number | null;
    cat_update?: string | null;
    anime_status?: string | null;
}

interface MemberRow {
    uuid: string;
    name: string;
    email: string;
    created_at: string | null;
}

interface TrendingRow {
    cat_id: number;
    cat_title: string;
    cat_image: string | null;
    cat_type: number;
    views: number;
}

interface ReferrerRow {
    domain: string;
    count: number;
}

interface ViewsPoint {
    date: string;
    views: number;
}

const props = defineProps<{
    stats: {
        totalAnime: number;
        totalEpisodes: number;
        totalMembers: number;
        animeByType: { sub: number; dub: number; movie: number };
        views: { total: number; today: number; week: number; month: number };
    };
    recentAnime: AnimeRow[];
    recentMembers: MemberRow[];
    trendingAnime: TrendingRow[];
    topReferrers: ReferrerRow[];
    viewsOverTime: ViewsPoint[];
}>();

// Sparkline scaling — viewsOverTime is up to 31 daily points; normalize
// against the largest day so a tiny range still produces visible bars.
const maxDailyViews = computed(() => {
    let m = 0;
    for (const p of props.viewsOverTime) if (p.views > m) m = p.views;
    return m || 1;
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
];
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div
                    class="rounded-xl p-5"
                    style="
                        background: hsl(var(--bg-elev));
                        border: 1px solid hsl(var(--border-ahd));
                    "
                >
                    <div
                        class="font-mono text-[10px] tracking-widest uppercase"
                        style="color: hsl(var(--fg-faint))"
                    >
                        Anime
                    </div>
                    <div
                        class="font-display mt-2 italic"
                        style="font-size: 40px"
                    >
                        {{ stats.totalAnime.toLocaleString() }}
                    </div>
                </div>
                <div
                    class="rounded-xl p-5"
                    style="
                        background: hsl(var(--bg-elev));
                        border: 1px solid hsl(var(--border-ahd));
                    "
                >
                    <div
                        class="font-mono text-[10px] tracking-widest uppercase"
                        style="color: hsl(var(--fg-faint))"
                    >
                        Episodes
                    </div>
                    <div
                        class="font-display mt-2 italic"
                        style="font-size: 40px"
                    >
                        {{ stats.totalEpisodes.toLocaleString() }}
                    </div>
                </div>
                <div
                    class="rounded-xl p-5"
                    style="
                        background: hsl(var(--bg-elev));
                        border: 1px solid hsl(var(--border-ahd));
                    "
                >
                    <div
                        class="font-mono text-[10px] tracking-widest uppercase"
                        style="color: hsl(var(--fg-faint))"
                    >
                        Members
                    </div>
                    <div
                        class="font-display mt-2 italic"
                        style="font-size: 40px"
                    >
                        {{ stats.totalMembers.toLocaleString() }}
                    </div>
                </div>
                <div
                    class="rounded-xl p-5"
                    style="
                        background: hsl(var(--bg-elev));
                        border: 1px solid hsl(var(--border-ahd));
                    "
                >
                    <div
                        class="mb-2 font-mono text-[10px] tracking-widest uppercase"
                        style="color: hsl(var(--fg-faint))"
                    >
                        By type
                    </div>
                    <div class="flex gap-4 text-[13px]">
                        <div>
                            <span class="chip font-mono">SUB</span>
                            <span class="ml-1">{{
                                stats.animeByType.sub
                            }}</span>
                        </div>
                        <div>
                            <span class="chip font-mono">DUB</span>
                            <span class="ml-1">{{
                                stats.animeByType.dub
                            }}</span>
                        </div>
                        <div>
                            <span class="chip font-mono">MOV</span>
                            <span class="ml-1">{{
                                stats.animeByType.movie
                            }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div
                    v-for="(label, key) in {
                        total: 'Page views (all)',
                        today: 'Today',
                        week: 'Last 7d',
                        month: 'Last 30d',
                    }"
                    :key="key"
                    class="rounded-xl p-5"
                    style="
                        background: hsl(var(--bg-elev));
                        border: 1px solid hsl(var(--border-ahd));
                    "
                >
                    <div
                        class="font-mono text-[10px] tracking-widest uppercase"
                        style="color: hsl(var(--fg-faint))"
                    >
                        {{ label }}
                    </div>
                    <div
                        class="font-display mt-2 italic"
                        style="font-size: 32px"
                    >
                        {{ stats.views[key].toLocaleString() }}
                    </div>
                </div>
            </div>

            <section
                v-if="viewsOverTime.length"
                class="rounded-xl p-5"
                style="
                    background: hsl(var(--bg-elev));
                    border: 1px solid hsl(var(--border-ahd));
                "
            >
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-display text-[22px] italic">
                        Views — last 30 days
                    </h2>
                    <span
                        class="font-mono text-[11px]"
                        style="color: hsl(var(--fg-faint))"
                    >
                        peak {{ maxDailyViews.toLocaleString() }}/day
                    </span>
                </div>
                <div
                    class="flex items-end gap-1"
                    style="height: 100px"
                >
                    <div
                        v-for="p in viewsOverTime"
                        :key="p.date"
                        class="flex-1 rounded-sm"
                        :style="{
                            height: `${Math.max(2, (p.views / maxDailyViews) * 100)}%`,
                            background: 'hsl(var(--accent) / 0.7)',
                        }"
                        :title="`${p.date} — ${p.views.toLocaleString()} views`"
                    />
                </div>
            </section>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <section
                    v-if="trendingAnime.length"
                    class="rounded-xl p-5"
                    style="
                        background: hsl(var(--bg-elev));
                        border: 1px solid hsl(var(--border-ahd));
                    "
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-display text-[22px] italic">
                            Trending — last 7d
                        </h2>
                    </div>
                    <ul class="space-y-2">
                        <li v-for="(a, i) in trendingAnime" :key="a.cat_id">
                            <Link
                                :href="`/anime/${a.cat_id}`"
                                class="flex items-center gap-3 rounded-lg p-2 text-[13px]"
                                style="background: hsl(var(--bg-soft))"
                            >
                                <span
                                    class="font-display w-6 shrink-0 text-center italic"
                                    style="color: hsl(var(--fg-faint))"
                                >
                                    #{{ i + 1 }}
                                </span>
                                <img
                                    v-if="a.cat_image"
                                    :src="a.cat_image"
                                    class="h-14 w-10 shrink-0 rounded object-cover"
                                    :alt="a.cat_title"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate">{{ a.cat_title }}</div>
                                </div>
                                <span
                                    class="font-mono text-[12px]"
                                    style="color: hsl(var(--accent))"
                                >
                                    {{ a.views.toLocaleString() }}
                                </span>
                            </Link>
                        </li>
                    </ul>
                </section>

                <section
                    v-if="topReferrers.length"
                    class="rounded-xl p-5"
                    style="
                        background: hsl(var(--bg-elev));
                        border: 1px solid hsl(var(--border-ahd));
                    "
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-display text-[22px] italic">
                            Top referrers — 30d
                        </h2>
                    </div>
                    <ul class="space-y-2">
                        <li
                            v-for="r in topReferrers"
                            :key="r.domain"
                            class="flex items-center justify-between rounded-lg p-2 text-[13px]"
                            style="background: hsl(var(--bg-soft))"
                        >
                            <span class="truncate font-mono text-[12px]">{{ r.domain }}</span>
                            <span
                                class="font-mono text-[12px]"
                                style="color: hsl(var(--accent))"
                            >
                                {{ r.count.toLocaleString() }}
                            </span>
                        </li>
                    </ul>
                </section>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <section
                    class="rounded-xl p-5"
                    style="
                        background: hsl(var(--bg-elev));
                        border: 1px solid hsl(var(--border-ahd));
                    "
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-display text-[22px] italic">
                            Recent anime
                        </h2>
                        <Link
                            href="/"
                            class="u-grow font-mono text-[12px]"
                            style="color: hsl(var(--fg-muted))"
                            >View site →</Link
                        >
                    </div>
                    <ul class="space-y-2">
                        <li v-for="a in recentAnime" :key="a.cat_id">
                            <Link
                                :href="`/anime/${a.cat_id}`"
                                class="flex items-center gap-3 rounded-lg p-2 text-[13px]"
                                style="background: hsl(var(--bg-soft))"
                            >
                                <img
                                    v-if="a.cat_image"
                                    :src="a.cat_image"
                                    class="h-14 w-10 shrink-0 rounded object-cover"
                                    :alt="a.cat_title"
                                />
                                <div class="min-w-0 flex-1">
                                    <div class="truncate">
                                        {{ a.cat_title }}
                                    </div>
                                    <div
                                        class="font-mono text-[11px]"
                                        style="color: hsl(var(--fg-faint))"
                                    >
                                        {{ a.anime_status ?? '—' }}
                                    </div>
                                </div>
                            </Link>
                        </li>
                    </ul>
                </section>

                <section
                    class="rounded-xl p-5"
                    style="
                        background: hsl(var(--bg-elev));
                        border: 1px solid hsl(var(--border-ahd));
                    "
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-display text-[22px] italic">
                            Recent members
                        </h2>
                        <Link
                            href="/dashboard/members"
                            class="u-grow font-mono text-[12px]"
                            style="color: hsl(var(--fg-muted))"
                        >
                            Manage →
                        </Link>
                    </div>
                    <ul class="space-y-2">
                        <li
                            v-for="m in recentMembers"
                            :key="m.uuid"
                            class="rounded-lg p-2 text-[13px]"
                            style="background: hsl(var(--bg-soft))"
                        >
                            <div class="font-medium">{{ m.name }}</div>
                            <div
                                class="font-mono text-[11px]"
                                style="color: hsl(var(--fg-faint))"
                            >
                                {{ m.email }}
                            </div>
                        </li>
                        <li
                            v-if="!recentMembers.length"
                            class="font-mono text-[12px] opacity-60"
                        >
                            No members yet.
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
