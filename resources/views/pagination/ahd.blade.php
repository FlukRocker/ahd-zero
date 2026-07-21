@if ($paginator->hasPages())
    <nav class="mt-10 flex flex-wrap items-center justify-center gap-1" role="navigation" aria-label="Pagination">
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-3 py-2 font-mono text-[13px] opacity-40" style="color: hsl(var(--fg-muted))">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="inline-block rounded-full px-3 py-2 font-mono text-[13px]" style="background: hsl(var(--fg)); color: hsl(var(--bg));">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="inline-block rounded-full px-3 py-2 font-mono text-[13px] transition-colors" style="background: hsl(var(--bg-soft)); color: hsl(var(--fg-muted)); border: 1px solid hsl(var(--border-ahd));">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
    </nav>
@endif
