@props(['rooms', 'room'])
{{-- Rooms above the online list. Stacks above the messages on phone widths
     (the parent is flex-col below lg) and sits to the side from lg up. --}}
<div data-chat-sidebar class="flex w-full flex-col gap-3 lg:w-56 lg:shrink-0">
    <nav class="rounded-xl border border-white/10 p-3" aria-label="Rooms">
        <h2 class="text-xs font-semibold uppercase opacity-60">Rooms</h2>
        <ul class="mt-2 space-y-1 text-sm">
            @foreach ($rooms as $r)
                <li>
                    <a
                        href="{{ $r->slug === 'lobby' ? route('chat.show', [], false) : route('chat.room', $r->slug, false) }}"
                        class="block rounded px-2 py-1 hover:bg-white/10 {{ $r->id === $room->id ? 'bg-white/10 font-medium' : '' }}"
                        @if ($r->id === $room->id) aria-current="true" @endif
                    ># {{ $r->name }}</a>
                </li>
            @endforeach
        </ul>
    </nav>
    <x-chat.roster />
</div>
