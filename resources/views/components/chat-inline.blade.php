{{-- Chat embedded in a page (the homepage). Same Alpine component as the
     floating bubble, but always open. Nothing is fetched and no socket opens
     until the section scrolls into view, so it costs nothing above the fold. --}}
<section class="mx-auto mt-20 max-w-[1440px] px-6 lg:px-10" aria-label="แชท">
    <x-section-header eyebrow="พูดคุยกับเพื่อน ๆ" title="แชทสด" link="เปิดแชทเต็ม" :linkHref="route('chat.show', [], false)" />

    <div
        data-chat-inline
        x-data="chatMini"
        x-init="open = true; const io = new IntersectionObserver((entries) => { if (entries.some((e) => e.isIntersecting)) { io.disconnect(); load().then(() => $nextTick(() => scrollDown())); } }); io.observe($el)"
        class="flex h-[min(30rem,75vh)] flex-col overflow-hidden rounded-xl border border-white/10 bg-neutral-900 text-white shadow-lg"
    >
        <x-chat.name-styles />

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

        <ul class="flex-1 space-y-1 overflow-y-auto p-2" x-ref="list" x-show="config !== null">
            <template x-for="line in messages" :key="line.id">
                <x-chat.message />
            </template>
        </ul>

        <p class="px-3 pb-2 text-xs text-amber-400" x-show="notice" x-text="notice"></p>

        <template x-if="config !== null && config.canPost">
            <x-chat.composer compact placeholder="พิมพ์ข้อความ" send="ส่ง" />
        </template>
        <p class="px-3 pb-2 text-xs text-red-400" x-show="error" x-text="error"></p>
        <template x-if="config !== null && !config.canPost">
            <p class="border-t border-white/10 p-3 text-sm opacity-70">
                <a href="{{ route('member.login') }}" class="underline">เข้าสู่ระบบเพื่อร่วมสนทนา</a>
            </p>
        </template>

        <x-chat.report-modal />
    </div>
</section>
