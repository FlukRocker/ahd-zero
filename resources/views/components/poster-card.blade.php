@props(['item', 'eager' => false])
@php
    $src = \App\Support\Img::url($item['poster'], ['width' => 360, 'format' => 'webp']) ?? $item['poster'];
    $srcset = \App\Support\Img::srcset($item['poster'], \App\Support\Img::POSTER_WIDTHS, ['format' => 'webp']);
@endphp
<a href="{{ $item['href'] }}" class="group block">
    <div class="poster-card">
        <div class="halo"></div>
        <img
            src="{{ $src }}"
            @if ($srcset) srcset="{{ $srcset }}" sizes="{{ \App\Support\Img::POSTER_SIZES }}" @endif
            alt="{{ $item['title'] }}"
            loading="{{ $eager ? 'eager' : 'lazy' }}"
            @if ($eager) fetchpriority="high" @endif
            decoding="async"
            width="300"
            height="450"
        >
        @if ($item['tag'])
            <span class="sticker">{{ $item['tag'] }}</span>
        @endif
        <div class="grad-bot"></div>
        <div class="absolute right-3 bottom-3 left-3 text-white">
            <div class="flex items-center gap-2 font-mono text-[11px] opacity-90">
                @if ($item['ep'])<span>{{ $item['ep'] }}</span>@endif
                @if ($item['ep'] && $item['genre'])<span>·</span>@endif
                @if ($item['genre'])<span>{{ $item['genre'] }}</span>@endif
            </div>
        </div>
        <div class="absolute inset-0 flex items-center justify-center opacity-0 transition-opacity group-hover:opacity-100" style="background: rgba(0,0,0,0.25)">
            <span class="btn btn-primary">▶ Watch</span>
        </div>
    </div>
    <div class="mt-3">
        @if ($item['kanji'])
            <div class="mb-1 font-mono text-[11px]" style="color: hsl(var(--fg-faint))">{{ $item['kanji'] }}</div>
        @endif
        <div class="font-display text-[20px] leading-tight">{{ $item['title'] }}</div>
    </div>
</a>
