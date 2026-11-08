{{-- One chat line, one block per message. Alpine renders it client-side from
     the `line` loop variable. Text goes through x-text (a text node), images
     through :src of a fixed <img>; styles are an object of server-validated
     custom properties applied with style.setProperty. Never x-html, never a
     built style string. --}}
<li class="chat-row relative flex items-start gap-3 rounded-lg px-3 py-2 text-sm" :class="{ 'z-20': menuOpen === line.id }" :style="styleOf(line)">
    <span
        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/10 text-xs font-semibold uppercase"
        aria-hidden="true"
        x-text="(line.name || '?').charAt(0)"
    ></span>
    <div class="min-w-0 flex-1">
        <div class="flex items-baseline gap-2">
            <span
                class="chat-name font-medium"
                :class="{ 'chat-name-gradient': hasGradient(line) }"
                x-text="line.name"
            ></span>
            <span
                class="text-[10px] uppercase opacity-60"
                x-show="line.rank && line.rank !== 'member'"
                x-text="line.rank"
            ></span>
        </div>
        <p class="break-words">
            <template x-for="(part, index) in partsOf(line)" :key="index">
                <span>
                    <span x-show="part.t === 'text'" x-text="part.v"></span>
                    <img
                        x-show="part.t === 'img'"
                        :src="imageSrc(part)"
                        alt=""
                        class="inline h-4 w-4 align-text-bottom"
                    >
                </span>
            </template>
        </p>
    </div>
    {{-- The wrapper is the positioning context, so the menu is anchored to its
         button. It opens upward on the last row so it is not cut off by the
         scrolling list. --}}
    <div class="relative shrink-0" x-show="actionsFor(line).length">
        <button
            type="button"
            class="px-1 text-xs opacity-60 hover:opacity-100"
            @click="toggleMenu(line)"
            aria-label="Message actions"
        >&#8943;</button>
        <ul
            class="absolute right-0 z-10 rounded-md border border-white/10 bg-neutral-900 p-1 text-xs"
            :class="isLast(line) ? 'bottom-full mb-1' : 'top-full mt-1'"
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
                        x-text="({ delete: 'Delete', gag: 'Gag 10 min', kick: 'Kick', ban: 'Ban 24 h' })[action]"
                    ></button>
                </li>
            </template>
        </ul>
    </div>
</li>
