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
const failed = ref(false);
const busy = ref(false);
const registrationLocked = props.settings.registrationLockedByEnv === true;

function csrf(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

async function post(url: string, body: Record<string, unknown>) {
    if (busy.value) {
        return null;
    }

    busy.value = true;
    failed.value = false;
    let res: Response;

    try {
        res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
    } catch {
        busy.value = false;
        failed.value = true;
        flash.value = 'Network error — please retry.';

        return null;
    }

    busy.value = false;

    if (res.ok) {
        const data = await res.json();
        flash.value = data.message ?? 'Saved';
        setTimeout(() => (flash.value = null), 2500);

        return data;
    }

    failed.value = true;
    flash.value = `Request failed (${res.status})`;

    return null;
}

async function toggleMaintenance() {
    const next = !maintenance.value;

    if (
        next &&
        !window.confirm(
            'Turn maintenance mode ON? The public site will return 503 until you turn it off.',
        )
    ) {
        return;
    }

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
    if (!window.confirm("Clear this site's cache?")) {
        return;
    }

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
        <section class="mx-auto w-full max-w-3xl space-y-5 p-4 sm:p-6">
            <div>
                <h1 class="text-xl font-semibold">Site settings</h1>
                <p class="text-sm text-muted-foreground">
                    Switches that affect the whole public site.
                </p>
            </div>

            <Transition name="fade">
                <p
                    v-if="flash"
                    role="status"
                    class="rounded-md border px-3 py-2 text-sm"
                    :class="
                        failed
                            ? 'border-destructive/40 bg-destructive/10 text-destructive'
                            : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                    "
                >
                    {{ flash }}
                </p>
            </Transition>

            <div
                class="divide-y rounded-xl border bg-card text-card-foreground shadow-sm"
            >
                <div class="flex items-center justify-between gap-4 p-5">
                    <div>
                        <h2 class="font-semibold">Maintenance mode</h2>
                        <p class="text-sm text-muted-foreground">
                            Public site returns 503. Admin dashboard stays open.
                        </p>
                        <p
                            v-if="maintenance"
                            class="mt-1 text-xs font-medium text-destructive"
                        >
                            The public site is currently down.
                        </p>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="maintenance"
                        aria-label="Maintenance mode"
                        :disabled="busy"
                        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors disabled:opacity-50"
                        :class="maintenance ? 'bg-destructive' : 'bg-muted'"
                        @click="toggleMaintenance"
                    >
                        <span
                            class="inline-block size-5 rounded-full bg-white shadow transition-transform"
                            :class="
                                maintenance
                                    ? 'translate-x-[22px]'
                                    : 'translate-x-0.5'
                            "
                        />
                    </button>
                </div>

                <div class="flex items-center justify-between gap-4 p-5">
                    <div>
                        <h2 class="font-semibold">Member registration</h2>
                        <p class="text-sm text-muted-foreground">
                            Allow new member signups.
                        </p>
                        <p
                            v-if="registrationLocked"
                            class="mt-1 text-xs font-medium text-amber-600 dark:text-amber-400"
                        >
                            Locked off by env (REGISTRATION_ENABLED=false).
                        </p>
                    </div>
                    <button
                        type="button"
                        role="switch"
                        :aria-checked="registration"
                        aria-label="Member registration"
                        :disabled="registrationLocked || busy"
                        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors disabled:opacity-50"
                        :class="registration ? 'bg-emerald-500' : 'bg-muted'"
                        @click="toggleRegistration"
                    >
                        <span
                            class="inline-block size-5 rounded-full bg-white shadow transition-transform"
                            :class="
                                registration
                                    ? 'translate-x-[22px]'
                                    : 'translate-x-0.5'
                            "
                        />
                    </button>
                </div>

                <div class="flex items-center justify-between gap-4 p-5">
                    <div>
                        <h2 class="font-semibold">Application cache</h2>
                        <p class="text-sm text-muted-foreground">
                            Clear this site's cache keys (sitemaps, listings,
                            image variants). Other sites sharing Redis are not
                            touched.
                        </p>
                    </div>
                    <button
                        type="button"
                        :disabled="busy"
                        class="inline-flex h-9 shrink-0 items-center justify-center rounded-md border border-input bg-background px-4 text-sm font-medium hover:bg-accent disabled:opacity-50"
                        @click="clearCache"
                    >
                        Clear cache
                    </button>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
