{{-- Floating mini chat. Closed, it is a button and nothing else: no fetch, no
     socket. The first open loads the lobby from /chat/mini and connects the
     socket (see chatMini in chat/lobby.js). Not shown on chat pages. --}}
@unless (request()->routeIs('chat.*'))
    <div
        data-chat-bubble
        x-data="chatMini"
        @keydown.escape.window="open = false"
        class="fixed bottom-4 right-4 z-40 flex flex-col items-end gap-3 sm:bottom-6 sm:right-6"
    >
        <section
            x-show="open"
            x-cloak
            role="dialog"
            aria-label="แชท"
            class="flex h-[min(28rem,70vh)] w-[min(22rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-xl border border-white/10 bg-neutral-900 text-white shadow-2xl"
        >
            <x-chat.name-styles />
            <header class="flex items-center justify-between border-b border-white/10 px-3 py-2 text-sm">
                <span class="font-medium"># <span x-text="config?.room?.name ?? 'Lobby'"></span></span>
                <span class="flex items-center gap-3">
                    <a href="{{ route('chat.show', [], false) }}" class="text-xs underline opacity-70 hover:opacity-100">เปิดแชทเต็ม</a>
                    <button type="button" class="px-1 opacity-70 hover:opacity-100" @click="open = false" aria-label="ปิด">&times;</button>
                </span>
            </header>

            <p class="p-3 text-sm opacity-70" x-show="loading">…</p>
            <p class="p-3 text-sm text-amber-400" x-show="loadError">
                โหลดแชทไม่ได้ <button type="button" class="underline" @click="load()">ลองใหม่</button>
            </p>

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
        </section>

        <x-chat.report-modal />

        <button
            type="button"
            @click="toggle()"
            :aria-expanded="open"
            aria-label="แชท"
            class="flex h-12 w-12 items-center justify-center rounded-full bg-[hsl(var(--accent-strong))] text-[hsl(var(--accent-fg))] shadow-lg transition hover:scale-105 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[hsl(var(--accent-strong))] sm:h-14 sm:w-14"
        >
            <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.6 8.6 0 0 1-3.4-.7L3 21l1.8-5.1A8.4 8.4 0 1 1 21 11.5Z" />
            </svg>
        </button>
    </div>
@endunless
