<div
    x-show="searchOpen"
    x-cloak
    x-transition.opacity
    @keydown.escape.window="searchOpen = false"
    @click.self="searchOpen = false"
    class="fixed inset-0 z-[80] flex items-start justify-center px-6 pt-[12vh]"
    style="background: hsl(var(--bg) / 0.6); backdrop-filter: blur(12px);"
>
    <div class="glass w-full max-w-2xl rounded-3xl p-4">
        <form method="GET" action="{{ route('search.results') }}" class="flex items-center gap-3 px-4 py-3">
            <x-icon name="search" :size="18" />
            <input
                x-ref="searchInput"
                x-effect="if (searchOpen) $nextTick(() => $refs.searchInput.focus())"
                type="text"
                name="q"
                placeholder="ค้นหาอนิเมะ หมวดหมู่ สตูดิโอ…"
                class="flex-1 bg-transparent text-[15px] outline-none"
                autocomplete="off"
            >
            <span class="rounded px-1.5 py-0.5 font-mono text-[10px]" style="background: hsl(var(--bg-elev)); border: 1px solid hsl(var(--border-ahd));">↵</span>
            <button type="button" class="ml-2 opacity-60 hover:opacity-100" aria-label="ปิดค้นหา" @click="searchOpen = false">
                <x-icon name="close" :size="18" />
            </button>
        </form>
        <div class="px-4 pb-3 font-mono text-[12px]" style="color: hsl(var(--fg-faint))">กด Enter เพื่อค้นหา · Esc เพื่อปิด</div>
    </div>
</div>
