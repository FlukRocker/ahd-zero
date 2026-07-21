@props(['ad' => null])

@if (! empty($ad))
    @php $d = \App\Support\AdImage::dimensions($ad['src']); @endphp
    <a href="{{ $ad['href'] }}" rel="{{ ($ad['rel'] ?? '') ?: 'nofollow noopener sponsored noreferrer ugc' }}" target="_blank" {{ $attributes->merge(['class' => 'player-ad-slot block w-full overflow-hidden rounded-lg']) }} style="background: hsl(var(--bg-soft))">
        <img src="{{ $ad['src'] }}" alt="{{ $ad['alt'] ?? '' }}" class="block w-full"
            @if ($d) width="{{ $d['w'] }}" height="{{ $d['h'] }}" style="height:auto" @else style="aspect-ratio: 728 / 90; height: auto" @endif
            loading="lazy" decoding="async" referrerpolicy="no-referrer-when-downgrade">
    </a>
@endif
