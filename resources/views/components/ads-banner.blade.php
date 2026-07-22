@props(['banners' => [], 'eagerFirst' => false])

@if (! empty($banners))
    <aside class="ads-banner">
        <div class="ads-grid">
            @foreach ($banners as $b)
                @php $d = \App\Support\AdImage::dimensions($b['src']); $eager = $eagerFirst && $loop->first; @endphp
                <a href="{{ $b['href'] }}" target="_blank" rel="{{ ($b['rel'] ?? '') ?: 'nofollow noopener sponsored noreferrer ugc' }}" class="{{ ($b['col'] ?? 6) >= 12 ? 'col-full' : 'col-half' }}">
                    {{-- The first ad is the likely LCP on ad-topped pages, so load
                         it eagerly at high priority; the rest lazy-load. width/height
                         reserve exact space (no CLS). --}}
                    <img
                        src="{{ $b['src'] }}"
                        alt="{{ $b['alt'] ?? '' }}"
                        @if ($eager) fetchpriority="high" loading="eager" @else loading="lazy" @endif
                        @if ($d) width="{{ $d['w'] }}" height="{{ $d['h'] }}" @else style="aspect-ratio: 728 / 200" @endif
                        decoding="async" referrerpolicy="no-referrer-when-downgrade">
                </a>
            @endforeach
        </div>
    </aside>
@endif
