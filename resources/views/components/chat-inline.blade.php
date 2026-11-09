{{-- Chat embedded in a page (the homepage). Same Alpine component as the
     floating bubble, but always open: messages on the left, who is online on
     the right, composer underneath. Nothing is fetched and no socket opens
     until the section scrolls into view, so it costs nothing above the fold. --}}
<section class="mx-auto mt-20 max-w-[1440px] px-6 lg:px-10" aria-label="แชท">
    <x-section-header eyebrow="พูดคุยกับเพื่อน ๆ" title="แชทสด" link="เปิดแชทเต็ม" :linkHref="route('chat.show', [], false)" />

    <div
        data-chat-inline
        x-data="chatMini"
        x-init="open = true; const io = new IntersectionObserver((entries) => { if (entries.some((e) => e.isIntersecting)) { io.disconnect(); load().then(() => $nextTick(() => scrollDown())); } }); io.observe($el)"
        class="relative isolate overflow-hidden rounded-2xl border border-white/15 text-white shadow-2xl"
        style="background: linear-gradient(160deg, #7a2f4b 0%, #a8475e 38%, #d9794f 100%);"
    >
        {{-- Wallpaper: two soft discs over the gradient, pure CSS (nothing to download). --}}
        <div class="pointer-events-none absolute -top-20 left-1/4 -z-10 h-96 w-96 rounded-full bg-white/10" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-32 right-1/4 -z-10 h-[26rem] w-[26rem] rounded-full" style="background: rgba(255, 214, 170, 0.12);" aria-hidden="true"></div>
        <x-chat.name-styles />

        <header class="flex items-center justify-between gap-3 border-b border-white/15 bg-black/25 px-4 py-2.5 text-sm backdrop-blur">
            <span class="flex items-center gap-2 font-semibold">
                <span class="opacity-50">#</span>
                <span x-text="config?.room?.name ?? 'Lobby'"></span>
            </span>
            <span class="flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-2.5 py-1 text-xs font-medium text-emerald-400" x-show="config?.canPost" x-cloak>
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                <span x-text="roster.length"></span> ออนไลน์
            </span>
        </header>

        <div class="grid gap-3 p-3 md:grid-cols-[minmax(0,1fr)_16rem]">
            <div class="flex h-[min(32rem,75vh)] min-w-0 flex-col overflow-hidden rounded-2xl border border-white/15 backdrop-blur" style="background: rgba(20, 10, 16, 0.6);">
                <p class="p-3 text-sm opacity-70" x-show="loading">…</p>
                <p class="p-3 text-sm text-amber-400" x-show="loadError" x-cloak>
                    โหลดแชทไม่ได้ <button type="button" class="underline" @click="load()">ลองใหม่</button>
                </p>

                <div class="flex items-start gap-2 border-b border-white/10 bg-amber-400/10 px-3 py-2 text-xs" x-show="pinned" x-cloak>
                    <span aria-hidden="true">📌</span>
                    <button type="button" class="min-w-0 flex-1 truncate text-left" @click="jumpTo(pinned.id)">
                        <strong x-text="pinned?.name"></strong> <span class="opacity-80" x-text="pinnedText()"></span>
                    </button>
                    <button type="button" class="shrink-0 underline opacity-70 hover:opacity-100" x-show="canPin()" @click="moderate(pinned, 'unpin')">เลิกปักหมุด</button>
                </div>

                <ul class="flex-1 space-y-0.5 overflow-y-auto p-2" x-ref="list" x-show="config !== null">
                    <template x-for="line in messages" :key="line.id">
                        <x-chat.message />
                    </template>
                </ul>

                <p class="px-3 pb-2 text-xs text-amber-400" x-show="notice" x-text="notice"></p>

                <template x-if="config !== null && config.canPost">
                    <x-chat.composer placeholder="พิมพ์ข้อความ..." send="ส่ง" />
                </template>
                <p class="px-3 pb-2 text-xs text-red-400" x-show="error" x-text="error"></p>
                <template x-if="config !== null && !config.canPost">
                    <p class="border-t border-white/10 bg-black/20 p-4 text-center text-sm opacity-80">
                        <a href="{{ route('member.login') }}" class="font-medium underline">เข้าสู่ระบบ</a> เพื่อร่วมสนทนา
                    </p>
                </template>
            </div>

            <div class="hidden overflow-hidden rounded-2xl border border-white/15 backdrop-blur md:block" style="background: rgba(20, 10, 16, 0.6);">
                <x-chat.roster bare />
            </div>
        </div>

        <x-chat.report-modal />
    </div>
</section>
