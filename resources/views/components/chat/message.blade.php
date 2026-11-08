{{-- One chat line. Alpine renders it client-side from the `line` loop variable.
     Text goes through x-text (a text node), images through :src of a fixed
     <img>; styles are an object of server-validated custom properties applied
     with style.setProperty. Never x-html, never a built style string. --}}
<li class="relative text-sm">
    <span
        class="chat-name font-medium"
        :class="{ 'chat-name-gradient': hasGradient(line) }"
        :style="styleOf(line)"
        x-text="line.name"
    ></span>
    <span>
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
    </span>
    <button
        type="button"
        class="ml-1 px-1 text-xs opacity-60 hover:opacity-100"
        x-show="actionsFor(line).length"
        @click="toggleMenu(line)"
        aria-label="Message actions"
    >&#8943;</button>
    <ul
        class="absolute right-0 z-10 mt-1 rounded-md border border-white/10 bg-neutral-900 p-1 text-xs"
        x-show="menuOpen === line.id"
        @click.outside="menuOpen = null"
    >
        <template x-for="action in actionsFor(line)" :key="action">
            <li>
                <button
                    type="button"
                    class="block w-full rounded px-2 py-1 text-left hover:bg-white/10 disabled:opacity-50"
                    @click="moderate(line, action)"
                    :disabled="!!busy[String(line.id) + action]"
                    x-text="({ delete: 'Delete', gag: 'Gag 10 min', kick: 'Kick', ban: 'Ban 24 h' })[action]"
                ></button>
            </li>
        </template>
    </ul>
</li>
