@props(['banners' => [], 'eagerFirst' => false])

@if (! empty($banners))
    <aside class="ads-banner">
        <div class="ads-grid">
            @foreach ($banners as $b)
                @php $d = \App\Support\AdImage::dimensions($b['src']); $eager = $eagerFirst && $loop->first; @endphp
                <a href="{{ $b['href'] }}" target="_blank" rel="{{ ($b['rel'] ?? '') ?: 'nofollow noopener sponsored noreferrer ugc' }}" class="{{ ($b['col'] ?? 6) >= 12 ? 'col-full' : 'col-half' }}">
                    {{-- Non-eager ads use data-ad-src (loaded after LCP by blade.js)
                         so a heavy creative can't become the LCP. On pages with no
                         hero image (episode), the FIRST ad IS the largest element
                         and will be the LCP, so eager-load it (real src + high
                         priority) instead of deferring it to ~20s. width/height
                         reserve exact space either way (no CLS). --}}
                    <img
                        @if ($eager) src="{{ $b['src'] }}" fetchpriority="high" @else data-ad-src="{{ $b['src'] }}" @endif
                        alt="{{ $b['alt'] ?? '' }}"
                        @if ($d) width="{{ $d['w'] }}" height="{{ $d['h'] }}" @else style="aspect-ratio: 728 / 200" @endif
                        decoding="async" referrerpolicy="no-referrer-when-downgrade">
                </a>
            @endforeach
        </div>
    </aside>
@endif
