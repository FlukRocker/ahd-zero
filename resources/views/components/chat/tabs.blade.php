@props(['active' => 'chat'])
{{-- Chat | Store tab bar. Store is members-only, so guests see Chat alone. --}}
<nav class="flex gap-1 border-b border-white/10 px-1" aria-label="Chat sections">
    <a
        href="{{ route('chat.show', [], false) }}"
        class="rounded-t-md px-4 py-2 text-sm font-medium {{ $active === 'chat' ? 'border-b-2 border-current' : 'opacity-60 hover:opacity-100' }}"
        @if ($active === 'chat') aria-current="page" @endif
    >
        Chat
    </a>
    @if (auth('member')->check())
        <a
            href="{{ route('chat.store', [], false) }}"
            class="rounded-t-md px-4 py-2 text-sm font-medium {{ $active === 'store' ? 'border-b-2 border-current' : 'opacity-60 hover:opacity-100' }}"
            @if ($active === 'store') aria-current="page" @endif
        >
            Store
        </a>
    @endif
</nav>
