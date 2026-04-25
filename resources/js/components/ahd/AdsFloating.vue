<script setup lang="ts">
import { computed, ref } from 'vue';
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

const page = usePage<{ floatingAds?: FloatingPayload }>();
const data = computed<FloatingPayload>(
    () =>
        page.props.floatingAds ?? {
            left: null,
            right: null,
            bottom: [],
        },
);

// Dismiss is page-local only — no localStorage / sessionStorage. Refresh
// or new page = ads reappear. Keeps revenue intact while letting the
// user temporarily clear screen real-estate.
const visible = ref(true);

function dismissAll() {
    visible.value = false;
}

// Always force a sponsored rel even if upstream config left it empty —
// missing rel="sponsored" tanks SEO score and PSI flags it as a security
// concern. Falls back to a sensible default if config has no rel set.
function safeRel(rel: string | undefined): string {
    return (rel && rel.trim() !== '')
        ? rel
        : 'nofollow noopener sponsored noreferrer ugc';
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
                :rel="safeRel(data.left.rel)"
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
                :rel="safeRel(data.right.rel)"
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
                :rel="safeRel(it.rel)"
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
/* Full-width black bar pinned to the viewport bottom. Banners flow
 * through CSS Grid: desktop = 2 columns side-by-side (single row),
 * mobile = 1 column with banners stacked (multiple rows). Close button
 * absolute-positioned so it sits above banner #1 in either layout. */
.floating-b {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    pointer-events: auto;
    background: #000;
    box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.4);
}

.floating-b-item {
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    min-width: 0;
}

.floating-b-item img {
    display: block;
    width: 100%;
    height: auto;
    max-height: 100px;
    object-fit: contain;
}

.strip-close {
    position: absolute;
    top: 4px;
    right: 4px;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: hsl(354 78% 56%);
    color: white;
    font-size: 18px;
    font-weight: 800;
    line-height: 1;
    border-radius: 50%;
    cursor: pointer;
    border: 2px solid #fff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    transition: background 0.15s;
    z-index: 2;
}

.strip-close:hover {
    background: hsl(354 78% 48%);
}

/* Mobile — side rails shrink to fit narrow viewports (160px gutters
 * would crash into content). Bottom strip collapses to 1-column grid
 * so banners stack vertically instead of squishing into half-width.  */
@media (max-width: 992px) {
    .floating-l,
    .floating-r {
        width: 80px;
        top: 64px;
    }

    .rail-close {
        width: 22px;
        height: 22px;
        font-size: 14px;
        top: -6px;
        right: -6px;
    }

    .floating-b {
        grid-template-columns: 1fr;
    }

    .floating-b-item img {
        max-height: 70px;
    }
}
</style>
