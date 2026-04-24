<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import AhdIcon from '@/components/ahd/AhdIcon.vue';
import { useAutoReveal } from '@/composables/useReveal';
import { useSeo } from '@/composables/useSeo';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { debouncedWatch } from '@vueuse/core';
import { ref } from 'vue';

interface StaffItem {
    id: number;
    name: string;
    name_japanese?: string | null;
    image_url?: string | null;
    mal_id?: number | null;
}

interface Paginator<T> {
    data: T[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

const props = defineProps<{
    staffList: Paginator<StaffItem>;
    query: string;
}>();

const q = ref(props.query || '');

debouncedWatch(
    q,
    (val) => {
        router.get(
            '/staff',
            { q: val },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    },
    { debounce: 350 },
);

useSeo(() => ({ title: 'ทีมงาน', description: 'รายชื่อทีมงานอนิเมะ' }));

useAutoReveal();
</script>

<template>
    <Head title="ทีมงาน" />
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
                ทีมงาน
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
                    placeholder="ค้นหาทีมงาน…"
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
                        minmax(220px, 1fr)
                    );
                "
            >
                <div
                    v-for="s in staffList.data"
                    :key="s.id"
                    class="flex items-center gap-3 rounded-xl p-3"
                    style="
                        background: hsl(var(--bg-elev));
                        border: 1px solid hsl(var(--border-ahd));
                    "
                >
                    <img
                        v-if="s.image_url"
                        :src="s.image_url"
                        :alt="s.name"
                        class="h-12 w-12 shrink-0 rounded-full object-cover"
                        loading="lazy"
                    />
                    <div class="min-w-0 flex-1">
                        <div class="truncate font-medium">{{ s.name }}</div>
                        <div
                            v-if="s.name_japanese"
                            class="truncate font-mono text-[12px]"
                            style="color: hsl(var(--fg-muted))"
                        >
                            {{ s.name_japanese }}
                        </div>
                    </div>
                </div>
            </div>
            <Pagination :links="staffList.links" />
        </section>
    </FrontLayout>
</template>
