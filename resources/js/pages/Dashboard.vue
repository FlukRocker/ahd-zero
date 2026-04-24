<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

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

defineProps<{
    stats: {
        totalAnime: number;
        totalEpisodes: number;
        totalMembers: number;
        animeByType: { sub: number; dub: number; movie: number };
    };
    recentAnime: AnimeRow[];
    recentMembers: MemberRow[];
}>();

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
