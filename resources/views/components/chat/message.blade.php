{{-- One chat line. Alpine renders it client-side from the `line` loop variable.
     Text goes through x-text (a text node), images through :src of a fixed
     <img>; styles are an object of server-validated custom properties applied
     with style.setProperty. Never x-html, never a built style string. --}}
<li class="text-sm">
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
</li>
