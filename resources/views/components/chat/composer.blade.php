@props(['placeholder' => 'Message', 'send' => 'Send', 'compact' => false])
{{-- Message box: BBCode toolbar, image attach with preview, send. Everything is
     Alpine state from chatLobby/chatMini; the preview is a blob: URL of the
     picked file, nothing is uploaded until Send. --}}
<form class="border-t border-white/10 {{ $compact ? 'p-2' : 'p-3' }}" @submit.prevent="send()">
    <div class="mb-2 flex flex-wrap items-center gap-1 text-xs" role="toolbar" aria-label="จัดรูปแบบข้อความ">
        <button type="button" class="h-7 w-7 rounded font-bold hover:bg-white/10" title="ตัวหนา" @click="wrap('[b]', '[/b]')">B</button>
        <button type="button" class="h-7 w-7 rounded italic hover:bg-white/10" title="ตัวเอียง" @click="wrap('[i]', '[/i]')">I</button>
        <button type="button" class="h-7 w-7 rounded underline hover:bg-white/10" title="ขีดเส้นใต้" @click="wrap('[u]', '[/u]')">U</button>
        <button type="button" class="h-7 w-7 rounded line-through hover:bg-white/10" title="ขีดฆ่า" @click="wrap('[s]', '[/s]')">S</button>
        <button type="button" class="h-7 rounded px-2 hover:bg-white/10" title="ซ่อนข้อความ (คลิกเพื่อเปิด)" @click="wrap('[spoiler]', '[/spoiler]')">Spoiler</button>
        <span class="mx-1 h-4 w-px bg-white/15" aria-hidden="true"></span>
        <label class="flex h-7 cursor-pointer items-center gap-1 rounded px-2 hover:bg-white/10" title="แนบรูป (JPG, PNG, GIF, WebP · สูงสุด 3 MB)">
            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/></svg>
            <span>รูป</span>
            <input type="file" class="sr-only" accept="image/jpeg,image/png,image/gif,image/webp" @change="pickImage($event)">
        </label>
    </div>

    <div class="mb-2 flex items-center gap-2" x-show="preview" x-cloak>
        <img :src="preview" alt="ตัวอย่างรูป" class="h-14 w-14 rounded-md border border-white/10 object-cover">
        <button type="button" class="text-xs underline opacity-70 hover:opacity-100" @click="clearImage()">ลบรูป</button>
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
    <p class="mt-1 text-[11px] opacity-50">ห้ามแนบลิงก์ · ใช้ [b] [i] [u] [s] [spoiler] ได้</p>
</form>
