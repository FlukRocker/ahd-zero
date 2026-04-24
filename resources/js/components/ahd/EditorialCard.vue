<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { CardItem } from '@/lib/animeCard';
import StarIcon from './StarIcon.vue';

defineProps<{ item: CardItem }>();
</script>

<template>
    <Link :href="item.href" class="ed-card flex overflow-hidden group">
        <div class="w-[40%] relative shrink-0" style="aspect-ratio: 2/3;">
            <img :src="item.poster" class="w-full h-full object-cover" :alt="item.title" />
            <span v-if="item.tag" class="sticker">{{ item.tag }}</span>
        </div>
        <div class="flex-1 p-5 flex flex-col">
            <div
                v-if="item.genre"
                class="font-mono text-[10px] tracking-[0.2em] uppercase mb-3"
                style="color: hsl(var(--fg-faint));"
            >{{ item.genre }}</div>
            <div class="font-display text-[26px] leading-[1.05] mb-2">{{ item.title }}</div>
            <div v-if="item.kanji" class="text-[11px] font-mono mb-3" style="color: hsl(var(--fg-muted));">{{ item.kanji }}</div>
            <p class="text-[13px] mb-auto" style="color: hsl(var(--fg-muted));">
                <span v-if="item.ep">{{ item.ep }}</span>
            </p>
            <div class="flex items-center gap-2 mt-4">
                <span v-if="item.rating" class="rating-pill">
                    <StarIcon style="color: hsl(var(--accent));" /> {{ item.rating }}
                </span>
                <span v-if="item.ep" class="text-[11px] font-mono" style="color: hsl(var(--fg-faint));">{{ item.ep }}</span>
                <span class="ml-auto text-[12px] font-medium flex items-center gap-1" style="color: hsl(var(--accent));">
                    <span class="u-grow">More →</span>
                </span>
            </div>
        </div>
    </Link>
</template>
