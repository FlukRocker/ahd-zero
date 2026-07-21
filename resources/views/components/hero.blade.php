@props(['item', 'eager' => true])
@php
    $bg = \App\Support\Img::url($item['landscape'], ['width' => 1600, 'format' => 'webp']) ?? $item['landscape'];
@endphp
<section class="relative mx-auto mt-6 max-w-[1440px] overflow-hidden rounded-3xl px-6 lg:px-10">
    <div class="relative grid gap-8 rounded-3xl border p-8 md:grid-cols-[1.3fr_1fr] md:p-12"
         style="border-color: hsl(var(--border-ahd)); background: hsl(var(--bg-elev));">
        <div class="flex flex-col justify-center">
            <div class="mb-3 font-mono text-[11px] tracking-[0.22em] uppercase" style="color: hsl(var(--accent))">แนะนำ</div>
            <h2 class="font-display text-[40px] leading-none italic md:text-[64px]">{{ $item['title'] }}</h2>
            @if ($item['kanji'])
                <div class="mt-3 font-mono text-[13px]" style="color: hsl(var(--fg-faint))">{{ $item['kanji'] }}</div>
            @endif
            <div class="mt-6 flex items-center gap-3">
                <a href="{{ $item['href'] }}" class="btn btn-primary">▶ ดูเลย</a>
                @if ($item['ep'])
                    <span class="font-mono text-[13px]" style="color: hsl(var(--fg-muted))">{{ $item['ep'] }}</span>
                @endif
            </div>
        </div>
        <div class="relative aspect-[16/10] overflow-hidden rounded-2xl md:aspect-auto">
            <img src="{{ $bg }}" alt="{{ $item['title'] }}"
                 @if ($eager) fetchpriority="high" loading="eager" @else loading="lazy" @endif
                 decoding="async" class="h-full w-full object-cover">
        </div>
    </div>
</section>
