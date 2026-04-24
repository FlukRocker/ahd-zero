<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AhdIcon from './AhdIcon.vue';

interface MemberInfo {
    id: string;
    name: string;
    email: string;
    avatar?: string | null;
}

const emit = defineEmits<{ (e: 'open-search'): void }>();

const { appearance, toggleTheme } = useAppearance();
const page = usePage<{ memberAuth?: { member: MemberInfo | null } }>();
const member = computed(() => page.props.memberAuth?.member ?? null);

const scrolled = ref(false);
function onScroll() {
    scrolled.value = window.scrollY > 20;
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
});

onBeforeUnmount(() => window.removeEventListener('scroll', onScroll));

const nav = [
    { id: 'home', label: 'หน้าแรก', href: '/' },
    { id: 'sub', label: 'ซับไทย', href: '/category/1' },
    { id: 'dub', label: 'พากย์ไทย', href: '/category/2' },
    { id: 'movies', label: 'เดอะมูฟวี่', href: '/category/3' },
    { id: 'studios', label: 'สตูดิโอ', href: '/studios' },
];

function isActive(href: string): boolean {
    const current = page.url;
    if (href === '/') return current === '/';
    return current.startsWith(href);
}
</script>

<template>
    <header
        class="sticky top-0 z-50 transition-all"
        :class="scrolled ? 'glass' : 'bg-transparent'"
        :style="
            scrolled
                ? 'border-bottom: 1px solid hsl(var(--border-ahd));'
                : 'border-bottom: 1px solid transparent;'
        "
    >
        <div
            class="mx-auto flex h-[68px] max-w-[1440px] items-center gap-8 px-6 lg:px-10"
        >
            <Link href="/" class="flex shrink-0 items-center gap-2">
                <div
                    class="relative flex h-8 w-8 items-center justify-center rounded-lg"
                    style="
                        background: linear-gradient(
                            135deg,
                            hsl(var(--accent)),
                            hsl(var(--accent) / 0.6)
                        );
                    "
                >
                    <span
                        class="font-display text-xl leading-none text-white italic"
                        style="transform: translateY(-1px)"
                        >z</span
                    >
                </div>
                <div class="leading-tight">
                    <div class="font-display text-[22px] leading-none italic">
                        Zero
                    </div>
                    <div
                        class="font-mono text-[9px] tracking-[0.22em] uppercase"
                        style="color: hsl(var(--fg-muted))"
                    >
                        anime · hd
                    </div>
                </div>
            </Link>

            <nav class="hidden items-center gap-6 text-[14px] lg:flex">
                <Link
                    v-for="n in nav"
                    :key="n.id"
                    :href="n.href"
                    class="u-grow py-1"
                    :style="
                        isActive(n.href)
                            ? 'color: hsl(var(--fg)); font-weight: 500;'
                            : 'color: hsl(var(--fg-muted));'
                    "
                    >{{ n.label }}</Link
                >
            </nav>

            <div class="hidden flex-1 justify-center md:flex">
                <button
                    type="button"
                    class="flex w-[340px] items-center gap-3 rounded-full px-4 py-2"
                    style="
                        background: hsl(var(--bg-soft));
                        border: 1px solid hsl(var(--border-ahd));
                    "
                    @click="emit('open-search')"
                >
                    <AhdIcon name="search" :size="16" />
                    <span
                        class="text-[13px]"
                        style="color: hsl(var(--fg-faint))"
                    >
                        ค้นหาอนิเมะ สตูดิโอ นักพากย์…
                    </span>
                    <span
                        class="ml-auto rounded px-1.5 py-0.5 font-mono text-[10px]"
                        style="
                            background: hsl(var(--bg-elev));
                            border: 1px solid hsl(var(--border-ahd));
                        "
                        >⌘K</span
                    >
                </button>
            </div>

            <div class="ml-auto flex items-center gap-2">
                <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-full"
                    style="background: hsl(var(--bg-soft))"
                    title="สลับธีม"
                    @click="toggleTheme"
                >
                    <span v-if="appearance === 'dark'">☼</span>
                    <span v-else>☾</span>
                </button>
                <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-full md:hidden"
                    style="background: hsl(var(--bg-soft))"
                    @click="emit('open-search')"
                >
                    <AhdIcon name="search" :size="18" />
                </button>

                <template v-if="member">
                    <Link
                        href="#"
                        class="flex items-center gap-2 rounded-full py-1 pr-3 pl-1"
                        style="background: hsl(var(--bg-soft))"
                    >
                        <div
                            class="font-display flex h-8 w-8 items-center justify-center rounded-full bg-cover italic"
                            :style="
                                member.avatar
                                    ? `background-image: url('${member.avatar}');`
                                    : `background: hsl(var(--accent)); color: hsl(var(--accent-fg));`
                            "
                        >
                            <span v-if="!member.avatar">{{
                                member.name.charAt(0)
                            }}</span>
                        </div>
                        <span class="hidden text-[13px] md:inline">{{
                            member.name.split(' ')[0]
                        }}</span>
                    </Link>
                </template>
                <template v-else>
                    <Link
                        href="/member/login"
                        class="btn btn-ghost hidden px-3 py-2 text-[13px] md:inline-flex"
                        >เข้าสู่ระบบ</Link
                    >
                </template>
            </div>
        </div>
    </header>
</template>
