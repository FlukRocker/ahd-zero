<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import type { CardItem } from '@/lib/animeCard';
import { chevSrcset, LANDSCAPE_SIZES } from '@/lib/img';
import StarIcon from './StarIcon.vue';

const props = defineProps<{ item: CardItem }>();
const srcset = computed(() => chevSrcset(props.item.landscape));
</script>

<template>
    <Link :href="item.href" class="group block">
        <div class="land-card">
            <img
                :src="item.landscape"
                :srcset="srcset || undefined"
                :sizes="srcset ? LANDSCAPE_SIZES : undefined"
                :alt="item.title"
                loading="lazy"
                decoding="async"
                width="340"
                height="191"
            />
            <span v-if="item.tag" class="sticker">{{ item.tag }}</span>
            <div class="grad-bot" />
            <div class="absolute bottom-3 left-4 right-4 text-white">
                <div class="flex items-center gap-2 mb-1">
                    <span v-if="item.rating" class="rating-pill" style="background: rgba(255,255,255,0.15); color: white;">
                        <StarIcon style="color: hsl(var(--accent));" /> {{ item.rating }}
                    </span>
                    <span v-if="item.genre" class="text-[11px] font-mono opacity-90">{{ item.genre }}</span>
                </div>
                <div class="font-display text-[22px] leading-tight">{{ item.title }}</div>
                <div v-if="item.ep || item.kanji" class="text-[12px] opacity-80 font-mono">
                    <span v-if="item.ep">{{ item.ep }}</span>
                    <span v-if="item.ep && item.kanji"> · </span>
                    <span v-if="item.kanji">{{ item.kanji }}</span>
                </div>
            </div>
        </div>
    </Link>
</template>
