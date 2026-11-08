{{-- Floating shortcut to the chat lobby. A plain link: no socket, no JS, so it
     costs nothing on pages that are shared-cached. Not shown on chat pages. --}}
@unless (request()->routeIs('chat.*'))
    <a
        data-chat-bubble
        href="{{ route('chat.show', [], false) }}"
        aria-label="แชท"
        class="fixed bottom-4 right-4 z-40 flex h-12 w-12 items-center justify-center rounded-full bg-[hsl(var(--accent-strong))] text-[hsl(var(--accent-fg))] shadow-lg transition hover:scale-105 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[hsl(var(--accent-strong))] sm:bottom-6 sm:right-6 sm:h-14 sm:w-14"
    >
        <svg viewBox="0 0 24 24" class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.6 8.6 0 0 1-3.4-.7L3 21l1.8-5.1A8.4 8.4 0 1 1 21 11.5Z" />
        </svg>
    </a>
@endunless
