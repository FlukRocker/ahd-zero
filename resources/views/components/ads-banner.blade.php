@props(['banners' => []])

@if (! empty($banners))
    <aside class="ads-banner">
        <div class="ads-grid">
            @foreach ($banners as $b)
                @php $d = \App\Support\AdImage::dimensions($b['src']); @endphp
                <a href="{{ $b['href'] }}" target="_blank" rel="{{ ($b['rel'] ?? '') ?: 'nofollow noopener sponsored noreferrer ugc' }}" class="{{ ($b['col'] ?? 6) >= 12 ? 'col-full' : 'col-half' }}">
                    {{-- data-ad-src (not src): ads load after LCP via the deferred
                         loader in blade.js so a heavy creative can't become the LCP.
                         width/height still reserve exact space (no CLS). --}}
                    <img data-ad-src="{{ $b['src'] }}" alt="{{ $b['alt'] ?? '' }}"
                        @if ($d) width="{{ $d['w'] }}" height="{{ $d['h'] }}" @else style="aspect-ratio: 728 / 200" @endif
                        decoding="async" referrerpolicy="no-referrer-when-downgrade">
                </a>
            @endforeach
        </div>
    </aside>
@endif
