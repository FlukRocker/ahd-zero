@props(['rooms', 'room'])
{{-- Rooms above the online list, as frosted panels. Stacks under the messages on
     phone widths and sits to the side from lg up. --}}
<div data-chat-sidebar class="flex w-full flex-col gap-3">
    <nav class="rounded-2xl border border-white/15 p-3 backdrop-blur" style="background: rgba(20, 10, 16, 0.6);" aria-label="Rooms">
        <h2 class="text-[11px] font-semibold uppercase tracking-wider opacity-60">Rooms</h2>
        <ul class="mt-2 space-y-1 text-sm">
            @foreach ($rooms as $r)
                <li>
                    <a
                        href="{{ $r->slug === 'lobby' ? route('chat.show', [], false) : route('chat.room', $r->slug, false) }}"
                        class="block rounded-full px-3 py-1.5 transition hover:bg-white/15 {{ $r->id === $room->id ? 'bg-white/90 font-semibold text-[#7a2f4b] hover:bg-white/90' : 'bg-white/5' }}"
                        @if ($r->id === $room->id) aria-current="true" @endif
                    ># {{ $r->name }}</a>
                </li>
            @endforeach
        </ul>
    </nav>
    <div class="overflow-hidden rounded-2xl border border-white/15 backdrop-blur" style="background: rgba(20, 10, 16, 0.6);">
        <x-chat.roster bare />
    </div>
</div>
