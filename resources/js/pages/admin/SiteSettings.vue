<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
    settings: {
        maintenance: boolean;
        registration: boolean;
        registrationLockedByEnv?: boolean;
    };
}>();

const maintenance = ref(props.settings.maintenance);
const registration = ref(props.settings.registration);
const flash = ref<string | null>(null);
const registrationLocked = props.settings.registrationLockedByEnv === true;

function csrf(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function post(url: string, body: Record<string, unknown>) {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf(),
            Accept: 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });
    if (res.ok) {
        const data = await res.json();
        flash.value = data.message ?? 'Saved';
        setTimeout(() => (flash.value = null), 2500);
        return data;
    }
    flash.value = 'Error';
    return null;
}

async function toggleMaintenance() {
    const next = !maintenance.value;
    const data = await post('/dashboard/site-settings/maintenance', {
        enable: next,
    });
    if (data) maintenance.value = data.maintenance;
}

async function toggleRegistration() {
    const next = !registration.value;
    const data = await post('/dashboard/site-settings/registration', {
        enable: next,
    });
    if (data) registration.value = data.registration;
}

async function clearCache() {
    await post('/dashboard/site-settings/clear-cache', {});
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Site settings', href: '/dashboard/site-settings' },
];
</script>

<template>
    <Head title="Site settings" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-2xl space-y-6 p-4">
            <Transition name="fade">
                <div
                    v-if="flash"
                    class="rounded-lg px-4 py-2 text-[13px]"
                    style="
                        background: hsl(var(--accent) / 0.15);
                        border: 1px solid hsl(var(--accent) / 0.4);
                    "
                >
                    {{ flash }}
                </div>
            </Transition>

            <div
                class="rounded-xl p-5"
                style="
                    background: hsl(var(--bg-elev));
                    border: 1px solid hsl(var(--border-ahd));
                "
            >
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="font-display text-[20px] italic">
                            Maintenance mode
                        </div>
                        <div
                            class="text-[12px]"
                            style="color: hsl(var(--fg-muted))"
                        >
                            Public site returns 503. Admin dashboard stays open.
                        </div>
                    </div>
                    <button
                        type="button"
                        class="btn"
                        :class="maintenance ? 'btn-primary' : 'btn-ghost'"
                        @click="toggleMaintenance"
                    >
                        {{ maintenance ? 'ON' : 'OFF' }}
                    </button>
                </div>
            </div>

            <div
                class="rounded-xl p-5"
                style="
                    background: hsl(var(--bg-elev));
                    border: 1px solid hsl(var(--border-ahd));
                "
            >
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="font-display text-[20px] italic">
                            Member registration
                        </div>
                        <div
                            class="text-[12px]"
                            style="color: hsl(var(--fg-muted))"
                        >
                            Allow new member signups.
                            <span
                                v-if="registrationLocked"
                                class="mt-1 block font-mono"
                                style="color: hsl(var(--accent))"
                                >Locked OFF by env
                                (REGISTRATION_ENABLED=false)</span
                            >
                        </div>
                    </div>
                    <button
                        type="button"
                        class="btn"
                        :class="registration ? 'btn-primary' : 'btn-ghost'"
                        :disabled="registrationLocked"
                        @click="toggleRegistration"
                    >
                        {{ registration ? 'OPEN' : 'CLOSED' }}
                    </button>
                </div>
            </div>

            <div
                class="rounded-xl p-5"
                style="
                    background: hsl(var(--bg-elev));
                    border: 1px solid hsl(var(--border-ahd));
                "
            >
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="font-display text-[20px] italic">
                            Application cache
                        </div>
                        <div
                            class="text-[12px]"
                            style="color: hsl(var(--fg-muted))"
                        >
                            Flush all cache keys (sitemaps, listings, image
                            variants).
                        </div>
                    </div>
                    <button
                        type="button"
                        class="btn btn-ghost"
                        @click="clearCache"
                    >
                        Clear cache
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
