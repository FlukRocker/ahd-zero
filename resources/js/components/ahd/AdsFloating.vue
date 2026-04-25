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
            <a
                v-for="(it, i) in data.bottom"
                :key="i"
                :href="it.href"
                :rel="it.rel"
                target="_blank"
            >
                <img
                    :src="it.src"
                    :alt="it.alt"
                    loading="lazy"
                    decoding="async"
                />
            </a>
        </div>

        <button
            type="button"
            class="floating-close"
            aria-label="Close ads"
            @click="dismissAll"
        >×</button>
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

.floating-b {
    position: fixed;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    justify-content: center;
    pointer-events: auto;
    background: hsl(var(--bg) / 0.85);
    backdrop-filter: blur(8px);
    padding: 6px 10px;
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
    box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.15);
    max-width: calc(100vw - 24px);
}

.floating-b img {
    display: block;
    height: 60px;
    width: auto;
    border-radius: 4px;
}

.floating-close {
    position: fixed;
    top: 56px;
    right: 12px;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: hsl(0 0% 0% / 0.7);
    color: white;
    font-size: 14px;
    font-weight: 700;
    line-height: 1;
    pointer-events: auto;
    z-index: 1;
    cursor: pointer;
    border: none;
}

.floating-close:hover {
    background: hsl(0 0% 0%);
}

/* Hide all floating ads on smaller viewports — too cramped to be useful. */
@media (max-width: 992px) {
    .floating-ads {
        display: none;
    }
}
</style>
