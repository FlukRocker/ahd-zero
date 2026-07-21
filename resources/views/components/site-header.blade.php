@php
    $nav = [
        ['label' => 'หน้าแรก', 'href' => '/'],
        ['label' => 'ซับไทย', 'href' => '/category/1'],
        ['label' => 'พากย์ไทย', 'href' => '/category/2'],
        ['label' => 'เดอะมูฟวี่', 'href' => '/category/3'],
        ['label' => 'สตูดิโอ', 'href' => '/studios'],
        ['label' => 'แจ้งปัญหา', 'href' => 'https://www.facebook.com/animehdzeroo.v2'],
    ];
    $cur = request()->getPathInfo(); // '/', '/category/1', ...
    $isActive = fn (string $href): bool => $href === '/' ? $cur === '/' : str_starts_with($cur, $href);
@endphp

<header
    x-data="{ scrolled: false, mobileNavOpen: false, userMenuOpen: false }"
    x-init="scrolled = window.scrollY > 20"
    @scroll.window.passive="scrolled = window.scrollY > 20"
    class="sticky top-0 z-50 transition-all"
    :class="scrolled ? 'glass' : 'bg-transparent'"
    :style="scrolled ? 'border-bottom: 1px solid hsl(var(--border-ahd));' : 'border-bottom: 1px solid transparent;'"
>
    <div class="mx-auto flex h-[68px] max-w-[1440px] items-center gap-8 px-6 lg:px-10">
        <a href="/" class="flex shrink-0 items-center gap-2">
            <div class="relative flex h-8 w-8 items-center justify-center rounded-lg" style="background: linear-gradient(135deg, hsl(var(--accent)), hsl(var(--accent) / 0.6));">
                <span class="font-display text-xl leading-none text-white italic" style="transform: translateY(-1px)">z</span>
            </div>
            <div class="leading-tight">
                <div class="font-display text-[22px] leading-none italic">Zero</div>
                <div class="font-mono text-[9px] tracking-[0.22em] uppercase" style="color: hsl(var(--fg-muted))">anime · hd</div>
            </div>
        </a>

        <nav class="hidden items-center gap-6 text-[14px] lg:flex">
            @foreach ($nav as $n)
                @if (str_starts_with($n['href'], 'http'))
                    <a href="{{ $n['href'] }}" target="_blank" rel="noopener noreferrer" class="u-grow py-1" style="color: hsl(var(--fg-muted))">{{ $n['label'] }}</a>
                @else
                    <a href="{{ $n['href'] }}" class="u-grow py-1" style="{{ $isActive($n['href']) ? 'color: hsl(var(--fg)); font-weight: 500;' : 'color: hsl(var(--fg-muted));' }}">{{ $n['label'] }}</a>
                @endif
            @endforeach
        </nav>

        <div class="hidden flex-1 justify-center md:flex">
            <button type="button" class="flex w-[340px] items-center gap-3 rounded-full px-4 py-2" style="background: hsl(var(--bg-soft)); border: 1px solid hsl(var(--border-ahd));" @click="searchOpen = true">
                <x-icon name="search" :size="16" />
                <span class="text-[13px]" style="color: hsl(var(--fg-faint))">ค้นหาอนิเมะ สตูดิโอ นักพากย์…</span>
                <span class="ml-auto rounded px-1.5 py-0.5 font-mono text-[10px]" style="background: hsl(var(--bg-elev)); border: 1px solid hsl(var(--border-ahd));">⌘K</span>
            </button>
        </div>

        <div class="ml-auto flex items-center gap-2">
            <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full" style="background: hsl(var(--bg-soft))" title="สลับธีม" aria-label="สลับธีมสว่าง/มืด" @click="$store.appearance.toggle()">
                <span x-text="$store.appearance.isDark ? '☼' : '☾'" aria-hidden="true">☾</span>
            </button>
            <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full lg:hidden" style="background: hsl(var(--bg-soft))" aria-label="เมนู" :aria-expanded="mobileNavOpen" @click="mobileNavOpen = !mobileNavOpen">
                <template x-if="!mobileNavOpen"><x-icon name="menu" :size="18" /></template>
                <template x-if="mobileNavOpen"><x-icon name="close" :size="18" /></template>
            </button>
            <button type="button" class="flex h-10 w-10 items-center justify-center rounded-full md:hidden" style="background: hsl(var(--bg-soft))" aria-label="ค้นหา" @click="searchOpen = true">
                <x-icon name="search" :size="18" />
            </button>

            @if ($memberAuth)
                <div class="relative" @click.outside="userMenuOpen = false">
                    <button type="button" class="flex items-center gap-2 rounded-full py-1 pr-3 pl-1" style="background: hsl(var(--bg-soft))" aria-label="เมนูผู้ใช้" :aria-expanded="userMenuOpen" @click="userMenuOpen = !userMenuOpen">
                        <div class="font-display flex h-8 w-8 items-center justify-center rounded-full bg-cover italic" style="{{ $memberAuth['avatar'] ? "background-image: url('".e($memberAuth['avatar'])."');" : 'background: hsl(var(--accent)); color: hsl(var(--accent-fg));' }}">
                            @unless ($memberAuth['avatar'])<span>{{ mb_substr($memberAuth['name'], 0, 1) }}</span>@endunless
                        </div>
                        <span class="hidden text-[13px] md:inline">{{ explode(' ', $memberAuth['name'])[0] }}</span>
                    </button>
                    <div x-show="userMenuOpen" x-transition x-cloak class="absolute right-0 top-full mt-2 w-52 overflow-hidden rounded-xl" style="background: hsl(var(--bg-elev)); border: 1px solid hsl(var(--border-strong)); box-shadow: 0 12px 32px rgba(0,0,0,0.18); z-index: 60;">
                        <div class="border-b px-4 py-3" style="border-color: hsl(var(--border-ahd))">
                            <div class="font-medium text-[13px] truncate" style="color: hsl(var(--fg))">{{ $memberAuth['name'] }}</div>
                            <div class="font-mono text-[11px] truncate" style="color: hsl(var(--fg-muted))">{{ $memberAuth['email'] }}</div>
                        </div>
                        <a href="/member/settings/profile" class="block px-4 py-2 text-[13px]" style="color: hsl(var(--fg))">ตั้งค่าโปรไฟล์</a>
                        <a href="/member/settings/password" class="block px-4 py-2 text-[13px]" style="color: hsl(var(--fg))">เปลี่ยนรหัสผ่าน</a>
                        <form method="POST" action="/member/logout">
                            @csrf
                            <button type="submit" class="block w-full border-t px-4 py-2 text-left text-[13px]" style="color: hsl(var(--accent)); border-color: hsl(var(--border-ahd));">ออกจากระบบ</button>
                        </form>
                    </div>
                </div>
            @else
                <a href="/member/login" class="btn btn-ghost hidden px-3 py-2 text-[13px] md:inline-flex">เข้าสู่ระบบ</a>
            @endif
        </div>
    </div>

    {{-- Mobile nav drawer --}}
    <div x-show="mobileNavOpen" x-transition x-cloak class="border-t lg:hidden" style="background: hsl(var(--bg)); border-color: hsl(var(--border-ahd));">
        <nav class="mx-auto flex max-w-[1440px] flex-col gap-1 px-6 py-3 lg:px-10">
            @foreach ($nav as $n)
                @if (str_starts_with($n['href'], 'http'))
                    <a href="{{ $n['href'] }}" target="_blank" rel="noopener noreferrer" class="rounded-lg px-3 py-2 text-[14px]" style="color: hsl(var(--fg-muted))" @click="mobileNavOpen = false">{{ $n['label'] }}</a>
                @else
                    <a href="{{ $n['href'] }}" class="rounded-lg px-3 py-2 text-[14px]" style="{{ $isActive($n['href']) ? 'background: hsl(var(--bg-soft)); color: hsl(var(--fg)); font-weight: 500;' : 'color: hsl(var(--fg-muted));' }}" @click="mobileNavOpen = false">{{ $n['label'] }}</a>
                @endif
            @endforeach
            @unless ($memberAuth)
                <a href="/member/login" class="mt-2 rounded-lg px-3 py-2 text-[14px] font-medium" style="background: hsl(var(--accent)); color: hsl(var(--accent-fg));" @click="mobileNavOpen = false">เข้าสู่ระบบ</a>
            @endunless
        </nav>
    </div>
</header>
