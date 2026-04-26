<script setup lang="ts">
import AhdIcon from '@/components/ahd/AhdIcon.vue';
import TurnstileWidget from '@/components/ahd/TurnstileWidget.vue';
import { useSeo } from '@/composables/useSeo';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const page = usePage<{
    siteConfig?: { turnstileSiteKey?: string | null };
}>();
const turnstileSiteKey = computed(
    () => page.props.siteConfig?.turnstileSiteKey ?? '',
);

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    'cf-turnstile-response': '',
});

const turnstileRef = ref<InstanceType<typeof TurnstileWidget> | null>(null);

function submit() {
    form.post('/member/register', {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
            form['cf-turnstile-response'] = '';
            turnstileRef.value?.reset();
        },
    });
}

useSeo(() => ({ title: 'สมัครสมาชิก', robots: 'noindex, follow' }));
</script>

<template>
    <Head title="สมัครสมาชิก" />
    <FrontLayout>
        <section class="mx-auto max-w-md px-6 pt-20 pb-24 lg:px-10">
            <div
                class="mb-3 font-mono text-[11px] tracking-[0.25em] uppercase"
                style="color: hsl(var(--fg-muted))"
            >
                สมาชิก
            </div>
            <h1
                class="font-display mb-8 leading-none italic"
                style="font-size: clamp(44px, 6vw, 64px)"
            >
                สมัครสมาชิก
            </h1>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label
                        class="mb-2 block font-mono text-[10px] tracking-widest uppercase"
                        style="color: hsl(var(--fg-muted))"
                    >
                        ชื่อ
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        autocomplete="name"
                        required
                        class="w-full rounded-xl px-4 py-3 outline-none"
                        style="
                            background: hsl(var(--bg-elev));
                            border: 1px solid hsl(var(--border-ahd));
                        "
                    />
                    <p
                        v-if="form.errors.name"
                        class="mt-2 text-[12px]"
                        style="color: hsl(var(--accent))"
                    >
                        {{ form.errors.name }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-2 block font-mono text-[10px] tracking-widest uppercase"
                        style="color: hsl(var(--fg-muted))"
                    >
                        อีเมล
                    </label>
                    <input
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        required
                        class="w-full rounded-xl px-4 py-3 outline-none"
                        style="
                            background: hsl(var(--bg-elev));
                            border: 1px solid hsl(var(--border-ahd));
                        "
                    />
                    <p
                        v-if="form.errors.email"
                        class="mt-2 text-[12px]"
                        style="color: hsl(var(--accent))"
                    >
                        {{ form.errors.email }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-2 block font-mono text-[10px] tracking-widest uppercase"
                        style="color: hsl(var(--fg-muted))"
                    >
                        รหัสผ่าน
                    </label>
                    <input
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="w-full rounded-xl px-4 py-3 outline-none"
                        style="
                            background: hsl(var(--bg-elev));
                            border: 1px solid hsl(var(--border-ahd));
                        "
                    />
                    <p
                        v-if="form.errors.password"
                        class="mt-2 text-[12px]"
                        style="color: hsl(var(--accent))"
                    >
                        {{ form.errors.password }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-2 block font-mono text-[10px] tracking-widest uppercase"
                        style="color: hsl(var(--fg-muted))"
                    >
                        ยืนยันรหัสผ่าน
                    </label>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="w-full rounded-xl px-4 py-3 outline-none"
                        style="
                            background: hsl(var(--bg-elev));
                            border: 1px solid hsl(var(--border-ahd));
                        "
                    />
                </div>

                <div v-if="turnstileSiteKey">
                    <TurnstileWidget
                        ref="turnstileRef"
                        v-model="form['cf-turnstile-response']"
                        :site-key="turnstileSiteKey"
                    />
                    <p
                        v-if="form.errors['cf-turnstile-response']"
                        class="mt-2 text-[12px]"
                        style="color: hsl(var(--accent))"
                    >
                        {{ form.errors['cf-turnstile-response'] }}
                    </p>
                </div>

                <button
                    type="submit"
                    class="btn btn-primary w-full justify-center"
                    :disabled="
                        form.processing ||
                        (!!turnstileSiteKey && !form['cf-turnstile-response'])
                    "
                >
                    <AhdIcon name="arrow" :size="14" /> สมัครสมาชิก
                </button>

                <p
                    class="text-center text-[13px]"
                    style="color: hsl(var(--fg-muted))"
                >
                    มีบัญชีแล้ว?
                    <Link
                        href="/member/login"
                        class="u-grow"
                        style="color: hsl(var(--accent))"
                        >เข้าสู่ระบบ</Link
                    >
                </p>
            </form>
        </section>
    </FrontLayout>
</template>
