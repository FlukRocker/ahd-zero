<script setup lang="ts">
import AhdIcon from '@/components/ahd/AhdIcon.vue';
import { useSeo } from '@/composables/useSeo';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

interface MemberProfile {
    name: string;
    email: string;
    avatar: string | null;
    bio: string | null;
}

const props = defineProps<{ member: MemberProfile }>();

const page = usePage<{ flash?: { status?: string } }>();
const flash = computed(() => page.props.flash?.status ?? null);

const form = useForm({
    name: props.member.name,
    avatar: props.member.avatar ?? '',
    bio: props.member.bio ?? '',
});

function submit() {
    form.patch('/member/settings/profile', { preserveScroll: true });
}

const deleteForm = useForm({ password: '' });
const showDelete = (window as Window & { __show?: boolean }).__show
    ? true
    : false;

useSeo(() => ({ title: 'ตั้งค่าโปรไฟล์', robots: 'noindex, nofollow' }));
</script>

<template>
    <Head title="ตั้งค่าโปรไฟล์" />
    <FrontLayout>
        <section class="mx-auto max-w-3xl px-6 pt-12 pb-20 lg:px-10">
            <div
                class="mb-3 font-mono text-[11px] tracking-[0.25em] uppercase"
                style="color: hsl(var(--fg-muted))"
            >
                ตั้งค่า
            </div>
            <h1
                class="font-display mb-8 leading-none italic"
                style="font-size: clamp(36px, 5vw, 48px)"
            >
                โปรไฟล์
            </h1>

            <nav class="mb-8 flex gap-4 border-b" style="border-color: hsl(var(--border-ahd))">
                <Link
                    href="/member/settings/profile"
                    class="border-b-2 px-1 py-2 text-[14px] font-medium"
                    style="border-color: hsl(var(--accent)); color: hsl(var(--fg))"
                >
                    โปรไฟล์
                </Link>
                <Link
                    href="/member/settings/password"
                    class="border-b-2 border-transparent px-1 py-2 text-[14px]"
                    style="color: hsl(var(--fg-muted))"
                >
                    รหัสผ่าน
                </Link>
            </nav>

            <div
                v-if="flash === 'profile-updated'"
                class="mb-6 rounded-xl px-4 py-3 text-[13px]"
                style="
                    background: hsl(var(--accent-soft));
                    color: hsl(var(--accent));
                "
            >
                บันทึกการเปลี่ยนแปลงเรียบร้อยแล้ว
            </div>

            <form class="space-y-5" @submit.prevent="submit">
                <div>
                    <label
                        class="mb-2 block font-mono text-[10px] tracking-widest uppercase"
                        style="color: hsl(var(--fg-muted))"
                    >
                        อีเมล
                    </label>
                    <input
                        :value="member.email"
                        type="email"
                        readonly
                        disabled
                        class="w-full rounded-xl px-4 py-3 outline-none"
                        style="
                            background: hsl(var(--bg-soft));
                            border: 1px solid hsl(var(--border-ahd));
                            color: hsl(var(--fg-muted));
                        "
                    />
                </div>

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
                        URL รูปโปรไฟล์
                    </label>
                    <input
                        v-model="form.avatar"
                        type="url"
                        placeholder="https://..."
                        class="w-full rounded-xl px-4 py-3 outline-none"
                        style="
                            background: hsl(var(--bg-elev));
                            border: 1px solid hsl(var(--border-ahd));
                        "
                    />
                    <p
                        v-if="form.errors.avatar"
                        class="mt-2 text-[12px]"
                        style="color: hsl(var(--accent))"
                    >
                        {{ form.errors.avatar }}
                    </p>
                </div>

                <div>
                    <label
                        class="mb-2 block font-mono text-[10px] tracking-widest uppercase"
                        style="color: hsl(var(--fg-muted))"
                    >
                        แนะนำตัว
                    </label>
                    <textarea
                        v-model="form.bio"
                        rows="4"
                        maxlength="500"
                        class="w-full resize-none rounded-xl px-4 py-3 outline-none"
                        style="
                            background: hsl(var(--bg-elev));
                            border: 1px solid hsl(var(--border-ahd));
                        "
                    />
                    <p
                        v-if="form.errors.bio"
                        class="mt-2 text-[12px]"
                        style="color: hsl(var(--accent))"
                    >
                        {{ form.errors.bio }}
                    </p>
                </div>

                <button
                    type="submit"
                    class="btn btn-primary justify-center px-6"
                    :disabled="form.processing"
                >
                    <AhdIcon name="arrow" :size="14" /> บันทึก
                </button>
            </form>
        </section>
    </FrontLayout>
</template>
