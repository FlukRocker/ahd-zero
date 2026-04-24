<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import FrontLayout from '@/layouts/FrontLayout.vue';
import AhdIcon from '@/components/ahd/AhdIcon.vue';
import { useSeo } from '@/composables/useSeo';

const page = usePage<{ siteConfig?: { registrationEnabled?: boolean } }>();
const registrationEnabled = computed(
    () => page.props.siteConfig?.registrationEnabled !== false,
);

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function submit() {
    form.post('/member/login', {
        onFinish: () => form.reset('password'),
    });
}

useSeo(() => ({ title: 'เข้าสู่ระบบ', robots: 'noindex, follow' }));
</script>

<template>
    <Head title="เข้าสู่ระบบ" />
    <FrontLayout>
        <section class="max-w-md mx-auto px-6 lg:px-10 pt-20 pb-24">
            <div class="font-mono text-[11px] tracking-[0.25em] uppercase mb-3" style="color: hsl(var(--fg-muted));">
                สมาชิก
            </div>
            <h1 class="font-display italic leading-none mb-8" style="font-size: clamp(44px, 6vw, 64px);">
                ยินดีต้อนรับกลับ
            </h1>

            <form class="space-y-5" @submit.prevent="submit">
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
                        autocomplete="current-password"
                        required
                        class="w-full px-4 py-3 rounded-xl outline-none"
                        style="background: hsl(var(--bg-elev)); border: 1px solid hsl(var(--border-ahd));"
                    />
                </div>

                <label class="flex items-center gap-2 text-[13px]" style="color: hsl(var(--fg-muted));">
                    <input v-model="form.remember" type="checkbox" />
                    จดจำฉัน
                </label>

                <button
                    type="submit"
                    class="btn btn-primary w-full justify-center"
                    :disabled="form.processing"
                >
                    <AhdIcon name="arrow" :size="14" /> เข้าสู่ระบบ
                </button>

                <p
                    v-if="registrationEnabled"
                    class="text-[13px] text-center"
                    style="color: hsl(var(--fg-muted));"
                >
                    ยังไม่มีบัญชี?
                    <Link href="/member/register" class="u-grow" style="color: hsl(var(--accent));">สมัครสมาชิก</Link>
                </p>
                <p
                    v-else
                    class="text-[13px] text-center font-mono"
                    style="color: hsl(var(--fg-faint));"
                >ปิดรับสมาชิกใหม่</p>
            </form>
        </section>
    </FrontLayout>
</template>
