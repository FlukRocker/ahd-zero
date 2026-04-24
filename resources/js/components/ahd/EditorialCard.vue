<script setup lang="ts">
import type { CardItem } from '@/lib/animeCard';
import { Link } from '@inertiajs/vue3';
import StarIcon from './StarIcon.vue';

defineProps<{ item: CardItem }>();
</script>

<template>
    <Link :href="item.href" class="ed-card group flex overflow-hidden">
        <div class="relative w-[40%] shrink-0" style="aspect-ratio: 2/3">
            <img
                :src="item.poster"
                class="h-full w-full object-cover"
                :alt="item.title"
            />
            <span v-if="item.tag" class="sticker">{{ item.tag }}</span>
        </div>
        <div class="flex flex-1 flex-col p-5">
            <div
                v-if="item.genre"
                class="mb-3 font-mono text-[10px] tracking-[0.2em] uppercase"
                style="color: hsl(var(--fg-faint))"
            >
                {{ item.genre }}
            </div>
            <div class="font-display mb-2 text-[26px] leading-[1.05]">
                {{ item.title }}
            </div>
            <div
                v-if="item.kanji"
                class="mb-3 font-mono text-[11px]"
                style="color: hsl(var(--fg-muted))"
            >
                {{ item.kanji }}
            </div>
            <p class="mb-auto text-[13px]" style="color: hsl(var(--fg-muted))">
                <span v-if="item.ep">{{ item.ep }}</span>
            </p>
            <div class="mt-4 flex items-center gap-2">
                <span v-if="item.rating" class="rating-pill">
                    <StarIcon style="color: hsl(var(--accent))" />
                    {{ item.rating }}
                </span>
                <span
                    v-if="item.ep"
                    class="font-mono text-[11px]"
                    style="color: hsl(var(--fg-faint))"
                    >{{ item.ep }}</span
                >
                <span
                    class="ml-auto flex items-center gap-1 text-[12px] font-medium"
                    style="color: hsl(var(--accent))"
                >
                    <span class="u-grow">More →</span>
                </span>
            </div>
        </div>
    </Link>
</template>
