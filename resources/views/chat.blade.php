@extends('layouts.app')

@section('title', 'Chat')
@section('robots', 'noindex,follow')

@php
    $member = auth('member')->user();
    // Any logged-in member can post; email verification is not required.
    $canPost = $member !== null;

    // The bootstrap goes in a script block, never in an x-data attribute: a
    // double quote in a message body would close the attribute and turn the
    // rest of the payload into markup. The HEX flags also neutralise </script>
    // and ampersands inside the JSON itself. Members are identified by uuid
    // only; members.id is never exposed.
    $bootstrap = json_encode([
        'history' => $history,
        'room' => ['id' => $room->id, 'slug' => $room->slug, 'name' => $room->name],
        'rooms' => $rooms->map(fn ($r) => ['id' => $r->id, 'slug' => $r->slug, 'name' => $r->name, 'topic' => $r->topic])->values()->all(),
        'me' => $member?->uuid,
        'canPost' => $canPost,
        // Null for guests; 'member' when there is no chat_members row.
        'myRank' => $myRank,
        'meName' => $member?->name,
        'pinned' => $pinned,
        'smilies' => $smilies,
        'rules' => \App\Support\Chat\ChatRules::forClient(),
        // No socket config here on purpose: after `artisan optimize` env()
        // returns null outside config files. The browser values are compiled
        // into the bundle by Vite instead; see lobby.js.
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
@endphp

@section('content')
    <x-chat.name-styles />
    <main class="mx-auto w-full max-w-5xl px-4 py-6" x-data="chatLobby" x-init="start()">
        <x-chat.tabs active="chat" />

        {{-- Same look as the homepage chat: wallpaper, frosted panels, rooms and
             the online list on the right. --}}
        <div
            class="relative isolate mt-4 overflow-hidden rounded-2xl border border-white/15 text-white shadow-2xl"
            style="background: linear-gradient(160deg, #7a2f4b 0%, #a8475e 38%, #d9794f 100%);"
        >
            <div class="pointer-events-none absolute -top-20 left-1/4 -z-10 h-96 w-96 rounded-full bg-white/10" aria-hidden="true"></div>
            <div class="pointer-events-none absolute -bottom-32 right-1/4 -z-10 h-[26rem] w-[26rem] rounded-full" style="background: rgba(255, 214, 170, 0.12);" aria-hidden="true"></div>

            <header class="flex items-center justify-between gap-3 border-b border-white/15 bg-black/25 px-4 py-2.5 text-sm backdrop-blur">
                <span class="flex items-center gap-2 font-semibold"><span class="opacity-50">#</span> {{ $room->name }}</span>
                <span class="flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-2.5 py-1 text-xs font-medium text-emerald-400" x-show="config?.canPost" x-cloak>
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                    <span x-text="roster.length"></span> ออนไลน์
                </span>
            </header>

            <div class="grid gap-3 p-3 lg:grid-cols-[minmax(0,1fr)_16rem]">
                <section class="flex h-[min(40rem,78vh)] min-w-0 flex-col overflow-hidden rounded-2xl border border-white/15 backdrop-blur" style="background: rgba(20, 10, 16, 0.6);">
                    <div class="flex items-start gap-2 border-b border-white/10 bg-amber-400/10 px-3 py-2 text-xs" x-show="pinned" x-cloak>
                        <span aria-hidden="true">📌</span>
                        <button type="button" class="min-w-0 flex-1 truncate text-left" @click="jumpTo(pinned.id)">
                            <strong x-text="pinned?.name"></strong> <span class="opacity-80" x-text="pinnedText()"></span>
                        </button>
                        <button type="button" class="shrink-0 underline opacity-70 hover:opacity-100" x-show="canPin()" @click="moderate(pinned, 'unpin')">เลิกปักหมุด</button>
                    </div>

                    <ul class="flex-1 space-y-0.5 overflow-y-auto p-2" x-ref="list">
                        <template x-for="line in messages" :key="line.id">
                            <x-chat.message />
                        </template>
                    </ul>

                    <p class="px-3 pb-2 text-xs text-amber-300" x-show="notice" x-text="notice"></p>

                    @if ($canPost)
                        <x-chat.composer placeholder="Message #{{ $room->name }}" />
                        <p class="px-3 pb-3 text-xs text-red-300" x-show="error" x-text="error"></p>
                    @else
                        <p class="border-t border-white/10 bg-black/20 p-4 text-center text-sm opacity-80">
                            <a href="{{ route('member.login') }}" class="font-medium underline">Log in to join the conversation</a>
                        </p>
                    @endif
                </section>

                <x-chat.sidebar :rooms="$rooms" :room="$room" />
            </div>
        </div>
        <x-chat.report-modal />
    </main>

    <script type="application/json" id="chat-bootstrap">{!! $bootstrap !!}</script>
@endsection
