<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

// Cloudflare Turnstile widget. Loads challenges.cloudflare.com/turnstile/v0/api.js
// once per page (subsequent mounts re-use the global `window.turnstile` API)
// and exposes the verification token via v-model so parent forms can ship it
// alongside their payload as `cf-turnstile-response`.
//
// CSP: script-src already whitelists challenges.cloudflare.com.
// Theme: matches the site appearance (light/dark) via data-theme attr.

const props = defineProps<{
    siteKey: string;
    /** Optional theme override; defaults to 'auto' (follows system). */
    theme?: 'light' | 'dark' | 'auto';
}>();

const emit = defineEmits<{
    'update:modelValue': [token: string];
    error: [message: string];
    expired: [];
}>();

const container = ref<HTMLDivElement | null>(null);
let widgetId: string | null = null;

const SCRIPT_SRC =
    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';

interface TurnstileApi {
    render: (
        el: HTMLElement,
        opts: {
            sitekey: string;
            theme?: string;
            callback?: (token: string) => void;
            'error-callback'?: () => void;
            'expired-callback'?: () => void;
        },
    ) => string;
    remove: (id: string) => void;
    reset: (id: string) => void;
}

declare global {
    interface Window {
        turnstile?: TurnstileApi;
    }
}

function loadScript(): Promise<TurnstileApi> {
    return new Promise((resolve, reject) => {
        if (window.turnstile) {
            resolve(window.turnstile);
            return;
        }
        // Reuse an in-flight load if another widget already kicked it off
        const existing = document.querySelector<HTMLScriptElement>(
            `script[src^="${SCRIPT_SRC.split('?')[0]}"]`,
        );
        const onReady = () => {
            if (window.turnstile) resolve(window.turnstile);
            else reject(new Error('Turnstile script loaded but window.turnstile undefined'));
        };
        if (existing) {
            existing.addEventListener('load', onReady, { once: true });
            existing.addEventListener('error', () => reject(new Error('script error')), {
                once: true,
            });
            return;
        }
        const s = document.createElement('script');
        s.src = SCRIPT_SRC;
        s.async = true;
        s.defer = true;
        s.addEventListener('load', onReady, { once: true });
        s.addEventListener('error', () => reject(new Error('Turnstile script load failed')), {
            once: true,
        });
        document.head.appendChild(s);
    });
}

async function render() {
    if (!container.value || !props.siteKey) return;
    try {
        const api = await loadScript();
        widgetId = api.render(container.value, {
            sitekey: props.siteKey,
            theme: props.theme ?? 'auto',
            callback: (token: string) => emit('update:modelValue', token),
            'error-callback': () => emit('error', 'turnstile_error'),
            'expired-callback': () => {
                emit('expired');
                emit('update:modelValue', '');
            },
        });
    } catch (e) {
        emit('error', (e as Error).message);
    }
}

onMounted(render);

// Re-render if the site key changes (theme switch handled by Cloudflare)
watch(
    () => props.siteKey,
    (k, old) => {
        if (k && k !== old) {
            if (widgetId && window.turnstile) {
                window.turnstile.remove(widgetId);
                widgetId = null;
            }
            render();
        }
    },
);

onBeforeUnmount(() => {
    if (widgetId && window.turnstile) {
        window.turnstile.remove(widgetId);
        widgetId = null;
    }
});

defineExpose({
    reset() {
        if (widgetId && window.turnstile) window.turnstile.reset(widgetId);
        emit('update:modelValue', '');
    },
});
</script>

<template>
    <div ref="container" class="cf-turnstile" />
</template>
