{{-- One chat line, one block per message. Alpine renders it client-side from
     the `line` loop variable. Text goes through x-text (a text node), images
     through :src of a fixed <img>; styles are an object of server-validated
     custom properties applied with style.setProperty. Never x-html, never a
     built style string. --}}
<li class="chat-row relative flex items-start gap-3 rounded-lg px-3 py-2 text-sm" :class="{ 'z-20': menuOpen === line.id }" :id="'msg-' + line.id" :data-mention="mentionsMe(line)" :style="styleOf(line)">
    {{-- The initial is always there; the profile picture sits on top of it and
         hides itself if it fails to load. --}}
    <span
        class="chat-avatar relative flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full text-sm font-semibold uppercase"
        :data-rank="line.rank"
        aria-hidden="true"
        x-data="{ broken: false }"
    >
        <span x-text="(line.name || '?').charAt(0)"></span>
        <img
            x-show="avatarSrc(line) && !broken"
            :src="avatarSrc(line)"
            alt=""
            loading="lazy"
            referrerpolicy="no-referrer"
            class="absolute inset-0 h-full w-full object-cover"
            x-on:error="broken = true"
        >
    </span>
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
            <span
                class="chat-badge"
                :data-rank="line.rank"
                x-show="rankLabel(line)"
                x-text="rankLabel(line)"
            ></span>
            <span class="chat-icon" x-show="iconOf(line)" :data-icon="iconOf(line)" aria-hidden="true"></span>
            <span
                class="chat-name text-[15px]"
                :data-anim="animOf(line)"
                :class="{ 'chat-name-gradient': hasGradient(line) }"
                x-text="line.name"
            ></span>
            <time class="text-[11px] opacity-50" x-text="timeOf(line)"></time>
        </div>
        {{-- Quoted original: plain text only, click to jump to it. --}}
        <button
            type="button"
            class="chat-reply mb-0.5 block max-w-full truncate text-left text-xs opacity-70 hover:opacity-100"
            x-show="replyOf(line)"
            @click="jumpTo(replyOf(line).id)"
        >
            <span aria-hidden="true">↪</span>
            <template x-if="replyOf(line) && replyOf(line).name !== null">
                <span><strong x-text="replyOf(line).name"></strong> <span x-text="replyOf(line).text"></span></span>
            </template>
            <template x-if="replyOf(line) && replyOf(line).name === null">
                <span class="italic">ข้อความถูกลบแล้ว</span>
            </template>
        </button>
        <p class="break-words leading-relaxed opacity-90">
            <template x-for="(part, index) in partsOf(line)" :key="index">
                <span>
                    <span
                        class="chat-mention"
                        x-show="part.t === 'mention'"
                        x-text="part.v"
                    ></span>
                    <span
                        x-show="part.t === 'text'"
                        :class="{ 'font-bold': part.b, 'italic': part.i, 'underline': part.u, 'line-through': part.s, 'chat-spoiler': part.sp && !spoiled[line.id + '-' + index] }"
                        :style="textStyle(part)"
                        @click="part.sp && (spoiled[line.id + '-' + index] = true)"
                        x-text="part.v"
                    ></span>
                    <img
                        x-show="part.t === 'img'"
                        :src="imageSrc(part)"
                        alt=""
                        class="inline h-4 w-4 align-text-bottom"
                    >
                    <img
                        x-show="part.t === 'image'"
                        :src="embedSrc(part)"
                        alt="รูปในแชท"
                        loading="lazy"
                        referrerpolicy="no-referrer"
                        :class="{ 'chat-spoiler-img': part.sp && !spoiled[line.id + '-' + index] }"
                        @click="part.sp && (spoiled[line.id + '-' + index] = true)"
                        x-on:error="$el.style.display = 'none'"
                        class="mt-1 block max-h-64 max-w-full rounded-lg border border-white/10"
                    >
                </span>
            </template>
        </p>
    </div>
    {{-- The wrapper is the positioning context, so the menu is anchored to its
         button. It opens upward only when the list has room above and too
         little below, so the scrolling list never clips it. --}}
    <div class="relative shrink-0" x-show="actionsFor(line).length">
        <button
            type="button"
            class="px-1 text-xs opacity-60 hover:opacity-100"
            @click="toggleMenu(line, $event)"
            aria-label="Message actions"
        >&#8943;</button>
        <ul
            class="absolute right-0 z-10 rounded-md border border-white/10 bg-neutral-900 p-1 text-xs"
            :class="menuUp ? 'bottom-full mb-1' : 'top-full mt-1'"
            x-show="menuOpen === line.id"
            @click.outside="menuOpen = null"
        >
            <template x-for="action in actionsFor(line)" :key="action">
                <li>
                    <button
                        type="button"
                        class="block w-full whitespace-nowrap rounded px-2 py-1 text-left hover:bg-white/10 disabled:opacity-50"
                        @click="moderate(line, action)"
                        :disabled="!!busy[String(line.id) + action]"
                        x-text="({ reply: 'ตอบกลับ', tag: 'แท็ก', pin: 'ปักหมุด', unpin: 'เลิกปักหมุด', delete: 'Delete', gag: 'Gag 10 min', kick: 'Kick', ban: 'Ban 24 h', report: 'รายงาน' })[action]"
                    ></button>
                </li>
            </template>
        </ul>
    </div>
</li>
