<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import FrontLayout from '@/layouts/FrontLayout.vue';
import AhdIcon from '@/components/ahd/AhdIcon.vue';
import { useSeo } from '@/composables/useSeo';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/member/register', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}

useSeo(() => ({ title: 'สมัครสมาชิก', robots: 'noindex, follow' }));
</script>

<template>
    <Head title="สมัครสมาชิก" />
    <FrontLayout>
        <section class="max-w-md mx-auto px-6 lg:px-10 pt-20 pb-24">
            <div class="font-mono text-[11px] tracking-[0.25em] uppercase mb-3" style="color: hsl(var(--fg-muted));">
                สมาชิก
            </div>
            <h1 class="font-display italic leading-none mb-8" style="font-size: clamp(44px, 6vw, 64px);">
                สมัครสมาชิก
            </h1>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label class="font-mono text-[10px] tracking-widest uppercase mb-2 block" style="color: hsl(var(--fg-muted));">
                        ชื่อ
                    </label>
                    <input
                        v-model="form.name"
                        type="text"
                        autocomplete="name"
                        required
                        class="w-full px-4 py-3 rounded-xl outline-none"
                        style="background: hsl(var(--bg-elev)); border: 1px solid hsl(var(--border-ahd));"
                    />
                    <p v-if="form.errors.name" class="text-[12px] mt-2" style="color: hsl(var(--accent));">
                        {{ form.errors.name }}
                    </p>
                </div>

                <div>
                    <label class="font-mono text-[10px] tracking-widest uppercase mb-2 block" style="color: hsl(var(--fg-muted));">
                        อีเมล
                    </label>
                    <input
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        required
                        class="w-full px-4 py-3 rounded-xl outline-none"
                        style="background: hsl(var(--bg-elev)); border: 1px solid hsl(var(--border-ahd));"
                    />
                    <p v-if="form.errors.email" class="text-[12px] mt-2" style="color: hsl(var(--accent));">
                        {{ form.errors.email }}
                    </p>
                </div>

                <div>
                    <label class="font-mono text-[10px] tracking-widest uppercase mb-2 block" style="color: hsl(var(--fg-muted));">
                        รหัสผ่าน
                    </label>
                    <input
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="w-full px-4 py-3 rounded-xl outline-none"
                        style="background: hsl(var(--bg-elev)); border: 1px solid hsl(var(--border-ahd));"
                    />
                    <p v-if="form.errors.password" class="text-[12px] mt-2" style="color: hsl(var(--accent));">
                        {{ form.errors.password }}
                    </p>
                </div>

                <div>
                    <label class="font-mono text-[10px] tracking-widest uppercase mb-2 block" style="color: hsl(var(--fg-muted));">
                        ยืนยันรหัสผ่าน
                    </label>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                        class="w-full px-4 py-3 rounded-xl outline-none"
                        style="background: hsl(var(--bg-elev)); border: 1px solid hsl(var(--border-ahd));"
                    />
                </div>

                <button
                    type="submit"
                    class="btn btn-primary w-full justify-center"
                    :disabled="form.processing"
                >
                    <AhdIcon name="arrow" :size="14" /> สมัครสมาชิก
                </button>

                <p class="text-[13px] text-center" style="color: hsl(var(--fg-muted));">
                    มีบัญชีแล้ว?
                    <Link href="/member/login" class="u-grow" style="color: hsl(var(--accent));">เข้าสู่ระบบ</Link>
                </p>
            </form>
        </section>
    </FrontLayout>
</template>
