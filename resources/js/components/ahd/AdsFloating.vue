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

/* Side rails need 160px gutters — hide on tablet/mobile. Bottom strip
 * stays visible (full-width bar works fine on small screens). */
@media (max-width: 992px) {
    .floating-l,
    .floating-r {
        display: none;
    }

    .floating-b {
        height: 60px;
    }

    .floating-b-item img {
        height: 60px;
    }

    .strip-close {
        flex: 0 0 28px;
        width: 28px;
        height: 60px;
        font-size: 18px;
    }
}
</style>
