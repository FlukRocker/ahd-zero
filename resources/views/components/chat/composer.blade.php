@props(['placeholder' => 'Message', 'send' => 'Send', 'compact' => false])
{{-- Message box: BBCode toolbar (incl. [img]https-url[/img]) and send.
     Everything is Alpine state from chatLobby/chatMini. --}}
{{-- Rules gate: shown instead of the form until this browser has accepted the
     current rules text (kept in localStorage, keyed by a hash of the text). --}}
{{-- One root element: this component is used inside <template x-if>, which only renders a single root. --}}
<div>
<div class="border-t border-white/10 {{ $compact ? 'p-2' : 'p-3' }}" x-show="rulesPending()" x-cloak>
    <p class="mb-1 text-sm font-semibold">กติกาการใช้แชท</p>
    <p class="mb-2 max-h-40 overflow-y-auto whitespace-pre-line text-xs leading-relaxed opacity-80" x-text="config?.rules?.text"></p>
    <button type="button" class="rounded-md bg-[hsl(var(--accent-strong))] px-3 py-2 text-sm font-medium text-[hsl(var(--accent-fg))]" @click="acceptRules()">ยอมรับและเริ่มแชท</button>
</div>

<form class="relative border-t border-white/10 {{ $compact ? 'p-2' : 'p-3' }}" x-show="!rulesPending()" @submit.prevent="send()">
    <div class="mb-2 flex items-center gap-2 rounded-md bg-white/5 px-2 py-1 text-xs" x-show="replyTo" x-cloak>
        <span aria-hidden="true">↪</span>
        <span class="min-w-0 flex-1 truncate">ตอบกลับ <strong x-text="replyTo?.name"></strong> <span class="opacity-70" x-text="replyTo?.text"></span></span>
        <button type="button" class="px-1 opacity-70 hover:opacity-100" aria-label="ยกเลิกการตอบกลับ" @click="replyTo = null">&times;</button>
    </div>
    <ul class="absolute bottom-full left-3 right-3 z-30 mb-1 overflow-hidden rounded-md border border-white/10 bg-neutral-900 text-sm shadow-lg" x-show="mentionOptions.length" x-cloak>
        <template x-for="person in mentionOptions" :key="person.uuid">
            <li><button type="button" class="block w-full truncate px-3 py-1.5 text-left hover:bg-white/10" @click="pickMention(person)">@<span x-text="person.name"></span></button></li>
        </template>
    </ul>
    <div class="mb-2 flex flex-wrap items-center gap-1 text-xs" role="toolbar" aria-label="จัดรูปแบบข้อความ">
        <button type="button" class="h-7 w-7 rounded font-bold hover:bg-white/10" title="ตัวหนา" @click="wrap('[b]', '[/b]')">B</button>
        <button type="button" class="h-7 w-7 rounded italic hover:bg-white/10" title="ตัวเอียง" @click="wrap('[i]', '[/i]')">I</button>
        <button type="button" class="h-7 w-7 rounded underline hover:bg-white/10" title="ขีดเส้นใต้" @click="wrap('[u]', '[/u]')">U</button>
        <button type="button" class="h-7 w-7 rounded line-through hover:bg-white/10" title="ขีดฆ่า" @click="wrap('[s]', '[/s]')">S</button>
        <button type="button" class="h-7 rounded px-2 hover:bg-white/10" title="ซ่อนข้อความ (คลิกเพื่อเปิด)" @click="wrap('[spoiler]', '[/spoiler]')">Spoiler</button>
        <button type="button" class="h-7 rounded px-2 hover:bg-white/10" title="แทรกรูปจากลิงก์ https (.jpg .png .gif .webp)" @click="wrap('[img]', '[/img]')">รูป</button>
    </div>

    <div class="mb-2 flex flex-wrap items-center gap-1" x-show="smileyList().length" x-cloak>
        <template x-for="s in smileyList()" :key="s.code">
            <button type="button" class="rounded-lg p-1 hover:bg-white/10" :title="s.code" @click="insertSmiley(s.code)">
                <img :src="s.url" :alt="s.code" class="h-6 w-6">
            </button>
        </template>
    </div>

    <div class="flex items-center gap-2 rounded-full border border-white/10 bg-black/30 py-1 pl-4 pr-1 focus-within:border-white/30">
        <input
            x-ref="draftInput"
            class="min-w-0 flex-1 bg-transparent py-1.5 text-sm outline-none placeholder:opacity-50"
            x-model="draft"
            @input="onDraftInput()"
            @keydown.escape="mentionOptions = []"
            maxlength="500"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
        >
        <button class="flex h-9 shrink-0 items-center gap-1.5 rounded-full bg-[hsl(var(--accent-strong))] px-4 text-sm font-medium text-[hsl(var(--accent-fg))] transition hover:brightness-110 disabled:opacity-50" :disabled="!!sending">
            <span>{{ $send }}</span>
            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
        </button>
    </div>
    <p class="mt-1 text-[11px] opacity-50">ห้ามแนบลิงก์ · ใช้ [b] [i] [u] [s] [spoiler] และ [img]ลิงก์รูป https[/img] ได้ · พิมพ์ @ เพื่อแท็กคนออนไลน์</p>
</form>
</div>
