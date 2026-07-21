@props(['banners' => []])

@if (! empty($banners))
    <aside class="ads-banner">
        <div class="ads-grid">
            @foreach ($banners as $b)
                @php $d = \App\Support\AdImage::dimensions($b['src']); @endphp
                <a href="{{ $b['href'] }}" target="_blank" rel="{{ ($b['rel'] ?? '') ?: 'nofollow noopener sponsored noreferrer ugc' }}" class="{{ ($b['col'] ?? 6) >= 12 ? 'col-full' : 'col-half' }}">
                    <img src="{{ $b['src'] }}" alt="{{ $b['alt'] ?? '' }}"
                        @if ($d) width="{{ $d['w'] }}" height="{{ $d['h'] }}" @else style="aspect-ratio: 728 / 200" @endif
                        loading="lazy" decoding="async" referrerpolicy="no-referrer-when-downgrade">
                </a>
            @endforeach
        </div>
    </aside>
@endif
