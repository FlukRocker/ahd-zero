<script setup lang="ts">
import { computed, ref } from 'vue';

const props = defineProps<{
    reactions: Array<{ emoji: string; user_ids: string[] }>;
    currentUserId: string | null;
}>();

const emit = defineEmits<{
    react: [emoji: string];
}>();

const emojis = ['👍', '❤️', '😂', '😮', '😢', '🔥'];
const showPicker = ref(false);

function hasReacted(emoji: string): boolean {
    const reaction = props.reactions.find((r) => r.emoji === emoji);
    return reaction
        ? reaction.user_ids.includes(props.currentUserId ?? '')
        : false;
}

function getCount(emoji: string): number {
    return props.reactions.find((r) => r.emoji === emoji)?.user_ids.length ?? 0;
}

const activeReactions = computed(() =>
    props.reactions.filter((r) => r.user_ids.length > 0),
);
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5">
        <button
            v-for="reaction in activeReactions"
            :key="reaction.emoji"
            type="button"
            class="reaction-pill"
            :class="{ active: hasReacted(reaction.emoji) }"
            @click="emit('react', reaction.emoji)"
        >
            <span>{{ reaction.emoji }}</span>
            <span class="font-medium">{{ getCount(reaction.emoji) }}</span>
        </button>

        <div class="relative">
            <button
                type="button"
                class="reaction-add"
                aria-label="เพิ่มรีแอ็คชัน"
                @click="showPicker = !showPicker"
            >+</button>
            <div v-if="showPicker" class="reaction-picker">
                <button
                    v-for="emoji in emojis"
                    :key="emoji"
                    type="button"
                    class="rounded-lg p-1 text-base hover:opacity-70"
                    @click="emit('react', emoji); showPicker = false"
                >
                    {{ emoji }}
                </button>
            </div>
        </div>
    </div>
</template>

<style scoped>
.reaction-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 12px;
    border: 1px solid hsl(var(--border-ahd));
    background: hsl(var(--bg-soft));
    color: hsl(var(--fg-muted));
    transition: border-color 0.15s, background 0.15s, color 0.15s;
}

.reaction-pill:hover {
    border-color: hsl(var(--border-strong));
}

.reaction-pill.active {
    border-color: hsl(var(--accent) / 0.5);
    background: hsl(var(--accent) / 0.12);
    color: hsl(var(--accent));
}

.reaction-add {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    border: 1px solid hsl(var(--border-ahd));
    background: hsl(var(--bg-soft));
    color: hsl(var(--fg-muted));
    font-size: 12px;
    transition: border-color 0.15s, color 0.15s;
}

.reaction-add:hover {
    border-color: hsl(var(--border-strong));
    color: hsl(var(--fg));
}

.reaction-picker {
    position: absolute;
    bottom: 100%;
    left: 0;
    margin-bottom: 4px;
    z-index: 10;
    display: flex;
    gap: 4px;
    padding: 6px;
    border-radius: 12px;
    border: 1px solid hsl(var(--border-strong));
    background: hsl(var(--bg-elev));
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
}
</style>
