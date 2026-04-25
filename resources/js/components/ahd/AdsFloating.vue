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
        <a
            v-if="data.left"
            :href="data.left.href"
            :rel="data.left.rel"
            target="_blank"
            class="floating-l"
        >
            <img
                :src="data.left.src"
                :alt="data.left.alt"
                loading="lazy"
                decoding="async"
            />
        </a>

        <a
            v-if="data.right"
            :href="data.right.href"
            :rel="data.right.rel"
            target="_blank"
            class="floating-r"
        >
            <img
                :src="data.right.src"
                :alt="data.right.alt"
                loading="lazy"
                decoding="async"
            />
        </a>

        <div v-if="data.bottom.length" class="floating-b">
            <button
                type="button"
                class="floating-close"
                aria-label="Close all ads"
                @click="dismissAll"
            >X</button>
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

.floating-l,
.floating-r {
    position: fixed;
    top: 88px;
    width: 160px;
    pointer-events: auto;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.25);
    border-radius: 6px;
    overflow: hidden;
}

.floating-l {
    left: 12px;
}

.floating-r {
    right: 12px;
}

.floating-l img,
.floating-r img {
    display: block;
    width: 100%;
    height: auto;
}

/* Bottom strip stretches full viewport width. Close button sits as the first
 * column at the left edge — same 90px height as the 728x90 banners. */
.floating-b {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: stretch;
    justify-content: flex-start;
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

.floating-close {
    flex: 0 0 90px;
    height: 90px;
    width: 90px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: hsl(354 78% 56%);
    color: white;
    font-size: 36px;
    font-weight: 800;
    line-height: 1;
    cursor: pointer;
    border: none;
    transition: background 0.15s;
}

.floating-close:hover {
    background: hsl(354 78% 48%);
}

/* Hide all floating ads on smaller viewports — too cramped to be useful. */
@media (max-width: 992px) {
    .floating-ads {
        display: none;
    }
}
</style>
