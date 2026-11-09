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

        <div class="mt-4 flex flex-col gap-4 lg:flex-row">
        <x-chat.sidebar :rooms="$rooms" :room="$room" />

        <section class="flex min-h-[60vh] min-w-0 flex-1 flex-col overflow-hidden rounded-xl border border-white/10 bg-black/20">
            <p class="border-b border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold"><span class="opacity-50">#</span> {{ $room->name }}</p>

            <div class="flex items-start gap-2 border-b border-white/10 bg-amber-400/10 px-3 py-2 text-xs" x-show="pinned" x-cloak>
                <span aria-hidden="true">📌</span>
                <button type="button" class="min-w-0 flex-1 truncate text-left" @click="jumpTo(pinned.id)">
                    <strong x-text="pinned?.name"></strong> <span class="opacity-80" x-text="pinnedText()"></span>
                </button>
                <button type="button" class="shrink-0 underline opacity-70 hover:opacity-100" x-show="canPin()" @click="moderate(pinned, 'unpin')">เลิกปักหมุด</button>
            </div>

            <ul class="max-h-[70vh] flex-1 space-y-0.5 overflow-y-auto p-2" x-ref="list">
                <template x-for="line in messages" :key="line.id">
                    <x-chat.message />
                </template>
            </ul>

            <p class="px-3 pb-2 text-xs text-amber-400" x-show="notice" x-text="notice"></p>

            @if ($canPost)
                <x-chat.composer placeholder="Message #{{ $room->name }}" />
                <p class="px-3 pb-3 text-xs text-red-400" x-show="error" x-text="error"></p>
            @else
                <p class="border-t border-white/10 p-3 text-sm opacity-70">
                    <a href="{{ route('member.login') }}" class="underline">Log in to join the conversation</a>
                </p>
            @endif
        </section>
        </div>
        <x-chat.report-modal />
    </main>

    <script type="application/json" id="chat-bootstrap">{!! $bootstrap !!}</script>
@endsection
