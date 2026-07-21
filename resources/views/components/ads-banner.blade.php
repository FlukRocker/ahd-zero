@props(['banners' => []])

@if (! empty($banners))
    <aside class="ads-banner">
        <div class="ads-grid">
            @foreach ($banners as $b)
                <a href="{{ $b['href'] }}" target="_blank" rel="{{ ($b['rel'] ?? '') ?: 'nofollow noopener sponsored noreferrer ugc' }}" class="{{ ($b['col'] ?? 6) >= 12 ? 'col-full' : 'col-half' }}">
                    <img src="{{ $b['src'] }}" alt="{{ $b['alt'] ?? '' }}" loading="lazy" decoding="async" referrerpolicy="no-referrer-when-downgrade">
                </a>
            @endforeach
        </div>
    </aside>
@endif
