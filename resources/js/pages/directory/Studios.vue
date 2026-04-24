<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import AhdIcon from '@/components/ahd/AhdIcon.vue';
import { useAutoReveal } from '@/composables/useReveal';
import { useSeo } from '@/composables/useSeo';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { debouncedWatch } from '@vueuse/core';
import { ref } from 'vue';

interface Studio {
    id: number;
    name: string;
    name_japanese?: string | null;
    mal_id?: number | null;
}

interface Paginator<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    total?: number;
}

const props = defineProps<{
    studios: Paginator<Studio>;
    query: string;
}>();

const q = ref(props.query || '');

debouncedWatch(
    q,
    (val) => {
        router.get(
            '/studios',
            { q: val },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    },
    { debounce: 350 },
);

useSeo(() => ({
    title: 'สตูดิโอ',
    description: 'รายชื่อสตูดิโออนิเมะทั้งหมด',
}));

useAutoReveal();
</script>

<template>
    <Head title="สตูดิโอ" />
    <FrontLayout>
        <section class="mx-auto max-w-[1440px] px-6 pt-16 pb-6 lg:px-10">
            <div
                class="mb-3 font-mono text-[11px] tracking-[0.25em] uppercase"
                style="color: hsl(var(--fg-muted))"
            >
                ไดเรกทอรี
            </div>
            <h1
                class="font-display mb-6 leading-none italic"
                style="font-size: clamp(48px, 7vw, 80px)"
            >
                สตูดิโอ
            </h1>
            <div
                class="flex max-w-md items-center gap-3 rounded-full px-4 py-2"
                style="
                    background: hsl(var(--bg-soft));
                    border: 1px solid hsl(var(--border-ahd));
                "
            >
                <AhdIcon name="search" :size="16" />
                <input
                    v-model="q"
                    type="text"
                    placeholder="ค้นหาสตูดิโอ…"
                    class="flex-1 bg-transparent text-[14px] outline-none"
                />
            </div>
        </section>

        <section class="reveal mx-auto max-w-[1440px] px-6 lg:px-10">
            <div
                class="grid gap-3"
                style="
                    grid-template-columns: repeat(
                        auto-fill,
                        minmax(240px, 1fr)
                    );
                "
            >
                <Link
                    v-for="s in studios.data"
                    :key="s.id"
                    :href="`/studio/${s.id}`"
                    class="rounded-xl p-4 transition-colors"
                    style="
                        background: hsl(var(--bg-elev));
                        border: 1px solid hsl(var(--border-ahd));
                    "
                >
                    <div class="font-medium">{{ s.name }}</div>
                    <div
                        v-if="s.name_japanese"
                        class="font-mono text-[12px]"
                        style="color: hsl(var(--fg-muted))"
                    >
                        {{ s.name_japanese }}
                    </div>
                </Link>
            </div>
            <Pagination :links="studios.links" />
        </section>
    </FrontLayout>
</template>
