<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { Link, router, usePage } from '@inertiajs/vue3';
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

const userMenuOpen = ref(false);
const userMenuRef = ref<HTMLDivElement | null>(null);
const mobileNavOpen = ref(false);

function onClickOutsideUserMenu(e: MouseEvent) {
    if (
        userMenuOpen.value &&
        userMenuRef.value &&
        !userMenuRef.value.contains(e.target as Node)
    ) {
        userMenuOpen.value = false;
    }
}

function logout() {
    userMenuOpen.value = false;
    router.post('/member/logout');
}

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    document.addEventListener('click', onClickOutsideUserMenu);
    onScroll();
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onScroll);
    document.removeEventListener('click', onClickOutsideUserMenu);
});

const nav = [
    { id: 'home', label: 'หน้าแรก', href: '/' },
    { id: 'sub', label: 'ซับไทย', href: '/category/1' },
    { id: 'dub', label: 'พากย์ไทย', href: '/category/2' },
    { id: 'movies', label: 'เดอะมูฟวี่', href: '/category/3' },
    { id: 'studios', label: 'สตูดิโอ', href: '/studios' },
    { id: 'fanpage', label: 'แจ้งปัญหา', href: 'https://www.facebook.com/animehdzeroo.v2' },
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
                    aria-label="สลับธีมสว่าง/มืด"
                    @click="toggleTheme"
                >
                    <span v-if="appearance === 'dark'" aria-hidden="true">☼</span>
                    <span v-else aria-hidden="true">☾</span>
                </button>
                <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-full lg:hidden"
                    style="background: hsl(var(--bg-soft))"
                    aria-label="เมนู"
                    :aria-expanded="mobileNavOpen"
                    @click="mobileNavOpen = !mobileNavOpen"
                >
                    <AhdIcon :name="mobileNavOpen ? 'close' : 'menu'" :size="18" />
                </button>
                <button
                    type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-full md:hidden"
                    style="background: hsl(var(--bg-soft))"
                    aria-label="ค้นหา"
                    @click="emit('open-search')"
                >
                    <AhdIcon name="search" :size="18" />
                </button>

                <template v-if="member">
                    <div ref="userMenuRef" class="relative">
                        <button
                            type="button"
                            class="flex items-center gap-2 rounded-full py-1 pr-3 pl-1"
                            style="background: hsl(var(--bg-soft))"
                            aria-label="เมนูผู้ใช้"
                            :aria-expanded="userMenuOpen"
                            @click="userMenuOpen = !userMenuOpen"
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
                        </button>

                        <div
                            v-if="userMenuOpen"
                            class="absolute right-0 top-full mt-2 w-52 overflow-hidden rounded-xl"
                            style="
                                background: hsl(var(--bg-elev));
                                border: 1px solid hsl(var(--border-strong));
                                box-shadow: 0 12px 32px rgba(0, 0, 0, 0.18);
                                z-index: 60;
                            "
                        >
                            <div
                                class="border-b px-4 py-3"
                                style="border-color: hsl(var(--border-ahd))"
                            >
                                <div
                                    class="font-medium text-[13px] truncate"
                                    style="color: hsl(var(--fg))"
                                >
                                    {{ member.name }}
                                </div>
                                <div
                                    class="font-mono text-[11px] truncate"
                                    style="color: hsl(var(--fg-muted))"
                                >
                                    {{ member.email }}
                                </div>
                            </div>

                            <Link
                                href="/member/settings/profile"
                                class="block px-4 py-2 text-[13px]"
                                style="color: hsl(var(--fg))"
                                @click="userMenuOpen = false"
                            >
                                ตั้งค่าโปรไฟล์
                            </Link>
                            <Link
                                href="/member/settings/password"
                                class="block px-4 py-2 text-[13px]"
                                style="color: hsl(var(--fg))"
                                @click="userMenuOpen = false"
                            >
                                เปลี่ยนรหัสผ่าน
                            </Link>
                            <button
                                type="button"
                                class="block w-full border-t px-4 py-2 text-left text-[13px]"
                                style="
                                    color: hsl(var(--accent));
                                    border-color: hsl(var(--border-ahd));
                                "
                                @click="logout"
                            >
                                ออกจากระบบ
                            </button>
                        </div>
                    </div>
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

        <!-- Mobile nav drawer — slides under the header on lg breakpoint
             and below. Tapping a link closes the drawer so the user lands
             on the page without an extra dismiss step. -->
        <div
            v-if="mobileNavOpen"
            class="border-t lg:hidden"
            :style="{
                background: 'hsl(var(--bg))',
                borderColor: 'hsl(var(--border-ahd))',
            }"
        >
            <nav class="mx-auto flex max-w-[1440px] flex-col gap-1 px-6 py-3 lg:px-10">
                <Link
                    v-for="n in nav"
                    :key="n.id"
                    :href="n.href"
                    class="rounded-lg px-3 py-2 text-[14px]"
                    :style="
                        isActive(n.href)
                            ? 'background: hsl(var(--bg-soft)); color: hsl(var(--fg)); font-weight: 500;'
                            : 'color: hsl(var(--fg-muted));'
                    "
                    @click="mobileNavOpen = false"
                    >{{ n.label }}</Link
                >
                <Link
                    v-if="!member"
                    href="/member/login"
                    class="mt-2 rounded-lg px-3 py-2 text-[14px] font-medium"
                    style="
                        background: hsl(var(--accent));
                        color: hsl(var(--accent-fg));
                    "
                    @click="mobileNavOpen = false"
                >
                    เข้าสู่ระบบ
                </Link>
            </nav>
        </div>
    </header>
</template>
