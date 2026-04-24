<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import { animate, scroll, stagger } from 'motion';
import type { CardItem } from '@/lib/animeCard';
import AhdIcon from './AhdIcon.vue';
import StarIcon from './StarIcon.vue';

type Variant = 'cinema' | 'editorial';

const props = withDefaults(
    defineProps<{ items: CardItem[]; variant?: Variant; interval?: number }>(),
    { variant: 'editorial', interval: 6500 },
);

const root = ref<HTMLElement | null>(null);
const backdrop = ref<HTMLElement | null>(null);
const idx = ref(0);
let timer: ReturnType<typeof setInterval> | null = null;
let stopParallax: (() => void) | null = null;

function prefersReducedMotion() {
    return typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

const cur = computed(() => props.items[idx.value] ?? props.items[0]);

function start() {
    stop();
    if (props.items.length <= 1) return;
    timer = setInterval(() => {
        idx.value = (idx.value + 1) % props.items.length;
    }, props.interval);
}

function stop() {
    if (timer) {
        clearInterval(timer);
        timer = null;
    }
}

function go(i: number) {
    idx.value = i;
    start();
}

function animateIn() {
    if (!root.value) return;
    const els = root.value.querySelectorAll<HTMLElement>('[data-hero-anim]');
    if (!els.length) return;
    animate(
        Array.from(els),
        { opacity: [0, 1], y: [24, 0] },
        { duration: 0.8, delay: stagger(0.08), easing: [0.2, 0.7, 0.2, 1] },
    );
}

function initParallax() {
    if (prefersReducedMotion()) return;
    const el = backdrop.value;
    if (!el || !root.value) return;
    stopParallax?.();
    stopParallax = scroll(
        (_progress: number, info: { y?: { progress?: number } }) => {
            const p = info?.y?.progress ?? _progress ?? 0;
            el.style.transform = `translate3d(0, ${p * 120}px, 0) scale(${1 + p * 0.04})`;
            el.style.opacity = String(Math.max(0, 1 - p * 0.85));
        },
        { target: root.value, offset: ['start start', 'end start'] },
    );
}

onMounted(() => {
    start();
    nextTick(() => {
        animateIn();
        initParallax();
    });
});

onBeforeUnmount(() => {
    stop();
    stopParallax?.();
});

watch(idx, () => nextTick(animateIn));
watch(() => props.variant, () => nextTick(animateIn));
</script>

<template>
    <section v-if="cur" ref="root" class="relative overflow-hidden" style="min-height: 88vh;">
        <!-- Cinema: full-bleed backdrop -->
        <template v-if="variant === 'cinema'">
            <div ref="backdrop" class="absolute inset-0 will-change-transform">
                <Transition name="crossfade" mode="out-in">
                    <img :key="cur.id" :src="cur.landscape" class="w-full h-full object-cover" :alt="cur.title" />
                </Transition>
                <div class="grad-hero" />
            </div>
            <div
                class="blob"
                style="width: 500px; height: 500px; top: -100px; right: -100px; background: hsl(var(--accent));"
            />
            <div
                class="max-w-[1440px] mx-auto px-6 lg:px-10 relative h-full flex items-end pb-20 pt-32"
                style="min-height: 88vh;"
            >
                <div class="grid grid-cols-12 gap-6 w-full">
                    <div class="col-span-12 lg:col-span-7">
                        <div data-hero-anim class="flex items-center gap-3 mb-5">
                            <span v-if="cur.tag" class="chip chip-accent font-mono">{{ cur.tag }}</span>
                            <span
                                v-if="cur.genre"
                                class="font-mono text-[11px] tracking-[0.2em] uppercase"
                                style="color: hsl(var(--fg-muted));"
                            >{{ cur.genre }}</span>
                        </div>
                        <div
                            v-if="cur.kanji"
                            data-hero-anim
                            class="font-mono text-[12px] tracking-[0.25em] uppercase mb-3"
                            style="color: hsl(var(--fg-muted));"
                        >{{ cur.kanji }}</div>
                        <h1
                            data-hero-anim
                            class="font-display text-[56px] md:text-[92px] italic leading-[0.95] mb-6"
                            style="text-wrap: balance;"
                        >{{ cur.title }}</h1>
                        <div data-hero-anim class="flex flex-wrap items-center gap-3">
                            <Link :href="cur.href" class="btn btn-primary"><AhdIcon name="play" :size="14" /> ดูตอนนี้</Link>
                            <div class="flex items-center gap-2 ml-2">
                                <span v-if="cur.ep" class="font-mono text-[11px]" style="color: hsl(var(--fg-muted));">
                                    {{ cur.ep }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div
                        data-hero-anim
                        class="col-span-12 lg:col-span-5 hidden lg:flex justify-end items-end"
                    >
                        <div
                            class="tilt relative w-[320px] aspect-[2/3] rounded-2xl overflow-hidden shadow-2xl"
                            style="box-shadow: 0 40px 80px -30px rgba(0,0,0,0.5), 0 0 0 1px hsl(var(--border));"
                        >
                            <img :src="cur.poster" class="w-full h-full object-cover" :alt="cur.title" />
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Editorial: split layout -->
        <template v-else>
            <div
                class="max-w-[1440px] mx-auto px-6 lg:px-10 pt-20 pb-20 grid grid-cols-12 gap-8 items-center relative"
                style="min-height: 88vh;"
            >
                <div
                    class="blob"
                    style="width: 480px; height: 480px; top: 20px; left: -100px; background: hsl(var(--accent));"
                />
                <div class="col-span-12 lg:col-span-7 relative">
                    <div
                        data-hero-anim
                        class="font-mono text-[11px] tracking-[0.25em] uppercase mb-4"
                        style="color: hsl(var(--fg-muted));"
                    >
                        แนะนำ · ลำดับที่ {{ String(idx + 1).padStart(2, '0') }} / {{ String(items.length).padStart(2, '0') }}
                    </div>
                    <h1
                        data-hero-anim
                        class="font-display italic leading-[0.9] mb-6"
                        style="font-size: clamp(60px, 10vw, 160px); text-wrap: balance;"
                    >{{ cur.title }}</h1>
                    <div v-if="cur.kanji" data-hero-anim class="flex items-baseline gap-6 mb-8">
                        <div class="font-display text-[40px] italic" style="color: hsl(var(--accent));">
                            {{ cur.kanji }}
                        </div>
                        <div class="h-px flex-1" style="background: hsl(var(--border-strong));" />
                        <div
                            class="font-mono text-[12px] tracking-widest uppercase"
                            style="color: hsl(var(--fg-muted));"
                        >{{ cur.genre }}</div>
                    </div>
                    <div data-hero-anim class="flex flex-wrap items-center gap-3">
                        <Link :href="cur.href" class="btn btn-primary"><AhdIcon name="play" :size="14" /> เริ่มรับชม</Link>
                    </div>
                </div>
                <div data-hero-anim class="col-span-12 lg:col-span-5 relative">
                    <div
                        ref="backdrop"
                        class="relative aspect-[3/4] rounded-2xl overflow-hidden will-change-transform"
                        style="box-shadow: 0 40px 80px -30px rgba(0,0,0,0.3);"
                    >
                        <img :src="cur.poster" class="w-full h-full object-cover" />
                        <div class="absolute top-4 left-4 right-4 flex justify-between">
                            <span v-if="cur.tag" class="chip chip-accent font-mono">{{ cur.tag }}</span>
                            <span v-if="cur.rating" class="rating-pill" style="background: rgba(255,255,255,0.85);">
                                <StarIcon style="color: hsl(var(--accent));" /> {{ cur.rating }}
                            </span>
                        </div>
                    </div>
                    <div
                        v-if="cur.ep"
                        class="absolute -bottom-4 -left-4 font-mono text-[10px] tracking-[0.2em] uppercase px-3 py-2 rounded-lg"
                        style="background: hsl(var(--fg)); color: hsl(var(--bg));"
                    >{{ cur.ep }}</div>
                </div>
            </div>
            <div class="absolute bottom-8 left-0 right-0">
                <div class="max-w-[1440px] mx-auto px-6 lg:px-10 flex gap-2">
                    <button
                        v-for="(h, i) in items"
                        :key="h.id"
                        type="button"
                        class="pager-dot"
                        :class="{ active: i === idx }"
                        @click="go(i)"
                    />
                </div>
            </div>
        </template>

        <div
            class="absolute bottom-0 left-0 right-0 h-20"
            style="background: linear-gradient(180deg, transparent, hsl(var(--bg)));"
        />
    </section>
</template>
