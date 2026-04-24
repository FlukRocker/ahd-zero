<script setup lang="ts">
import { ref, watch, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import AhdIcon from './AhdIcon.vue';

const props = defineProps<{ open: boolean }>();
const emit = defineEmits<{ (e: 'close'): void }>();

const q = ref('');
const input = ref<HTMLInputElement | null>(null);

watch(
    () => props.open,
    async (v) => {
        if (v) {
            await nextTick();
            input.value?.focus();
        } else {
            q.value = '';
        }
    },
);

function submit() {
    const term = q.value.trim();
    if (!term) return;
    router.get('/search/results', { q: term }, { preserveScroll: false });
    emit('close');
}

function onKey(e: KeyboardEvent) {
    if (e.key === 'Escape') emit('close');
}
</script>

<template>
    <Transition name="fade">
        <div
            v-if="open"
            class="fixed inset-0 z-[80] flex items-start justify-center pt-[12vh] px-6"
            style="background: hsl(var(--bg) / 0.6); backdrop-filter: blur(12px);"
            @click.self="emit('close')"
            @keydown="onKey"
        >
            <div class="glass w-full max-w-2xl rounded-3xl p-4">
                <form class="flex items-center gap-3 px-4 py-3" @submit.prevent="submit">
                    <AhdIcon name="search" :size="18" />
                    <input
                        ref="input"
                        v-model="q"
                        type="text"
                        placeholder="ค้นหาอนิเมะ หมวดหมู่ สตูดิโอ…"
                        class="flex-1 bg-transparent outline-none text-[15px]"
                    />
                    <span
                        class="font-mono text-[10px] px-1.5 py-0.5 rounded"
                        style="background: hsl(var(--bg-elev)); border: 1px solid hsl(var(--border-ahd));"
                    >↵</span>
                    <button type="button" class="ml-2 opacity-60 hover:opacity-100" @click="emit('close')">
                        <AhdIcon name="close" :size="18" />
                    </button>
                </form>
                <div class="px-4 pb-3 text-[12px] font-mono" style="color: hsl(var(--fg-faint));">
                    กด Enter เพื่อค้นหา · Esc เพื่อปิด
                </div>
            </div>
        </div>
    </Transition>
</template>
