<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { debouncedWatch } from '@vueuse/core';
import FrontLayout from '@/layouts/FrontLayout.vue';
import Pagination from '@/components/Pagination.vue';
import AhdIcon from '@/components/ahd/AhdIcon.vue';
import { useAutoReveal } from '@/composables/useReveal';
import { useSeo } from '@/composables/useSeo';

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
        router.get('/studios', { q: val }, { preserveScroll: true, preserveState: true, replace: true });
    },
    { debounce: 350 },
);

useSeo(() => ({ title: 'สตูดิโอ', description: 'รายชื่อสตูดิโออนิเมะทั้งหมด' }));

useAutoReveal();
</script>

<template>
    <Head title="สตูดิโอ" />
    <FrontLayout>
        <section class="max-w-[1440px] mx-auto px-6 lg:px-10 pt-16 pb-6">
            <div class="font-mono text-[11px] tracking-[0.25em] uppercase mb-3" style="color: hsl(var(--fg-muted));">
                ไดเรกทอรี
            </div>
            <h1 class="font-display italic leading-none mb-6" style="font-size: clamp(48px, 7vw, 80px);">
                สตูดิโอ
            </h1>
            <div
                class="flex items-center gap-3 px-4 py-2 rounded-full max-w-md"
                style="background: hsl(var(--bg-soft)); border: 1px solid hsl(var(--border-ahd));"
            >
                <AhdIcon name="search" :size="16" />
                <input
                    v-model="q"
                    type="text"
                    placeholder="ค้นหาสตูดิโอ…"
                    class="flex-1 bg-transparent outline-none text-[14px]"
                />
            </div>
        </section>

        <section class="max-w-[1440px] mx-auto px-6 lg:px-10 reveal">
            <div class="grid gap-3" style="grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));">
                <Link
                    v-for="s in studios.data"
                    :key="s.id"
                    :href="`/studio/${s.id}`"
                    class="p-4 rounded-xl transition-colors"
                    style="background: hsl(var(--bg-elev)); border: 1px solid hsl(var(--border-ahd));"
                >
                    <div class="font-medium">{{ s.name }}</div>
                    <div v-if="s.name_japanese" class="text-[12px] font-mono" style="color: hsl(var(--fg-muted));">
                        {{ s.name_japanese }}
                    </div>
                </Link>
            </div>
            <Pagination :links="studios.links" />
        </section>
    </FrontLayout>
</template>
