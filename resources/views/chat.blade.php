@extends('layouts.app')

@section('title', 'Chat')
@section('robots', 'noindex,follow')

@php
    $member = auth('member')->user();
    // Unverified members read the room but cannot post (the POST route is
    // behind `verified` and would 403/redirect anyway).
    $canPost = $member !== null && $member->hasVerifiedEmail();

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

        <section class="flex min-h-[60vh] min-w-0 flex-1 flex-col rounded-xl border border-white/10">
            <p class="border-b border-white/10 px-3 py-2 text-sm font-medium"># {{ $room->name }}</p>

            <ul class="max-h-[70vh] flex-1 space-y-1 overflow-y-auto p-3" x-ref="list">
                <template x-for="line in messages" :key="line.id">
                    <x-chat.message />
                </template>
            </ul>

            <p class="px-3 pb-2 text-xs text-amber-400" x-show="notice" x-text="notice"></p>

            @if ($canPost)
                <form class="flex gap-2 border-t border-white/10 p-3" @submit.prevent="send()">
                    <input
                        class="flex-1 rounded-md bg-black/20 px-3 py-2 text-sm"
                        x-model="draft"
                        maxlength="500"
                        placeholder="Message #{{ $room->name }}"
                    >
                    <button class="rounded-md px-3 py-2 text-sm font-medium" :disabled="!!sending">Send</button>
                </form>
                <p class="px-3 pb-3 text-xs text-red-400" x-show="error" x-text="error"></p>
            @elseif ($member !== null)
                <p class="border-t border-white/10 p-3 text-sm opacity-70">
                    <a href="{{ route('member.verification.notice') }}" class="underline">Verify your email to join the conversation</a>
                </p>
            @else
                <p class="border-t border-white/10 p-3 text-sm opacity-70">
                    <a href="{{ route('member.login') }}" class="underline">Log in to join the conversation</a>
                </p>
            @endif
        </section>
        </div>
    </main>

    <script type="application/json" id="chat-bootstrap">{!! $bootstrap !!}</script>
@endsection
