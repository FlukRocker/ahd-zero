@props(['eyebrow' => null, 'title', 'link' => null, 'linkHref' => null])

<div class="mb-6 flex items-end justify-between">
    <div>
        @if ($eyebrow)
            <div class="mb-2 font-mono text-[10px] tracking-[0.22em] uppercase" style="color: hsl(var(--fg-faint))">{{ $eyebrow }}</div>
        @endif
        <h2 class="font-display text-[36px] leading-none italic md:text-[44px]">{{ $title }}</h2>
    </div>
    @if ($link)
        <a href="{{ $linkHref ?? '#' }}" class="u-grow hidden font-mono text-[13px] tracking-wider uppercase md:block">{{ $link }} →</a>
    @endif
</div>
