@extends('layouts.app')

@section('title', 'Chat store')
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
        <h1 class="mt-4 text-xl font-semibold">Chat store</h1>
        <p class="text-sm opacity-70">Balance: <span x-text="balance"></span> coins</p>
        <p class="mt-2 text-sm text-red-400" x-show="error" x-text="error"></p>

        <ul class="mt-4 grid gap-3 sm:grid-cols-2">
            <template x-for="item in items" :key="item.id">
                <li class="rounded-lg border border-white/10 p-3">
                    <template x-if="isBackground(item)">
                        <span
                            class="chat-background-preview block rounded-md px-2 py-1 text-sm"
                            :style="previewStyle(item)"
                            x-text="'Sample message from ' + item.name"
                        ></span>
                    </template>
                    <span
                        x-show="!isBackground(item)"
                        class="chat-name font-medium"
                        :class="{ 'chat-name-gradient': hasGradient(item) }"
                        :style="previewStyle(item)"
                        x-text="item.name"
                    ></span>
                    <span class="block text-xs opacity-60" x-text="typeLabel(item)"></span>
                    <span class="text-sm opacity-70"><span x-text="item.price"></span> coins</span>

                    <template x-if="!item.owned">
                        <button
                            type="button"
                            class="mt-2 block rounded-md px-3 py-1 text-sm font-medium disabled:opacity-50"
                            :disabled="!!(!canBuy(item))"
                            @click="buy(item)"
                        >Buy</button>
                    </template>
                    <template x-if="item.owned && item.slot !== null && !item.equipped">
                        <button
                            type="button"
                            class="mt-2 block rounded-md px-3 py-1 text-sm font-medium disabled:opacity-50"
                            :disabled="!!(busy !== null)"
                            @click="equip(item)"
                        >Equip</button>
                    </template>
                    <template x-if="item.owned && item.slot !== null && item.equipped">
                        <span class="mt-2 block text-xs uppercase opacity-60">Equipped</span>
                    </template>
                    <template x-if="item.owned && item.slot === null">
                        <span class="mt-2 block text-xs uppercase opacity-60">Owned</span>
                    </template>
                </li>
            </template>
        </ul>
    </main>

    <script type="application/json" id="store-bootstrap">{!! $bootstrap !!}</script>
@endsection
