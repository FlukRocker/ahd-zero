<script setup lang="ts">
import { useTilt } from '@/composables/useTilt';
import type { CardItem } from '@/lib/animeCard';
import { chevSrcset, POSTER_SIZES } from '@/lib/img';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AhdIcon from './AhdIcon.vue';
import StarIcon from './StarIcon.vue';

const props = defineProps<{ item: CardItem; rank?: number | null }>();

const tiltEl = ref<HTMLElement | null>(null);
useTilt(tiltEl, { max: 5, scale: 1.03 });

const srcset = computed(() => chevSrcset(props.item.poster));
</script>

<template>
    <Link :href="item.href" class="group block">
        <div ref="tiltEl" class="poster-card tilt">
            <div class="halo" />
            <img
                :src="item.poster"
                :srcset="srcset || undefined"
                :sizes="srcset ? POSTER_SIZES : undefined"
                :alt="item.title"
                loading="lazy"
                decoding="async"
                width="300"
                height="450"
            />
            <span v-if="item.tag" class="sticker">{{ item.tag }}</span>
            <span
                v-if="rank"
                class="font-display absolute top-3 right-3 leading-none italic"
                style="
                    font-size: 56px;
                    color: white;
                    text-shadow:
                        0 4px 20px rgba(0, 0, 0, 0.6),
                        0 0 0 1px rgba(0, 0, 0, 0.4);
                "
                >#{{ rank }}</span
            >
            <div class="grad-bot" />
            <div class="absolute right-3 bottom-3 left-3 text-white">
                <div
                    class="flex items-center gap-2 font-mono text-[11px] opacity-90"
                >
                    <span v-if="item.ep">{{ item.ep }}</span>
                    <span v-if="item.ep && item.genre">·</span>
                    <span v-if="item.genre">{{ item.genre }}</span>
                </div>
            </div>
            <div
                class="absolute inset-0 flex items-center justify-center opacity-0 transition-opacity group-hover:opacity-100"
                style="background: rgba(0, 0, 0, 0.25)"
            >
                <span class="btn btn-primary"
                    ><AhdIcon name="play" :size="14" /> Watch</span
                >
            </div>
        </div>
        <div class="mt-3">
            <div class="mb-1 flex items-center gap-2">
                <span v-if="item.rating" class="rating-pill">
                    <StarIcon style="color: hsl(var(--accent))" />
                    {{ item.rating }}
                </span>
                <span
                    v-if="item.kanji"
                    class="font-mono text-[11px]"
                    style="color: hsl(var(--fg-faint))"
                    >{{ item.kanji }}</span
                >
            </div>
            <div class="font-display text-[20px] leading-tight">
                {{ item.title }}
            </div>
        </div>
    </Link>
</template>
