<script setup lang="ts">
import AboutSeo from '@/components/ahd/AboutSeo.vue';
import AdsFloating from '@/components/ahd/AdsFloating.vue';
import AdsNavbar from '@/components/ahd/AdsNavbar.vue';
import Footer from '@/components/ahd/Footer.vue';
import SearchOverlay from '@/components/ahd/SearchOverlay.vue';
import SiteHeader from '@/components/ahd/SiteHeader.vue';
import { usePageTransition } from '@/composables/usePageTransition';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const searchOpen = ref(false);
const mainEl = ref<HTMLElement | null>(null);

function onKey(e: KeyboardEvent) {
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        searchOpen.value = true;
    }
}

onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));

usePageTransition(() => mainEl.value);
</script>

<template>
    <div class="flex min-h-screen flex-col">
        <AdsNavbar />
        <SiteHeader @open-search="searchOpen = true" />
        <main ref="mainEl" class="flex-1">
            <slot />
            <AboutSeo />
        </main>
        <Footer />
        <SearchOverlay :open="searchOpen" @close="searchOpen = false" />
        <AdsFloating />
    </div>
</template>
