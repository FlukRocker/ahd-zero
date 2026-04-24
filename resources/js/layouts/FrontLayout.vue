<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import SiteHeader from '@/components/ahd/SiteHeader.vue';
import Footer from '@/components/ahd/Footer.vue';
import SearchOverlay from '@/components/ahd/SearchOverlay.vue';
import { usePageTransition } from '@/composables/usePageTransition';

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
    <div class="min-h-screen flex flex-col">
        <SiteHeader @open-search="searchOpen = true" />
        <main ref="mainEl" class="flex-1">
            <slot />
        </main>
        <Footer />
        <SearchOverlay :open="searchOpen" @close="searchOpen = false" />
    </div>
</template>
