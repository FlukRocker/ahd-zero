@props(['placeholder' => 'Message', 'send' => 'Send', 'compact' => false])
{{-- Message box: BBCode toolbar (incl. [img]https-url[/img]) and send.
     Everything is Alpine state from chatLobby/chatMini. --}}
<form class="border-t border-white/10 {{ $compact ? 'p-2' : 'p-3' }}" @submit.prevent="send()">
    <div class="mb-2 flex flex-wrap items-center gap-1 text-xs" role="toolbar" aria-label="จัดรูปแบบข้อความ">
        <button type="button" class="h-7 w-7 rounded font-bold hover:bg-white/10" title="ตัวหนา" @click="wrap('[b]', '[/b]')">B</button>
        <button type="button" class="h-7 w-7 rounded italic hover:bg-white/10" title="ตัวเอียง" @click="wrap('[i]', '[/i]')">I</button>
        <button type="button" class="h-7 w-7 rounded underline hover:bg-white/10" title="ขีดเส้นใต้" @click="wrap('[u]', '[/u]')">U</button>
        <button type="button" class="h-7 w-7 rounded line-through hover:bg-white/10" title="ขีดฆ่า" @click="wrap('[s]', '[/s]')">S</button>
        <button type="button" class="h-7 rounded px-2 hover:bg-white/10" title="ซ่อนข้อความ (คลิกเพื่อเปิด)" @click="wrap('[spoiler]', '[/spoiler]')">Spoiler</button>
        <button type="button" class="h-7 rounded px-2 hover:bg-white/10" title="แทรกรูปจากลิงก์ https (.jpg .png .gif .webp)" @click="wrap('[img]', '[/img]')">รูป</button>
    </div>

    <div class="flex gap-2">
        <input
            x-ref="draftInput"
            class="min-w-0 flex-1 rounded-md bg-black/20 px-3 py-2 text-sm"
            x-model="draft"
            maxlength="500"
            placeholder="{{ $placeholder }}"
            autocomplete="off"
        >
        <button class="rounded-md px-3 py-2 text-sm font-medium" :disabled="!!sending">{{ $send }}</button>
    </div>
    <p class="mt-1 text-[11px] opacity-50">ห้ามแนบลิงก์ · ใช้ [b] [i] [u] [s] [spoiler] และ [img]ลิงก์รูป https[/img] ได้</p>
</form>
