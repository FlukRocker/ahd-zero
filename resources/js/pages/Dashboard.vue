<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
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

interface CommentRow {
    id: string;
    user_name: string;
    body: string;
    created_at: string | null;
    deleted: boolean;
}

interface ChatRow {
    id: number;
    room: string | null;
    member_name: string | null;
    body: string;
    created_at: string | null;
}

interface ModRow {
    action: string;
    target_member_name: string | null;
    created_at: string | null;
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
    latestComments: CommentRow[] | null;
    recentChat: ChatRow[];
    modLog: ModRow[];
    openReports: number;
}>();

function csrf(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

// Inline moderation on the latest-comments block. Same endpoints the
// Comments page uses; reload afterwards so the row shows as deleted.
async function moderateComment(id: string, ban: boolean) {
    if (ban && !window.confirm('Delete this comment and ban its author?')) {
        return;
    }

    await fetch(
        ban
            ? `/dashboard/comments/${id}/delete-and-ban`
            : `/dashboard/comments/${id}`,
        {
            method: ban ? 'POST' : 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': csrf(),
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            body: ban
                ? JSON.stringify({ ban_reason: '', delete_all: false })
                : undefined,
        },
    );
    router.reload();
}

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
                <div class="flex items-end gap-1" style="height: 100px">
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

            <section
                class="flex items-center justify-between rounded-xl p-5"
                style="
                    background: hsl(var(--bg-elev));
                    border: 1px solid hsl(var(--border-ahd));
                "
            >
                <div>
                    <h2 class="font-display text-[22px] italic">
                        Needs attention
                    </h2>
                    <p
                        class="text-sm"
                        style="color: hsl(var(--fg-faint))"
                        data-open-reports
                    >
                        {{ openReports }} open
                        {{ openReports === 1 ? 'report' : 'reports' }}
                    </p>
                </div>
                <Link href="/dashboard/reports" class="text-sm underline"
                    >Open Reports</Link
                >
            </section>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <section
                    class="rounded-xl p-5"
                    style="
                        background: hsl(var(--bg-elev));
                        border: 1px solid hsl(var(--border-ahd));
                    "
                >
                    <h2 class="font-display mb-4 text-[22px] italic">
                        Latest comments
                    </h2>
                    <p
                        v-if="latestComments === null"
                        class="text-sm"
                        style="color: hsl(var(--fg-faint))"
                    >
                        Comments unavailable right now
                    </p>
                    <p
                        v-else-if="!latestComments.length"
                        class="text-sm"
                        style="color: hsl(var(--fg-faint))"
                    >
                        No comments yet
                    </p>
                    <ul v-else class="space-y-3">
                        <li
                            v-for="c in latestComments"
                            :key="c.id"
                            class="flex items-start justify-between gap-3 text-sm"
                        >
                            <div class="min-w-0">
                                <span class="font-medium">{{
                                    c.user_name
                                }}</span>
                                <p class="break-words opacity-80">
                                    {{ c.deleted ? '(deleted)' : c.body }}
                                </p>
                            </div>
                            <div v-if="!c.deleted" class="flex shrink-0 gap-2">
                                <button
                                    type="button"
                                    class="underline"
                                    @click="moderateComment(c.id, false)"
                                >
                                    Delete
                                </button>
                                <button
                                    type="button"
                                    class="underline"
                                    @click="moderateComment(c.id, true)"
                                >
                                    Ban
                                </button>
                            </div>
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
                    <h2 class="font-display mb-4 text-[22px] italic">
                        Chat activity
                    </h2>
                    <ul class="space-y-2 text-sm">
                        <li v-for="m in recentChat" :key="m.id">
                            <span class="font-medium">{{
                                m.member_name ?? '—'
                            }}</span>
                            <span class="opacity-60">
                                in #{{ m.room ?? '?' }}</span
                            >
                            <p class="break-words opacity-80">{{ m.body }}</p>
                        </li>
                        <li v-if="!recentChat.length" class="opacity-60">
                            No chat messages yet
                        </li>
                    </ul>
                    <h3
                        class="mt-5 mb-2 font-mono text-[10px] tracking-widest uppercase"
                        style="color: hsl(var(--fg-faint))"
                    >
                        Moderation log
                    </h3>
                    <ul class="space-y-1 text-sm">
                        <li v-for="(l, i) in modLog" :key="i">
                            {{ l.action }}
                            <span class="opacity-60"
                                >→ {{ l.target_member_name ?? '—' }}</span
                            >
                        </li>
                        <li v-if="!modLog.length" class="opacity-60">
                            No moderation actions yet
                        </li>
                    </ul>
                </section>
            </div>

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
                                    <div class="truncate">
                                        {{ a.cat_title }}
                                    </div>
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
                            <span class="truncate font-mono text-[12px]">{{
                                r.domain
                            }}</span>
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
