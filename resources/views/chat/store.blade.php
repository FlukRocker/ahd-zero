@extends('layouts.app')

@section('title', 'ร้านค้าแชท')
@section('robots', 'noindex,follow')

@php
    // Same embed pattern as #chat-bootstrap: a script block, never an
    // attribute, with the HEX flags so a hostile item name cannot close it.
    $bootstrap = json_encode(
        ['items' => $items, 'balance' => $balance],
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR,
    );
@endphp

@section('content')
    <x-chat.name-styles />
    <style>
        /* Translucent tint behind the text; the text colour is untouched. */
        .chat-background-preview { position: relative; isolation: isolate; }
        .chat-background-preview::before {
            content: '';
            position: absolute;
            inset: 0;
            z-index: -1;
            border-radius: inherit;
            background: var(--chat-background, transparent);
            opacity: 0.3;
        }
    </style>
    <main class="mx-auto w-full max-w-3xl px-4 py-6" x-data="chatStore">
        <x-chat.tabs active="store" />
        <h1 class="mt-4 text-xl font-semibold">ร้านค้าแชท</h1>
        <p class="text-sm opacity-70">ยอดเหรียญ: <span class="font-semibold text-amber-400" x-text="balance"></span> เหรียญ</p>
        <p class="mt-2 text-sm text-red-400" x-show="error" x-text="error"></p>

        <ul class="mt-4 grid gap-3 sm:grid-cols-2">
            <template x-for="item in items" :key="item.id">
                <li class="rounded-lg border border-white/10 p-3">
                    <template x-if="isBackground(item)">
                        <span
                            class="chat-background-preview block rounded-md px-2 py-1 text-sm"
                            :style="previewStyle(item)"
                            x-text="'ตัวอย่างข้อความจาก ' + item.name"
                        ></span>
                    </template>
                    <span class="chat-icon" x-show="!isBackground(item) && iconOf(item)" :data-icon="iconOf(item)" aria-hidden="true"></span>
                    <span
                        x-show="!isBackground(item)"
                        class="chat-name font-medium"
                        :data-anim="animOf(item)"
                        :class="{ 'chat-name-gradient': hasGradient(item) }"
                        :style="previewStyle(item)"
                        x-text="item.name"
                    ></span>
                    <span class="ml-1 rounded bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-amber-400" x-show="item.limited">ลิมิเต็ด</span>
                    <span class="block text-xs opacity-60" x-text="typeLabel(item)"></span>
                    <span class="text-sm opacity-70"><span x-text="item.price"></span> เหรียญ</span>

                    <span class="mt-2 block text-xs font-semibold text-amber-400" x-show="!item.owned && item.show_only">ไม่ได้วางขาย</span>
                    <span class="mt-2 block text-xs font-semibold text-red-400" x-show="!item.owned && !item.show_only && item.sold_out">สินค้าหมด</span>
                    <template x-if="!item.owned && !item.show_only && !item.sold_out">
                        <button
                            type="button"
                            class="mt-2 block rounded-md bg-emerald-600 px-4 py-1.5 text-sm font-semibold text-white shadow hover:bg-emerald-500 disabled:cursor-not-allowed disabled:bg-white/10 disabled:text-white/40 disabled:shadow-none"
                            :disabled="!!(!canBuy(item))"
                            @click="buy(item)"
                        x-text="balance < item.price ? 'เหรียญไม่พอ' : 'ซื้อ'"></button>
                    </template>
                    <template x-if="item.owned && item.slot !== null && !item.equipped">
                        <button
                            type="button"
                            class="mt-2 block rounded-md bg-sky-600 px-4 py-1.5 text-sm font-semibold text-white shadow hover:bg-sky-500 disabled:cursor-not-allowed disabled:bg-white/10 disabled:text-white/40 disabled:shadow-none"
                            :disabled="!!(busy !== null)"
                            @click="equip(item)"
                        >สวมใส่</button>
                    </template>
                    <template x-if="item.owned && item.slot !== null && item.equipped">
                        <span class="mt-2 inline-block rounded bg-emerald-500/15 px-2 py-0.5 text-xs font-semibold text-emerald-400">กำลังสวมใส่</span>
                    </template>
                    <template x-if="item.owned && item.slot === null">
                        <span class="mt-2 inline-block rounded bg-white/10 px-2 py-0.5 text-xs font-semibold text-white/70">เป็นเจ้าของแล้ว</span>
                    </template>
                </li>
            </template>
        </ul>

        {{-- Result popup after buying or equipping. x-text only. --}}
        <div
            x-show="modal"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4"
            @click.self="closeModal()"
            @keydown.escape.window="closeModal()"
        >
            <div class="w-full max-w-sm rounded-2xl border border-white/10 bg-neutral-900 p-5 text-center shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="store-modal-title">
                <div
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-full text-2xl"
                    :class="modal && modal.ok ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'"
                    x-text="modal && modal.ok ? '✓' : '✕'"
                    aria-hidden="true"
                ></div>
                <h2 id="store-modal-title" class="mt-3 text-lg font-semibold" x-text="modal ? modal.title : ''"></h2>
                <p class="mt-1 text-sm opacity-80" x-text="modal ? modal.message : ''"></p>
                <div class="mt-4 flex justify-center gap-2">
                    <button
                        type="button"
                        class="rounded-md bg-sky-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-sky-500"
                        x-show="modal && modal.ok && modal.item"
                        @click="equipFromModal()"
                    >สวมใส่เลย</button>
                    <button
                        type="button"
                        class="rounded-md px-4 py-1.5 text-sm font-semibold text-white"
                        :class="modal && modal.ok ? 'bg-white/10 hover:bg-white/20' : 'bg-red-600 hover:bg-red-500'"
                        @click="closeModal()"
                    >ปิด</button>
                </div>
            </div>
        </div>
    </main>

    <script type="application/json" id="store-bootstrap">{!! $bootstrap !!}</script>
@endsection
