<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

interface FloatingItem {
    href: string;
    src: string;
    alt: string;
    rel: string;
}

interface FloatingPayload {
    left: FloatingItem | null;
    right: FloatingItem | null;
    bottom: FloatingItem[];
}

const STORAGE_KEY = 'ahd.adsFloating.dismissed';

const page = usePage<{ ads?: { floating?: FloatingPayload } }>();
const data = computed<FloatingPayload>(
    () =>
        page.props.ads?.floating ?? {
            left: null,
            right: null,
            bottom: [],
        },
);

const visible = ref(true);

onMounted(() => {
    if (typeof window === 'undefined') return;
    if (window.sessionStorage.getItem(STORAGE_KEY) === '1') {
        visible.value = false;
    }
});

function dismissAll() {
    visible.value = false;
    if (typeof window !== 'undefined') {
        window.sessionStorage.setItem(STORAGE_KEY, '1');
    }
}

const hasAny = computed(
    () =>
        data.value.left !== null ||
        data.value.right !== null ||
        data.value.bottom.length > 0,
);
</script>

<template>
    <div v-if="visible && hasAny" class="floating-ads">
        <div v-if="data.left" class="floating-l">
            <button
                type="button"
                class="rail-close"
                aria-label="Close all ads"
                @click.stop.prevent="dismissAll"
            >×</button>
            <a
                :href="data.left.href"
                :rel="data.left.rel"
                target="_blank"
                class="rail-link"
            >
                <img
                    :src="data.left.src"
                    :alt="data.left.alt"
                    loading="lazy"
                    decoding="async"
                />
            </a>
        </div>

        <div v-if="data.right" class="floating-r">
            <button
                type="button"
                class="rail-close"
                aria-label="Close all ads"
                @click.stop.prevent="dismissAll"
            >×</button>
            <a
                :href="data.right.href"
                :rel="data.right.rel"
                target="_blank"
                class="rail-link"
            >
                <img
                    :src="data.right.src"
                    :alt="data.right.alt"
                    loading="lazy"
                    decoding="async"
                />
            </a>
        </div>

        <div v-if="data.bottom.length" class="floating-b">
            <button
                type="button"
                class="strip-close"
                aria-label="Close all ads"
                @click="dismissAll"
            >×</button>
            <a
                v-for="(it, i) in data.bottom"
                :key="i"
                :href="it.href"
                :rel="it.rel"
                target="_blank"
                class="floating-b-item"
            >
                <img
                    :src="it.src"
                    :alt="it.alt"
                    loading="lazy"
                    decoding="async"
                />
            </a>
        </div>
    </div>
</template>

<style scoped>
.floating-ads {
    position: fixed;
    inset: 0;
    pointer-events: none;
    z-index: 50;
}

/* ── Side rails (left + right) ──────────────────────────────────── */
.floating-l,
.floating-r {
    position: fixed;
    top: 88px;
    width: 160px;
    pointer-events: auto;
}

.floating-l {
    left: 12px;
}

.floating-r {
    right: 12px;
}

.rail-link {
    display: block;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
    border-radius: 6px;
    overflow: hidden;
}

.rail-link img {
    display: block;
    width: 100%;
    height: auto;
}

.rail-close {
    position: absolute;
    top: -8px;
    right: -8px;
    width: 26px;
    height: 26px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: hsl(354 78% 56%);
    color: white;
    font-size: 16px;
    font-weight: 800;
    line-height: 1;
    border-radius: 50%;
    cursor: pointer;
    border: 2px solid #fff;
    z-index: 1;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    transition: background 0.15s;
}

.rail-close:hover {
    background: hsl(354 78% 48%);
}

/* ── Bottom strip ───────────────────────────────────────────────── */
/* Full-width black bar pinned to the viewport bottom. Inner content (close
 * button + banners) flexes centered so a single off-center transform doesn't
 * overhang the page and visually overlap an in-page AdsBanner block sitting
 * lower in the document. */
.floating-b {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;
    align-items: stretch;
    justify-content: center;
    pointer-events: auto;
    background: #000;
    box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.4);
    height: 90px;
    overflow: hidden;
}

.floating-b-item {
    display: flex;
    align-items: center;
    flex: 0 0 auto;
}

.floating-b-item img {
    display: block;
    height: 90px;
    width: auto;
}

.strip-close {
    flex: 0 0 36px;
    width: 36px;
    height: 90px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: hsl(354 78% 56%);
    color: white;
    font-size: 22px;
    font-weight: 800;
    line-height: 1;
    cursor: pointer;
    border: none;
    transition: background 0.15s;
}

.strip-close:hover {
    background: hsl(354 78% 48%);
}

/* Hide all floating ads on smaller viewports — too cramped to be useful. */
@media (max-width: 992px) {
    .floating-ads {
        display: none;
    }
}
</style>
