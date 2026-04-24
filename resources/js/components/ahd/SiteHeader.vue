<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useAppearance } from '@/composables/useAppearance';
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
        :style="scrolled ? 'border-bottom: 1px solid hsl(var(--border-ahd));' : 'border-bottom: 1px solid transparent;'"
    >
        <div class="max-w-[1440px] mx-auto px-6 lg:px-10 flex items-center gap-8 h-[68px]">
            <Link href="/" class="flex items-center gap-2 shrink-0">
                <div
                    class="relative w-8 h-8 rounded-lg flex items-center justify-center"
                    style="background: linear-gradient(135deg, hsl(var(--accent)), hsl(var(--accent) / 0.6));"
                >
                    <span class="text-white font-display text-xl italic leading-none" style="transform: translateY(-1px);">z</span>
                </div>
                <div class="leading-tight">
                    <div class="font-display text-[22px] italic leading-none">Zero</div>
                    <div
                        class="font-mono text-[9px] tracking-[0.22em] uppercase"
                        style="color: hsl(var(--fg-muted));"
                    >anime · hd</div>
                </div>
            </Link>

            <nav class="hidden lg:flex items-center gap-6 text-[14px]">
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
                >{{ n.label }}</Link>
            </nav>

            <div class="flex-1 hidden md:flex justify-center">
                <button
                    type="button"
                    class="flex items-center gap-3 px-4 py-2 rounded-full w-[340px]"
                    style="background: hsl(var(--bg-soft)); border: 1px solid hsl(var(--border-ahd));"
                    @click="emit('open-search')"
                >
                    <AhdIcon name="search" :size="16" />
                    <span class="text-[13px]" style="color: hsl(var(--fg-faint));">
                        ค้นหาอนิเมะ สตูดิโอ นักพากย์…
                    </span>
                    <span
                        class="ml-auto font-mono text-[10px] px-1.5 py-0.5 rounded"
                        style="background: hsl(var(--bg-elev)); border: 1px solid hsl(var(--border-ahd));"
                    >⌘K</span>
                </button>
            </div>

            <div class="flex items-center gap-2 ml-auto">
                <button
                    type="button"
                    class="w-10 h-10 rounded-full flex items-center justify-center"
                    style="background: hsl(var(--bg-soft));"
                    title="สลับธีม"
                    @click="toggleTheme"
                >
                    <span v-if="appearance === 'dark'">☼</span>
                    <span v-else>☾</span>
                </button>
                <button
                    type="button"
                    class="w-10 h-10 rounded-full flex items-center justify-center md:hidden"
                    style="background: hsl(var(--bg-soft));"
                    @click="emit('open-search')"
                >
                    <AhdIcon name="search" :size="18" />
                </button>

                <template v-if="member">
                    <Link
                        href="#"
                        class="flex items-center gap-2 pr-3 pl-1 py-1 rounded-full"
                        style="background: hsl(var(--bg-soft));"
                    >
                        <div
                            class="w-8 h-8 rounded-full bg-cover flex items-center justify-center font-display italic"
                            :style="
                                member.avatar
                                    ? `background-image: url('${member.avatar}');`
                                    : `background: hsl(var(--accent)); color: hsl(var(--accent-fg));`
                            "
                        >
                            <span v-if="!member.avatar">{{ member.name.charAt(0) }}</span>
                        </div>
                        <span class="text-[13px] hidden md:inline">{{ member.name.split(' ')[0] }}</span>
                    </Link>
                </template>
                <template v-else>
                    <Link
                        href="/member/login"
                        class="hidden md:inline-flex btn btn-ghost text-[13px] py-2 px-3"
                    >เข้าสู่ระบบ</Link>
                </template>
            </div>
        </div>
    </header>
</template>
